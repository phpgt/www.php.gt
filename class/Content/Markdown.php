<?php
namespace GT\Website\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkRenderer;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Util\HtmlFilter;
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
		$config = [
			"html_input" => HtmlFilter::ALLOW,
			"allow_unsafe_links" => false,

			"heading_permalink" => [
				"html_class" => "heading-permalink",
				"id_prefix" => "",
				"apply_id_to_heading" => false,
				"heading_class" => "",
				"fragment_prefix" => "",
				"insert" => "before",
				"min_heading_level" => 1,
				"max_heading_level" => 6,
				"title" => "Permalink",
				"symbol" => HeadingPermalinkRenderer::DEFAULT_SYMBOL,
				"aria_hidden" => true,
			],

			"smartpunct" => [
				"double_quote_opener" => '“',
				"double_quote_closer" => '”',
				"single_quote_opener" => '‘',
				"single_quote_closer" => '’',
			],

			"table_of_contents" => [
				"html_class" => "table-of-contents",
				"position" => "top",
				"style" => "bullet",
				"min_heading_level" => 1,
				"max_heading_level" => 6,
				"normalize" => "relative",
				"placeholder" => null,
			],
		];
		$environment = new Environment($config);
		$environment->addExtension(new CommonMarkCoreExtension());
		$environment->addExtension(new GithubFlavoredMarkdownExtension());
		$environment->addExtension(new HeadingPermalinkExtension());
		$environment->addExtension(new SmartPunctExtension());
//		$environment->addExtension(new TableOfContentsExtension());

		$environment->addRenderer(FencedCode::class, new SyntaxHighlighterRenderer());

		$converter = new MarkdownConverter($environment);

		$markdownContent = file_get_contents($this->filePath);
		$markdownContent = $this->fixRelativeLinks($markdownContent);
		$markdownContent = $this->fixWikiLinks($markdownContent);

		return $converter->convert($markdownContent);
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
				rtrim(str_replace(' ', '-', $matches[1]), '?')
			),
			$markdown
		);
	}
}
