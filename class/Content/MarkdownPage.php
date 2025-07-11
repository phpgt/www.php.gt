<?php
namespace GT\Website\Content;

use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;

readonly class MarkdownPage {
	public function __construct(public string $dir) {}

	public function loadDocument(HTMLDocument $document):void {
		$document->querySelectorAll("[data-content]")->forEach(
			$this->loadElement(...)
		);
	}

	public function loadElement(Element $element):void {
		$contentName = $element->dataset->get("content");
		$repo = ".github/profile";
		$file = $contentName;

		if(str_contains($contentName, "/")) {
			[$repo, $file] = explode("/", $contentName);
		}

		$markdownFilePath = "$this->dir/$repo/$file.md";
		if(!is_file($markdownFilePath)) {
			$element->dataset->set("content-error", "not-found");
			return;
		}

		$markdown = new Markdown($markdownFilePath);
		$element->innerHTML = $markdown->getHtml();
	}
}
