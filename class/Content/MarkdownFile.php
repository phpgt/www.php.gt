<?php
namespace GT\Website\Content;

use Gt\Dom\HTMLDocument;
use Stringable;

readonly class MarkdownFile implements Stringable {
	public string $filePath;
	public ?string $hash;

	public function __construct(
		string $filePath,
		public string $baseLink = "",
	) {
		if(str_contains($filePath, "#")) {
			[$filePath, $this->hash] = explode(
				"#",
				$filePath,
				2,
			);
		}
		else {
			$this->hash = null;
		}

		$this->filePath = $filePath;
	}

	public function __toString():string {
		return $this->getHtml();
	}

	public function getHtml():string {
		if(!file_exists($this->filePath)) {
			return "";
		}

		$md5 = md5_file($this->filePath);
		$cacheFile = "cache/markdown/$md5.html";

		$markdownContent = file_get_contents($this->filePath);
		$markdownContent = $this->fixRelativeLinks($markdownContent);
		$markdownContent = $this->fixWikiLinks($markdownContent);

		$content = new MarkdownToHTML($markdownContent, $cacheFile);
		$html = $content->outputHTML();
		if($this->hash) {
			$document = new HTMLDocument($html);

			foreach($document->querySelectorAll("a") as $link) {
				if(!$link->classList->contains("heading-permalink")) {
					continue;
				}

				if($this->hash !== trim($link->href, "#")) {
					continue;
				}

				return "<p>" . $link->parentElement->nextElementSibling->innerHTML . "</p>";
			}
		}

		return $html;
	}

	public function getHtmlPreview():string {
		if($this->hash) {
			return $this->getHtml();
		}

		$html = $this->getHtml();
		$document = new HTMLDocument($html);
		return $document->querySelector("p")->innerHTML;
	}

	private function fixRelativeLinks(string $markdown):string {
		return preg_replace_callback(
			'/\[([^\]]+)\]\((?!http|#)([^)]+)\)/',
			fn(array $matches):string => sprintf(
				"[%s](%s/%s)",
				$matches[1],
				rtrim($this->baseLink, "/"),
				ltrim($matches[2], "/")
			),
			$markdown
		);
	}

	private function fixWikiLinks(string $markdown):string {

		return preg_replace_callback(
			'/\[\[([^\]]+)\]\]/',
			fn(array $matches):string => sprintf(
				"[%1\$s]($this->baseLink/%2\$s)",
				$matches[1],
				str_replace(['?', ' '], ['%3F', '-'], $matches[1]),
			),
			$markdown
		);
	}
}
