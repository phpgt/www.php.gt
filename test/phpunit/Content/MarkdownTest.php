<?php
namespace GT\Website\Test\Content;

use GT\Website\Content\MarkdownFile;
use GT\Website\Content\MarkdownPage;
use Gt\Dom\HTMLDocument;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function uniqid;

class MarkdownTest extends TestCase {
	public function testFixWikiLinks():void {
		$tmpPath = tempnam(sys_get_temp_dir(), "markdown-test");
		file_put_contents($tmpPath, "This is a [[Wiki Link]] in the content");

		$sut = new MarkdownFile($tmpPath);
		$html = $sut->getHTML();

		unlink($tmpPath);
		self::assertStringContainsString('<a href="/Wiki-Link">Wiki Link</a>', $html);
	}

	public function testMarkdownPageLoadsContentCaseInsensitively():void {
		$contentDir = sys_get_temp_dir() . "/" . uniqid("markdown-page-", true);
		mkdir("$contentDir/WebEngine", recursive: true);
		file_put_contents(
			"$contentDir/WebEngine/Getting-started.md",
			"# Case matched content"
		);

		$document = new HTMLDocument(
			'<article><div data-content="webengine/getting-STARTED"></div></article>'
		);

		$sut = new MarkdownPage($contentDir);
		$sut->loadDocument($document);

		$html = $document->querySelector("article div")->innerHTML;
		$this->removeDirectory($contentDir);
		self::assertStringContainsString("Case matched content", $html);
	}

	private function removeDirectory(string $dir):void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$dir,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach($iterator as $file) {
			/** @var SplFileInfo $file */
			$file->isDir()
				? rmdir($file->getPathname())
				: unlink($file->getPathname());
		}

		rmdir($dir);
	}
}
