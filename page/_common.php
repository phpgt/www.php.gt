<?php
use Gt\Dom\HTMLDocument;
use Gt\Routing\Path\DynamicPath;
use Gt\Website\Content\MarkdownPage;

function go_after(
	MarkdownPage $markdown,
	HTMLDocument $document,
):void {
	$markdown->loadDocument($document);
}
