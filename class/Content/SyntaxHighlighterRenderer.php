<?php
namespace GT\Website\Content;

use Highlight\Highlighter;
use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class SyntaxHighlighterRenderer implements NodeRendererInterface {
	private Highlighter $highlighter;

	public function __construct() {
		$this->highlighter = new Highlighter();
	}

	public function render(
		Node $node,
		ChildNodeRendererInterface $childRenderer,
	):string {
		if (!($node instanceof FencedCode)) {
			throw new InvalidArgumentException('Node must be FencedCode');
		}

		$language = $node->getInfo() ?? 'plaintext';
		$code = $node->getLiteral();

		try {
			$highlighted = $this->highlighter->highlight($language, $code);
			$html = "<pre><code class=\"hljs {$highlighted->language}\">{$highlighted->value}</code></pre>";
		} catch (\Throwable $e) {
			$html = "<pre><code>" . htmlspecialchars($code) . "</code></pre>";
		}

		return $html;
	}
}
