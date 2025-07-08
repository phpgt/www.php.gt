<?php
namespace GT\Website;

use Gt\WebEngine\Middleware\DefaultServiceLoader;
use Gt\Website\Content\MarkdownPage;

class ServiceLoader extends DefaultServiceLoader {
	public function loadMarkdown():MarkdownPage {
		return new MarkdownPage("data/markdown");
	}
}
