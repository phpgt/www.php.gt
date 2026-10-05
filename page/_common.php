<?php
use GT\Dom\HTMLDocument;
use GT\DomTemplate\Binder;
use GT\Http\Uri;
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
	$binder->bindKeyValue("version", $gitHash);

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
