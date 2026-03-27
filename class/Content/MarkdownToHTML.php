<?php
namespace GT\Website\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkRenderer;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Util\HtmlFilter;
use Stringable;

class MarkdownToHTML implements Stringable {
	public function __construct(
		private string $markdown,
		private ?string $cacheFile = null,
	) {}

	public function __toString():string {
		return $this->outputHTML();
	}

	public function outputHTML():string {
		if($this->cacheFile && file_exists($this->cacheFile)) {
			return file_get_contents($this->cacheFile);
		}

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
		$environment->addRenderer(FencedCode::class, new SyntaxHighlighterRenderer());

		$converter = new MarkdownConverter($environment);
		$html = (string)$converter->convert($this->markdown);

		if($this->cacheFile) {
			if(!is_dir(dirname($this->cacheFile))) {
				mkdir(dirname($this->cacheFile), recursive: true);
			}

			file_put_contents($this->cacheFile, $html);
		}

		return $html;
	}
}
