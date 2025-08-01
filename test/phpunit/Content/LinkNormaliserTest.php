<?php
namespace GT\Website\Test\Content;

use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use Gt\Http\Uri;
use GT\Website\Content\LinkNormaliser;
use PHPUnit\Framework\TestCase;

class LinkNormaliserTest extends TestCase {
	public function testNormalise_noLinks():void {
		$document = new HTMLDocument("<article><h1>Hello, PHPUnit!</h1></article>");
		$element = $document->querySelector("article");
		$uri = self::createMock(Uri::class);

		$htmlBefore = $element->innerHTML;
		$sut = new LinkNormaliser($element);
		$sut->normalise($uri);

		$htmlAfter = $element->innerHTML;
		self::assertSame($htmlBefore, $htmlAfter);
	}
}
