<?php
use GT\Dom\HTMLDocument;
use GT\DomTemplate\Binder;
use GT\Http\Uri;
use GT\Http\Request;
use GT\Http\Response;
use GT\Routing\Path\DynamicPath;
use GT\Website\Content\LinkNormaliser;
use GT\Website\Content\MarkdownPage;

function go_before(
	Binder $binder,
	DynamicPath $dynamicPath,
	MarkdownPage $markdown,
	HTMLDocument $document,
	Uri $uri,
):void {
	$gitHash = trim(shell_exec('git rev-parse HEAD') ?? '');
	// Builds and dependency upgrades can change assets without a new site commit.
	$assetHash = hash_init("sha256");
	foreach(["www/script.js", "www/style.css", "www/sw.js"] as $asset) {
		if(is_file($asset)) {
			hash_update_file($assetHash, $asset);
		}
	}
	$binder->bindKeyValue("version", "$gitHash-" . substr(hash_final($assetHash), 0, 16));

	$repo = $dynamicPath->get("repo");

	if($doc = urldecode($dynamicPath->get("doc"))) {
		$binder->bindKeyValue("repo", $repo);
		$binder->bindKeyValue("doc", $doc);
		$binder->bindKeyValue("docTitle", str_replace("-", " ", ucfirst($doc)));
	}

	$markdown->loadDocument($document);

	$linkNormaliser = new LinkNormaliser($document->querySelector("article"));
	$linkNormaliser->normalise($uri);
}

function go_after(Request $request, Response $response, Uri $uri):void {
	if($request->getMethod() !== "GET" || !in_array($response->getStatusCode(), [0, 200], true)) {
		return;
	}
	$path = rtrim($uri->getPath(), "/");
	$public = ["", "/news", "/releases", "/blueprints", "/contributing", "/help", "/learn", "/legal"];
	if(!in_array($path, $public, true) && !str_starts_with($path, "/docs/")) {
		return;
	}
	$query = $uri->getQuery();
	if($query !== "" && ($path !== "/releases" || !preg_match('/^page=\d+$/', $query))) {
		return;
	}
	// These pages contain public content; form submissions keep PHP's no-store policy.
	$response->headers->set("Cache-Control", "public, max-age=0, must-revalidate");
	$response->headers->remove("Pragma");
	$response->headers->remove("Expires");
}
