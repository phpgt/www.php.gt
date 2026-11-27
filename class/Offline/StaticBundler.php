<?php
namespace GT\Website\Offline;

use GT\Dom\Element;
use GT\Dom\HTMLDocument;
use GT\Fetch\Http;
use RuntimeException;
use Throwable;

/** Builds a public, same-origin snapshot without executing client JavaScript. */
class StaticBundler {
	public function __construct(private string $origin = "https://www.php.gt", private int $concurrency = 10) {
		$this->origin = rtrim($origin, "/");
		if($concurrency < 1) throw new \InvalidArgumentException("Concurrency must be at least 1");
	}

	public function build(string $htmlDir = "data/static-html", string $output = "www/static-bundle.json"):void {
		if(!is_dir($htmlDir)) mkdir($htmlDir, 0775, true);
		$lock = fopen("$htmlDir/.lock", "c");
		if(!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException("Static bundler already running");
		try {
			$this->generate($htmlDir, $output);
		}
		finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function generate(string $htmlDir, string $output):void {
		$started = (int)floor(microtime(true) * 1000);
		$percent = 0;
		printf("  0.00%% Loading sitemap: %s/sitemap.xml\n", $this->origin);
		$sitemap = $this->fetchBatch(["$this->origin/sitemap.xml"], $percent)["$this->origin/sitemap.xml"][0];
		if(!$sitemap?->ok) throw new RuntimeException("Cannot download sitemap");
		$xml = simplexml_load_string($sitemap->awaitText(), options: LIBXML_NONET);
		if(!$xml) throw new RuntimeException("Invalid sitemap");
		$queue = ["$this->origin/"];
		foreach($xml->xpath('//*[local-name()="loc"]') as $location) {
			$path = parse_url((string)$location, PHP_URL_PATH);
			if($url = $this->resolve($path, "$this->origin/")) $queue[] = $url;
		}
		$queue = array_values(array_unique($queue));
		$seen = [];
		$entries = [];
		$failures = [];
		$pending = [];
		for($i = 0; $i < count($queue); $i++) {
			$url = $queue[$i];
			if(isset($seen[$url])) continue;
			$seen[$url] = true;
			if(count($seen) > 20000) throw new RuntimeException("Static crawl exceeded 20000 URLs");
			$percent = 100 * $i / count($queue);
			printf("%6.2f%% [%d/%d known URLs] %s\n", $percent, $i, count($queue), $url);
			flush();
			if(!$pending) {
				$pending = $this->fetchBatch(array_slice($queue, $i, $this->concurrency), $percent);
			}
			[$response, $requestError] = $pending[$url];
			unset($pending[$url]);
			$path = substr($url, strlen($this->origin));
			if(!$response) {
				$failures[$url] = $requestError;
				printf("%6.2f%% SKIP %s (%s)\n", $percent, $url, $requestError);
				continue;
			}
			if(in_array($response->status, [301, 302, 303, 307, 308])) {
				$target = $this->resolve($response->getHeaderLine("Location"), $url);
				if(!$target) {
					$failures[$url] = "Unsupported redirect destination";
					printf("%6.2f%% SKIP %s (%s)\n", $percent, $url, $failures[$url]);
					continue;
				}
				$entries[$path] = ["url" => $path, "redirect" => substr($target, strlen($this->origin))];
				$queue[] = $target;
				$queue = array_values(array_unique($queue));
				continue;
			}
			if(!$response->ok) {
				$failures[$url] = "HTTP {$response->status}";
				printf("%6.2f%% SKIP %s (%s)\n", $percent, $url, $failures[$url]);
				continue;
			}
			$body = $response->awaitText();
			$type = $response->getHeaderLine("Content-Type") ?: "application/octet-stream";
			$entries[$path] = ["url" => $path, "contentType" => $type, "body" => base64_encode($body)];
			if(str_contains($type, "text/html")) {
				// Hash filenames keep arbitrary URL paths safely inside the snapshot directory.
				if(file_put_contents("$htmlDir/" . hash("sha256", $path) . ".html", $body) === false) {
					throw new RuntimeException("Cannot write HTML snapshot");
				}
				$document = new HTMLDocument($body);
				$base = $url;
				if($element = $document->querySelector("base[href]")) {
					$base = $this->resolve($element->getAttribute("href"), $url) ?? $url;
				}

				/** @var Element $element */
				foreach($document->querySelectorAll("a[href], link[href], script[src], img[src], source[src], video[poster], input[src], image") as $element) {
					// Don't follow resource hints, canonical URLs, feeds or form actions.
					if($element->localName === "link" && !preg_match('/stylesheet|icon|preload|modulepreload|manifest/', $element->getAttribute("rel"))) continue;
					$value = $element->getAttribute("href") ?: $element->getAttribute("src") ?: $element->getAttribute("poster");
					if($next = $this->resolve($value, $base)) {
						// Empty release pages still contain an infinite next-page link.
						if(str_contains($next, "/releases/?page=") && !str_contains($body, "/releases/tag/")) continue;
						$queue[] = $next;
					}
				}
				foreach($document->querySelectorAll("[srcset]") as $element) {
					foreach(explode(",", $element->getAttribute("srcset")) as $candidate) {
						$value = preg_split('/\s+/', trim($candidate))[0];
						if($next = $this->resolve($value, $base)) $queue[] = $next;
					}
				}
				foreach($document->querySelectorAll("style, [style]") as $element) {
					$this->cssLinks($element->localName === "style" ? $element->textContent : $element->getAttribute("style"), $base, $queue);
				}
			}
			elseif(str_contains($type, "text/css")) $this->cssLinks($body, $url, $queue);
			$queue = array_values(array_unique($queue));
		}
		// Do not publish aliases whose destination failed, or redirect cycles.
		foreach($entries as $path => $entry) {
			$target = $path;
			$chain = [];
			while(isset($entries[$target]["redirect"]) && !isset($chain[$target])) {
				$chain[$target] = true;
				$target = $entries[$target]["redirect"];
			}
			if(!isset($entries[$target]) || isset($entries[$target]["redirect"])) {
				unset($entries[$path]);
				$failures[$this->origin . $path] = "Redirect destination unavailable";
			}
		}
		if(!$entries) throw new RuntimeException("No successful responses; existing bundle preserved");
		ksort($entries);
		$entries = array_values($entries);
		$worker = is_file("www/sw.js") ? file_get_contents("www/sw.js") : "";
		preg_match('/const APP_BUILD = "([^"]+)";/', $worker, $build);
		$json = json_encode(["appBuild" => $build[1] ?? null, "version" => hash("sha256", json_encode($entries, JSON_THROW_ON_ERROR)), "generatedAt" => $started, "entries" => $entries], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		$temp = "$output.tmp";
		if(file_put_contents($temp, $json) === false || !rename($temp, $output)) throw new RuntimeException("Cannot publish static bundle");
		echo "100.00% Bundled " . count($entries) . " URLs into $output", PHP_EOL;
		if($failures) {
			echo "100.00% Skipped " . count($failures) . " failed URLs:", PHP_EOL;
			foreach($failures as $url => $reason) echo "100.00% $reason: $url", PHP_EOL;
		}
	}


	/** Queue a bounded group before driving Fetch's event loop. */
	private function fetchBatch(array $urls, float $percent):array {
		$results = [];
		$remaining = $urls;
		for($attempt = 0; $attempt < 3 && $remaining; $attempt++) {
			$http = new Http(
				[CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_ENCODING => ""],
				curlMultiClass: BundlerCurlMulti::class,
			);
			foreach($remaining as $url) {
				$results[$url] = [null, "No response"];
				$http->fetch($url, ["headers" => ["X-PHPGT" => "Static Bundler"]])
					->then(function($response) use(&$results, $url) {
						$results[$url] = [$response, "HTTP {$response->status}"];
					})
					->catch(function(Throwable $error) use(&$results, $url) {
						$results[$url] = [null, $error->getMessage()];
					});
			}
			try {
				// Promises can resolve on the first body chunk: wait for every
				// transfer to finish before parsing or releasing its resources.
				$http->wait();
			}
			finally {
				unset($http);
				gc_collect_cycles();
			}
			$remaining = array_values(array_filter($remaining, function($url) use($results) {
				$response = $results[$url][0];
				return !$response || $response->status === 0 || $response->status >= 500;
			}));
			if($attempt < 2 && $remaining) {
				foreach($remaining as $url) printf("%6.2f%% RETRY %d/2 %s (%s)\n", $percent, $attempt + 1, $url, $results[$url][1]);
				usleep(100000 * ($attempt + 1));
			}
		}
		return $results;
	}

	private function cssLinks(string $css, string $base, array &$queue):void {
		preg_match_all('/url\(\s*[\'"]?([^\s\'"\)]+)|@import\s+[\'"]([^\'"]+)/i', $css, $matches, PREG_SET_ORDER);
		foreach($matches as $match) {
			if($next = $this->resolve($match[2] ?? $match[1], $base)) $queue[] = $next;
		}
	}

	public function resolve(string $value, string $base):?string {
		$value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5);
		if($value === "" || $value[0] === "#") return null;
		if(str_starts_with($value, "//")) $value = parse_url($base, PHP_URL_SCHEME) . ":$value";
		if(!preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
			$basePath = parse_url($base, PHP_URL_PATH) ?: "/";
			$value = $this->origin . (str_starts_with($value, "/") ? $value : (str_starts_with($value, "?") ? $basePath : substr($basePath, 0, strrpos($basePath, "/") + 1)) . $value);
		}
		$parts = parse_url($value);
		if(!$parts || (($parts["scheme"] ?? "") . "://" . ($parts["host"] ?? "") . (isset($parts["port"]) ? ":{$parts['port']}" : "")) !== $this->origin || isset($parts["user"])) return null;
		$segments = [];
		foreach(explode("/", $parts["path"] ?? "/") as $segment) {
			if($segment === "..") array_pop($segments);
			elseif($segment !== ".") $segments[] = $segment;
		}
		$path = implode("/", $segments) ?: "/";
		if(preg_match('~^/(search|api)(/|$)|^/(static-bundle\.json|sw\.js|service-worker\.js|__offline-)~i', $path)) return null;
		if(isset($parts["query"]) && !preg_match('~^/asset/|\.[a-z0-9]+$~i', $path)) {
			if(!preg_match('~^/releases/?$~', $path) || !preg_match('/^page=([0-9]+)$/', $parts["query"], $page) || (int)$page[1] > 100) return null;
		}
		return $this->origin . $path . (isset($parts["query"]) ? "?{$parts['query']}" : "");
	}
}
