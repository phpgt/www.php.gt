<?php
const BASE_URL = "https://www.php.gt";

function go(
	string $pageDir = "page",
	string $contentDir = "data/content",
	string $outputPath = "www/sitemap.xml",
):void {
	$urlMap = [];

	foreach(getStaticPagePathList($pageDir) as [$path, $lastModified]) {
		addUrl($urlMap, $path, $lastModified);
	}

	foreach(getDocumentationPathList($contentDir) as [$path, $lastModified]) {
		addUrl($urlMap, $path, $lastModified);
	}

	ksort($urlMap);
	writeSitemap($urlMap, $outputPath);

	echo "Wrote " . count($urlMap) . " URLs to $outputPath", PHP_EOL;
}

function addUrl(array &$urlMap, string $path, int $lastModified):void {
	if(!isset($urlMap[$path]) || $lastModified > $urlMap[$path]) {
		$urlMap[$path] = $lastModified;
	}
}

function getStaticPagePathList(string $pageDir):Generator {
	$directoryIterator = new RecursiveDirectoryIterator(
		$pageDir,
		FilesystemIterator::SKIP_DOTS
	);
	$iterator = new RecursiveIteratorIterator($directoryIterator);

	foreach($iterator as $file) {
		/** @var SplFileInfo $file */
		if($file->getExtension() !== "html") {
			continue;
		}

		$relativePath = substr($file->getPathname(), strlen("$pageDir/"));
		if(isInternalPagePath($relativePath)) {
			continue;
		}

		$routePath = "/" . substr($relativePath, 0, -5);
		if($routePath === "/index") {
			$routePath = "/";
		}
		elseif(str_ends_with($routePath, "/index")) {
			$routePath = substr($routePath, 0, -5);
		}

		if($routePath !== "/" && str_ends_with($routePath, "/")) {
			$routePath = rtrim($routePath, "/");
		}
		if($routePath === "/docs") {
			$routePath = "/docs/";
		}

		yield [$routePath, $file->getMTime()];
	}
}

function isInternalPagePath(string $relativePath):bool {
	foreach(explode(DIRECTORY_SEPARATOR, $relativePath) as $part) {
		if($part === "") {
			continue;
		}

		$firstCharacter = $part[0];
		if($firstCharacter === "_" || $firstCharacter === "@") {
			return true;
		}
	}

	return false;
}

function getDocumentationPathList(string $contentDir):Generator {
	foreach(glob("$contentDir/*", GLOB_ONLYDIR) as $repoDir) {
		$repo = basename($repoDir);
		if($repo[0] === ".") {
			continue;
		}

		foreach(glob("$repoDir/*.md") as $markdownFilePath) {
			$doc = pathinfo($markdownFilePath, PATHINFO_FILENAME);
			if($doc[0] === "_") {
				continue;
			}

			$path = sprintf(
				"/docs/%s/%s/",
				rawurlencode($repo),
				rawurlencode($doc),
			);
			yield [$path, filemtime($markdownFilePath)];
		}
	}
}

function writeSitemap(array $urlMap, string $outputPath):void {
	$writer = new XMLWriter();
	$writer->openMemory();
	$writer->startDocument("1.0", "UTF-8");
	$writer->setIndent(true);
	$writer->startElement("urlset");
	$writer->writeAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");

	foreach($urlMap as $path => $lastModified) {
		$writer->startElement("url");
		$writer->writeElement("loc", BASE_URL . $path);
		$writer->writeElement("lastmod", gmdate("Y-m-d", $lastModified));
		$writer->endElement();
	}

	$writer->endElement();
	$writer->endDocument();

	file_put_contents($outputPath, $writer->outputMemory());
}

// TODO: Automate when cron is implemented.
chdir(__DIR__ . "/..");
go();
