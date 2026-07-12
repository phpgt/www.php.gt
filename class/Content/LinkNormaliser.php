<?php
namespace GT\Website\Content;

use Gt\Dom\Element;
use GT\Dom\NodeList;
use Gt\Http\Uri;

class LinkNormaliser {
	const string CLASS_EXTERNAL_LINK = "link-external";
	const CLASS_DOCS_LINK = "link-docs";

	public function __construct(
		private readonly ?Element $article
	) {}

	public function normalise(Uri $currentUri):void {
		if(!$this->article) {
			return;
		}

		$linkList = $this->article->querySelectorAll("a[href]");
		if($linkList->length === 0) {
			return;
		}

		$host = $currentUri->getHost();
		/** @var Element $link */
		foreach($linkList as $link) {
			$linkUri = new Uri($link->href);
			$linkHost = $linkUri->getHost();
			$linkPath = $linkUri->getPath();
			if($linkHost && $linkHost !== $host) {
				if($linkHost === "www.php.gt") {
					$link->classList->add(self::CLASS_DOCS_LINK);
					$link->href = $linkPath;
				}
				else {
					$link->classList->add(self::CLASS_EXTERNAL_LINK);
					$link->setAttribute("target", "_blank");
					$link->setAttribute("rel", "noopener");
				}
			}
		}

		$this->normaliseCrossSite($host, $linkList);
		$this->normaliseAbsolute($linkList, $currentUri);
		$this->addFlux($linkList);
	}

	private function normaliseCrossSite(
		string $host,
		NodeList $linkList,
	):void {}

	private function normaliseAbsolute(
		NodeList $linkList,
		Uri $currentUri,
	):void {
		$path = $currentUri->getPath();
		$pathDirName = pathinfo($path, PATHINFO_DIRNAME);
		foreach($linkList as $link) {
			if($link->href[0] !== "/") {
				continue;
			}

			$link->href = $pathDirName . $link->href;
		}
	}

	private function addFlux(
		NodeList $linkList
	):void {
		foreach($linkList as $link) {
			if(str_contains($link->href, "//")) {
				continue;
			}

			$link->dataset->set("flux", "link");
		}
	}
}
