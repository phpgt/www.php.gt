<?php
use Gt\DomTemplate\Binder;
use Gt\Routing\Path\DynamicPath;
use GT\Website\Content\MarkdownPage;

function go(
	Binder $binder,
	DynamicPath $dynamicPath,
):void {
	$doc = $dynamicPath->get("doc");

	$binder->bindKeyValue("repo", $dynamicPath->get("repo"));
	$binder->bindKeyValue("doc", $doc);
	$binder->bindKeyValue("docTitle", str_replace("-", " ", ucfirst($doc)));
}
