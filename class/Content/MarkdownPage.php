<?php
namespace GT\Website\Content;

use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;

class MarkdownPage {
	public function __construct(public readonly string $dir) {}

	public function loadDocument(HTMLDocument $document):void {
		$document->querySelectorAll("[data-content]")->forEach(
			$this->loadElement(...)
		);
	}

	public function loadElement(Element $element):void {
		$contentName = $element->dataset->get("content");
		$repo = "_phpgt";
		$file = $contentName;

		if(str_contains($contentName, "/")) {
			[$repo, $file] = explode("/", $contentName);
		}

		$cacheFilePath = "$this->dir/$repo/$file.md";
		if(!is_file($cacheFilePath)) {
			$element->dataset->set("content-error", "not-found");
			return;
		}
	}
}
