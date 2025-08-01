<?php
namespace GT\Website\Content;

use Gt\Dom\Element;
use Gt\Http\Uri;

class LinkNormaliser {
	const string CLASS_EXTERNAL_LINK = "link-external";
	const CLASS_DOCS_LINK = "link-docs";

	public function __construct(
		private readonly Element $article
	) {}

	public function normalise(Uri $currentUri):void {
		$linkList = $this->article->querySelectorAll("a[href]");
		if($linkList->length === 0) {
			return;
		}

		$host = $currentUri->getHost();
		foreach($linkList as $link) {
			$linkUri = new Uri($link->href);
			$linkHost = $linkUri->getHost();
			$linkPath = $linkUri->getPath();
			if($linkHost !== $host) {
				if($linkHost === "www.php.gt" && str_starts_with($linkPath, "/docs/")) {
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
	}
}
