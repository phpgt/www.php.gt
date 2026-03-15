<?php
use Gt\Dom\HTMLDocument;

function go(HTMLDocument $document):void {
	$document->querySelector("main h1")->textContent = "Updated from go";

	$exampleClass->doSomethingBad();
}
