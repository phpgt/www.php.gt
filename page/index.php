<?php
use Gt\Dom\Text;
use Gt\Dom\HTMLDocument;

function go(HTMLDocument $document):void {
// The first h1 on the homepage is designed in a way that needs its words
// splitting and wrapping in spans, so they can be easily styled in CSS.
	$h1 = $document->querySelector("article h1");
	$fragment = $document->createDocumentFragment();

	foreach($h1->childNodes as $childNode) {
		if($childNode instanceof Text) {
			foreach(explode(" ", $childNode->wholeText) as $word) {
				$span = $document->createElement("span");
				$span->innerText = "$word ";
				$fragment->appendChild($span);
			}
			$childNode->remove();
		}
	}

	$h1->appendChild($fragment);
}
