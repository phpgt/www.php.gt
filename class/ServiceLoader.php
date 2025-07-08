<?php
namespace GT\Website;

use Gt\WebEngine\Middleware\DefaultServiceLoader;
use Gt\Website\Content\MarkdownContent;

class ServiceLoader extends DefaultServiceLoader {
	public function loadMarkdown():MarkdownContent {
		return new MarkdownContent("data/markdown");
	}
}
