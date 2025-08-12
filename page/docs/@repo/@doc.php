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
	foreach(glob("data/content/*/") as $dir) {
		$dirName = pathinfo($dir, PATHINFO_FILENAME);
		if(strtolower($dirName) === $repoPage) {
			$response->redirect("/docs/$dirName/$docPage");
		}
	}

	if(strtolower($docPage) === "home") {
		$firstHeading = $document->querySelector("article h1");
		$firstHeading->innerText = "$repoPage documentation";
	}
}
