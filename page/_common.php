<?php
use Gt\Dom\HTMLDocument;
use Gt\Website\Content\MarkdownPage;

function go(
	MarkdownPage $markdown,
	HTMLDocument $document,
):void {
	$markdown->loadDocument($document);
}
