<?php
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Binder;
use Gt\Http\Uri;
use Gt\Routing\Path\DynamicPath;
use GT\Website\Content\LinkNormaliser;
use Gt\Website\Content\MarkdownPage;

function go_before(
	Binder $binder,
	DynamicPath $dynamicPath,
	MarkdownPage $markdown,
	HTMLDocument $document,
	Uri $uri,
):void {
	if($doc = urldecode($dynamicPath->get("doc"))) {
		$binder->bindKeyValue("repo", $dynamicPath->get("repo"));
		$binder->bindKeyValue("doc", $doc);
		$binder->bindKeyValue("docTitle", str_replace("-", " ", ucfirst($doc)));
	}

	$markdown->loadDocument($document);

	$linkNormaliser = new LinkNormaliser($document->querySelector("article"));
	$linkNormaliser->normalise($uri);
}
