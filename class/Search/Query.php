<?php
namespace GT\Website\Search;

use ArrayIterator;
use Gt\Http\Uri;
use GT\Website\Content\MarkdownFile;

class Query extends ArrayIterator {
	private const int SCORE_EXACT_TERM = 20;
	private const int SCORE_PREFIX_TERM = 8;
	private const int SCORE_METAPHONE = 2;
	private const int SCORE_TITLE_TERM = 1_000;
	private const int SCORE_TITLE_PREFIX = 500;
	private const int SCORE_TITLE_PHRASE = 5_000;
	private const int SCORE_TITLE_EXACT = 10_000;
	private const int SCORE_ALL_TERMS = 2_000;

	public function __construct(
		string $query,
		string $contentDir = "data/content",
		string $indexFile = "index.dat",
	) {
		$matches = [];
		$matchedTerms = [];

		$indexPath = "$contentDir/$indexFile";
		$index = unserialize(file_get_contents($indexPath));
		if(($index["version"] ?? null) !== SearchIndex::VERSION) {
			throw new \RuntimeException(
				"Search index is out of date; run gt cron --now build-search-index.",
			);
		}

		$queryTerms = $this->extractTerms($query);
		$normalisedQuery = implode(" ", $queryTerms);

		foreach($queryTerms as $queryTerm) {
			$this->addScores(
				$matches,
				$matchedTerms,
				$index["terms"][$queryTerm] ?? [],
				$queryTerm,
				self::SCORE_EXACT_TERM,
			);

			if(mb_strlen($queryTerm) >= 3) {
				foreach($index["terms"] as $term => $scores) {
					if($term === $queryTerm || !str_starts_with($term, $queryTerm)) {
						continue;
					}
					$this->addScores(
						$matches,
						$matchedTerms,
						$scores,
						$queryTerm,
						self::SCORE_PREFIX_TERM,
					);
				}
			}

			$this->addScores(
				$matches,
				$matchedTerms,
				$index["metaphones"][metaphone($queryTerm)] ?? [],
				$queryTerm,
				self::SCORE_METAPHONE,
			);
		}

		foreach($matches as $path => &$score) {
			if(count($matchedTerms[$path]) === count($queryTerms)) {
				$score += self::SCORE_ALL_TERMS;
			}

			$title = $index["titles"][$path] ?? "";
			if($title === $normalisedQuery) {
				$score += self::SCORE_TITLE_EXACT;
			}
			elseif(str_contains($title, $normalisedQuery)) {
				$score += self::SCORE_TITLE_PHRASE;
			}

			foreach($queryTerms as $queryTerm) {
				foreach(explode(" ", $title) as $titleTerm) {
					if($titleTerm === $queryTerm) {
						$score += self::SCORE_TITLE_TERM;
					}
					elseif(mb_strlen($queryTerm) >= 3
						&& str_starts_with($titleTerm, $queryTerm)) {
						$score += self::SCORE_TITLE_PREFIX;
					}
				}
			}
		}
		unset($score);

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
			$uri = new Uri("/docs/$repo/$pagePath");
			$uri = $uri->withQueryValue("query", $query);

			if(!isset($searchHitList[$repo])) {
				$searchHitList[$repo] = [];
			}

			$title = strtok($pagePath, "#");
			$title = trim($title, "/");
			$title = str_replace(["-"], [" "], $title);
			if($title === "Home") {
				$title = "$repo docs homepage";
			}
			$title = strip_tags($title);
			$markdown = new MarkdownFile("$contentDir/$path");

			array_push(
				$searchHitList[$repo], [
					"title" => $title,
					"url" => $uri,
					"preview" => $markdown->getHtmlPreview(),
				]
			);
		}

		parent::__construct($searchHitList);
	}

	private function extractTerms(string $text):array {
		preg_match_all("/[\\p{L}\\p{N}]+/u", mb_strtolower($text), $matches);
		return array_values(array_unique($matches[0]));
	}

	private function addScores(
		array &$matches,
		array &$matchedTerms,
		array $scores,
		string $queryTerm,
		int $multiplier,
	):void {
		foreach($scores as $path => $score) {
			$matches[$path] ??= 0;
			$matches[$path] += $score * $multiplier;
			$matchedTerms[$path][$queryTerm] = true;
		}
	}
}
