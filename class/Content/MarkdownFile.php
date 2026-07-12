<?php
namespace GT\Website\Content;

use Gt\Dom\HTMLDocument;
use Stringable;

readonly class MarkdownFile implements Stringable {
	private const CACHE_VERSION = 4;

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
		return $this->getHTML();
	}

	public function getHTML():string {
		if(!file_exists($this->filePath)) {
			return "";
		}

		$markdownContent = file_get_contents($this->filePath);
		$md5 = md5(self::CACHE_VERSION . "|" . $markdownContent);
		$cacheFile = "cache/markdown/$md5.html";

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
			return $this->getHTML();
		}

		$html = $this->getHTML();
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
			function(array $matches):string {
				[$label, $target] = $this->parseWikiLink($matches[1]);

				return sprintf(
					"[%s](%s/%s)",
					$label,
					$this->baseLink,
					$this->normalizeWikiLinkTarget($target),
				);
			},
			$markdown
		);
	}

	private function parseWikiLink(string $link):array {
		if(!str_contains($link, "|")) {
			return [$link, $link];
		}

		[$label, $target] = explode("|", $link, 2);
		return [trim($label), trim($target)];
	}

	private function normalizeWikiLinkTarget(string $target):string {
		return str_replace(
			['?', ' '],
			['%3F', '-'],
			$target,
		);
	}
}
