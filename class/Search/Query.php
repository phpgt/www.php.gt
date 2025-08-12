<?php
namespace GT\Website\Search;

use ArrayIterator;
use GT\Website\Content\Markdown;

class Query extends ArrayIterator {
	public function __construct(
		string $query,
		string $contentDir = "data/content",
		string $indexFile = "index.dat",
	) {
		$matches = [];

		$indexPath = "$contentDir/$indexFile";
		$index = unserialize(file_get_contents($indexPath));

		foreach(explode(" ", $query) as $word) {
			$metaphone = metaphone($word);

			if(!isset($index[$metaphone])) {
				continue;
			}

			foreach($index[$metaphone] as $path => $score) {
				if(!isset($matches[$path])) {
					$matches[$path] = 0;
				}

				$matches[$path] += $score;
			}
		}

		arsort($matches);
		$searchHitList = [];

		foreach(array_keys($matches) as $path) {
			if(str_contains($path, "_Sidebar")) {
				continue;
			}

			if(str_starts_with($path, "$contentDir/")) {
				$path = substr($path, strlen("$contentDir/"));
			}

			[$repo, $pagePath] = explode("/", $path, 2);
			$hashPart = "";
			if(str_contains($pagePath, "#")) {
				[$pagePath, $hashPart] = explode("#", $pagePath, 2);
				$hashPart = "#" . $hashPart;
			}

			$pathInfo = pathinfo($pagePath);
			$pagePath = $pathInfo["filename"] . "/" . $hashPart;
			$url = "/docs/$repo/$pagePath";

			if(!isset($searchHitList[$repo])) {
				$searchHitList[$repo] = [];
			}

			$title = strtok($pagePath, "#");
			$title = trim($title, "/");
			$title = str_replace(["-"], [" "], $title);
			if($title === "Home") {
				$title = "$repo docs homepage";
			}
			$markdown = new Markdown("$contentDir/$path");

			array_push(
				$searchHitList[$repo], [
					"title" => $title,
					"url" => $url,
					"preview" => $markdown->getHtmlPreview(),
				]
			);
		}

		parent::__construct($searchHitList);
	}
}
