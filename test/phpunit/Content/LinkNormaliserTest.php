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

	public function testNormalise_hasExternalLink():void {
		$document = new HTMLDocument("<article><h1>Hello, <a href='https://www.phpunit.de'>PHPUnit</a>!</h1></article>");
		$element = $document->querySelector("article");
		$uri = self::createMock(Uri::class);
		$uri->method("getHost")->willReturn("localhost");

		$htmlBefore = $element->innerHTML;
		$sut = new LinkNormaliser($element);
		$sut->normalise($uri);

		$htmlAfter = $element->innerHTML;
		self::assertNotSame($htmlBefore, $htmlAfter);
		$link = $document->querySelector("article a");
		self::assertTrue($link->classList->contains(LinkNormaliser::CLASS_EXTERNAL_LINK));
		self::assertSame("_blank", $link->getAttribute("target"));
		self::assertSame("noopener", $link->getAttribute("rel"));
	}

	public function testNormalise_hasDocsLink():void {
		$document = new HTMLDocument("<article><h1>Hello, <a href='https://www.php.gt/docs/webengine/getting-started/'>PHP.GT</a>!</h1></article>");
		$element = $document->querySelector("article");
		$uri = self::createMock(Uri::class);
		$uri->method("getHost")->willReturn("localhost");

		$htmlBefore = $element->innerHTML;
		$sut = new LinkNormaliser($element);
		$sut->normalise($uri);

		$htmlAfter = $element->innerHTML;
		self::assertNotSame($htmlBefore, $htmlAfter);
		$link = $document->querySelector("article a");
		self::assertTrue($link->classList->contains(LinkNormaliser::CLASS_DOCS_LINK));
		self::assertFalse($link->hasAttribute("target"));
		self::assertFalse($link->hasAttribute("rel"));
	}

	public function testNormalise_docsLinkAbsolute():void {
		$document = new HTMLDocument("<article><h1>Hello, <a href='https://www.php.gt/docs/webengine/getting-started/'>PHP.GT</a>!</h1></article>");
		$element = $document->querySelector("article");
		$uri = self::createMock(Uri::class);
		$uri->method("getHost")->willReturn("localhost");

		$sut = new LinkNormaliser($element);
		$sut->normalise($uri);

		$link = $document->querySelector("article a");
		self::assertSame("/docs/webengine/getting-started/", $link->href);
		self::assertFalse($link->hasAttribute("target"));
		self::assertFalse($link->hasAttribute("rel"));
	}

	public function testNormalise_docsLinkNoHost():void {
		$document = new HTMLDocument("<article><h1>Hello, <a href='/docs/webengine/getting-started/'>PHP.GT</a>!</h1></article>");
		$element = $document->querySelector("article");
		$uri = self::createMock(Uri::class);
		$uri->method("getHost")->willReturn("example.com");

		$sut = new LinkNormaliser($element);
		$sut->normalise($uri);

		$link = $document->querySelector("article a");
		self::assertSame("/docs/webengine/getting-started/", $link->href);
		self::assertFalse($link->classList->contains(LinkNormaliser::CLASS_EXTERNAL_LINK));
		self::assertFalse($link->hasAttribute("target"));
		self::assertFalse($link->hasAttribute("rel"));
	}
}
