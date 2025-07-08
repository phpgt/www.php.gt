<?php
use Gt\Dom\HTMLDocument;
use Gt\Http\Uri;
use Gt\Website\Content\MarkdownContent;

function go(
	MarkdownContent $markdown,
	HTMLDocument $document,
):void {
	$markdown->loadDocument($document);
}
