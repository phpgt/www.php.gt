<?php
// Run: php test/offline/bundler.php (starts an isolated local HTTP fixture).
require dirname(__DIR__, 2) . "/vendor/autoload.php";
use GT\Website\Offline\StaticBundler;

$dir = sys_get_temp_dir() . "/phpgt-bundle-test-" . bin2hex(random_bytes(6));
mkdir($dir);
$router = <<<'ROUTER'
<?php
if(($_SERVER['HTTP_X_PHPGT'] ?? '') !== 'Static Bundler') { http_response_code(403); return; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$countsFile = __DIR__ . '/counts.json';
$counts = is_file($countsFile) ? json_decode(file_get_contents($countsFile), true) : [];
$counts[$path] = ($counts[$path] ?? 0) + 1;
file_put_contents($countsFile, json_encode($counts));
if($path === '/sitemap.xml') { header('Content-Type: application/xml'); echo '<urlset><url><loc>https://www.php.gt/</loc></url></urlset>'; }
elseif($path === '/') { header('Content-Type: text/html'); header('Cache-Control: no-store'); echo '<!doctype html><html><head><link rel="stylesheet" href="/style.css"></head><body><a href="/old/">Help</a><a href="https://external.invalid/">External</a><a href="/search/?q=x">Search</a><img src="/asset/a.png"><img srcset="/asset/a.png 1x, /asset/b.png 2x"><a href="/retry/">Retry</a><a href="/missing/">Missing</a><a href="/broken/">Broken</a><a href="/forbidden/">Forbidden</a><a href="/bad-alias/">Bad alias</a></body></html>'; }
elseif($path === '/old/') { header('Location: /help/', true, 301); }
elseif($path === '/help/') { header('Content-Type: text/html'); header('Cache-Control: private'); echo '<!doctype html><html><body>Help</body></html>'; }
elseif($path === '/retry/') { if($counts[$path] < 3) { http_response_code(503); } else { header('Content-Type: text/html'); echo '<html><body>Recovered</body></html>'; } }
elseif($path === '/broken/') { http_response_code(500); }
elseif($path === '/forbidden/') { http_response_code(403); }
elseif($path === '/bad-alias/') { header('Location: /missing/', true, 301); }
elseif($path === '/style.css') { header('Content-Type: text/css'); echo '@import "/more.css"; body {background:url("asset/a.png")}'; }
elseif($path === '/more.css') { header('Content-Type: text/css'); echo '@font-face{font-family:x;src:url("/asset/font.woff2")}'; }
elseif(str_starts_with($path, '/asset/')) { header('Content-Type: application/octet-stream'); echo "\x00\xff\x80"; }
else { http_response_code(404); }
ROUTER;
file_put_contents("$dir/router.php", $router);
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, "$dir/router.php"], [0 => ['pipe','r'], 1 => ['file', "$dir/server.log", 'a'], 2 => ['file', "$dir/server.log", 'a']], $pipes);
try {
	for($i = 0; $i < 100; $i++) {
		$ready = @stream_socket_client("tcp://$address", $errno, $error, 0.1);
		if($ready) { fclose($ready); break; }
		usleep(20000);
	}
	$builder = new StaticBundler("http://$address");
	ob_start();
	$builder->build("$dir/html", "$dir/bundle.json");
	$progress = ob_get_clean();
	echo $progress;
	preg_match_all('/^\s*([0-9]+\.[0-9]+)% \[([0-9]+)\/([0-9]+) known URLs\] (http[^\s]+)$/m', $progress, $lines, PREG_SET_ORDER);
	if(count($lines) !== 13) throw new RuntimeException("Expected progress for each unique URL");
	foreach($lines as $index => $line) {
		if((int)$line[2] !== $index || (int)$line[3] < $index + 1) throw new RuntimeException("Incorrect progress counts");
		if(abs((float)$line[1] - 100 * $index / (int)$line[3]) > 0.01) throw new RuntimeException("Incorrect progress percentage");
	}
	if(!str_contains($progress, "100.00% Bundled 9 URLs")) throw new RuntimeException("Missing completion progress");
	$bundle = json_decode(file_get_contents("$dir/bundle.json"), true, flags: JSON_THROW_ON_ERROR);
	$entries = array_column($bundle['entries'], null, 'url');
	foreach(['/', '/old/', '/help/', '/style.css', '/more.css', '/asset/a.png', '/asset/b.png', '/asset/font.woff2', '/retry/'] as $path) {
		if(!isset($entries[$path])) throw new RuntimeException("Missing $path");
	}
	if(base64_decode($entries['/asset/a.png']['body']) !== "\x00\xff\x80") throw new RuntimeException('Binary corruption');
	if($entries['/old/']['redirect'] !== '/help/') throw new RuntimeException('Missing redirect');
	if(count($entries) !== 9) throw new RuntimeException('Unexpected URLs');
	$counts = json_decode(file_get_contents("$dir/counts.json"), true);
	if($counts['/retry/'] !== 3 || $counts['/broken/'] !== 3) throw new RuntimeException('Expected two retries for server errors');
	if($counts['/missing/'] !== 1 || $counts['/forbidden/'] !== 1) throw new RuntimeException('Client errors must not be retried');
	foreach(['/missing/', '/broken/', '/forbidden/', '/bad-alias/'] as $path) {
		if(isset($entries[$path])) throw new RuntimeException("Failed response was bundled: $path");
	}
	if(!str_contains($progress, 'Skipped 4 failed URLs')) throw new RuntimeException('Missing failure summary');
	$previous = file_get_contents("$dir/bundle.json");
	file_put_contents("$dir/router.php", '<?php http_response_code(503);');
	try { $builder->build("$dir/html", "$dir/bundle.json"); throw new LogicException('Expected HTTP failure'); }
	catch(RuntimeException $error) {
	}
	if(file_get_contents("$dir/bundle.json") !== $previous) throw new RuntimeException('Failed build replaced bundle');
	echo "Bundler: header, sitemap, HTML/CSS dependencies, srcset, redirects, binary and atomic failure passed\n";
}
finally {
	proc_terminate($process);
	proc_close($process);
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($dir);
}
