<?php
use Gt\Dom\Text;
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\Binder;
use GT\Input\Input;

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

// The example code is stored outside of the page content, but it should be
// displayed inline with the code example.
	$codeOutputEl = $document->getElementById("example-output");
	$exampleContainer = $document->querySelector("main article .example");
	$exampleContainer->appendChild($codeOutputEl);
}

function do_greet(Input $input, Binder $binder):void {
	$binder->bindData($input);
}
