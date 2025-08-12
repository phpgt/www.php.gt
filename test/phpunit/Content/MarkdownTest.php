<?php
namespace GT\Website\Test\Content;

use GT\Website\Content\Markdown;
use PHPUnit\Framework\TestCase;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class MarkdownTest extends TestCase {
	public function testFixWikiLinks():void {
		$tmpPath = tempnam(sys_get_temp_dir(), "markdown-test");
		file_put_contents($tmpPath, "This is a [[Wiki Link]] in the content");

		$sut = new Markdown($tmpPath);
		$html = $sut->getHtml();

		unlink($tmpPath);
		self::assertStringContainsString('<a href="/Wiki-Link">Wiki Link</a>', $html);
	}
}
