<?php
use Gt\Dom\HTMLDocument;
use Gt\Http\Response;
use Gt\Routing\Path\DynamicPath;

function go(
	DynamicPath $dynamicPath,
	HTMLDocument $document,
	Response $response,
):void {
	$repoPage = $dynamicPath->get("repo");
	$docPage = urldecode($dynamicPath->get("doc"));

// Ensure the URL is cased correctly according to the repo names.
	$repoDirName = null;
	foreach(glob("data/content/*/") as $dir) {
		$dirName = pathinfo($dir, PATHINFO_FILENAME);
		if(strtolower($dirName) === strtolower($repoPage)) {
			$repoDirName = $dirName;
			break;
		}
	}

	$docFileName = null;
	if($repoDirName) {
		foreach(glob("data/content/$repoDirName/*.md") as $filePath) {
			$fileName = pathinfo($filePath, PATHINFO_FILENAME);
			if(strtolower($fileName) === strtolower($docPage)) {
				$docFileName = $fileName;
				break;
			}
		}

		if(
			$repoDirName !== $repoPage
			|| ($docFileName !== null && $docFileName !== $docPage)
		) {
			$response->redirect(
				"/docs/$repoDirName/" . ($docFileName ?? $docPage),
				301
			);
		}
	}

	if(strtolower($docPage) === "home") {
		$firstHeading = $document->querySelector("article h1");
		$firstHeading->innerText = "$repoPage documentation";
	}

// Normalise all relative links:
	foreach($document->querySelectorAll("main article a") as $link) {
		if($link->href[0] !== "/") {
			continue;
		}

		$link->href = "/docs/$repoPage" . $link->href;
	}
}
