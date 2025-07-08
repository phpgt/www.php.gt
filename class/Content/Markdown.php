<?php
namespace GT\Website\Content;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Stringable;

readonly class Markdown implements Stringable {
	public function __construct(
		public string $filePath,
		public string $baseLink = "",
	) {
	}

	public function __toString():string {
		return $this->getHtml();
	}

	public function getHtml():string {
		$converter = new GithubFlavoredMarkdownConverter([
			"html_input" => "strip",
			"allow_unsafe_links" => false,
		]);

		$markdownContent = file_get_contents($this->filePath);
		$markdownContent = $this->fixWikiLinks($markdownContent);

		return $converter->convert($markdownContent);
	}

	private function fixWikiLinks(string $markdown):string {
		return preg_replace_callback(
			'/\[\[([^\]]+)\]\]/',
			fn(array $matches):string => sprintf(
				"[%1\$s]($this->baseLink/%2\$s)",
				$matches[1],
				str_replace(' ', '-', $matches[1])
			),
			$markdown
		);
	}
}
