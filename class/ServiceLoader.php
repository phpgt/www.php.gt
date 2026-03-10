<?php
namespace GT\Website;

use GT\WebEngine\Service\DefaultServiceLoader;
use GT\Website\Content\MarkdownPage;

class ServiceLoader extends DefaultServiceLoader {
	public function loadMarkdownPage():MarkdownPage {
		return new MarkdownPage("data/content");
	}
}
