<?php
use GT\Dom\Text;
use GT\Dom\HTMLDocument;
use GT\DomTemplate\Binder;
use GT\Input\Input;
use GT\Website\Egg\FamousName;

function go(Binder $binder, HTMLDocument $document):void {
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
	$exampleSource = $document->createElement("div");
	$exampleSource->setAttribute("class", "layout-content example-code");
	while($exampleContainer->firstChild) {
		$exampleSource->appendChild($exampleContainer->firstChild);
	}
	$exampleContainer->appendChild($exampleSource);
	$exampleContainer->appendChild($codeOutputEl);

	$binder->bindKeyValue("famousName", new FamousName());
}

function do_greet(Input $input, Binder $binder):void {
	$binder->bindData($input);
}
