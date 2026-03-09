<?php
use Exception;
use Gt\Dom\HTMLDocument;
use GT\Website\ExampleClass;

function go(HTMLDocument $document, ExampleClass $exampleClass):void {
	$document->querySelector("main h1")->textContent = "Updated from go";

	$exampleClass->doSomethingBad();
}
