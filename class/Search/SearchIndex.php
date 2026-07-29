<?php
namespace GT\Website\Search;

use Gt\Dom\HTMLDocument;
use GT\Website\Content\MarkdownFile;

class SearchIndex {
	public const int VERSION = 2;
	const array SCORE_SELECTOR_MAP = [
		"h1" => 10,
		"h2,h3,h4,h5,h6" => 5,
		"p" => 1,
	];

	const int SCORE_H23456 = 50;
	const int SCORE_WORD = 1;
	const array SKIP_SCORING_WORD_LIST = ["and", "the", "is", "are", "was", "were", "be", "been", "being", "have", "has", "had", "do", "does", "did", "will", "would", "shall", "should", "may", "might", "must", "can", "could", "of", "to", "in", "for", "on", "with", "by", "at", "from", "up", "about", "into", "over", "after", "a", "an", "this", "that", "these", "those", "it", "its", "we", "you", "they", "i", "he", "she"];

	/** @var array<string> */
	private array $repoDirList;
	/** @var array<string, array<string>> */
	private array $index;
	private string $indexFilePath;

	public function __construct(
		string $repoDirPath,
		string $indexFile = "index.dat",
	) {
		$this->repoDirList = glob("$repoDirPath/*");
		$this->indexFilePath = "$repoDirPath/$indexFile";
		if(is_file($this->indexFilePath)) {
			$this->index = unserialize(file_get_contents($this->indexFilePath));
		}
		else {
			$this->index = [];
		}
	}

	public function generate():void {
		$index = [
			"version" => self::VERSION,
			"terms" => [],
			"metaphones" => [],
			"titles" => [],
		];

		foreach($this->repoDirList as $repoDir) {
			foreach(glob("$repoDir/*.md") as $markdownFilePath) {
				$markdownIndex = $this->generateScores(
					$markdownFilePath,
				);
				foreach(["terms", "metaphones", "titles"] as $section) {
					$index[$section] = array_merge_recursive(
						$index[$section],
						$markdownIndex[$section],
					);
				}
			}
		}

		ksort($index["terms"]);
		ksort($index["metaphones"]);
		ksort($index["titles"]);
		$this->index = $index;
		file_put_contents($this->indexFilePath, serialize($this->index));
	}

	private function generateScores(string $markdownFilePath):array {
		$markdown = new MarkdownFile($markdownFilePath);
		$html = $markdown->getHTML();
		$document = new HTMLDocument($html);

		$scores = [
			"terms" => [],
			"metaphones" => [],
			"titles" => [
				$markdownFilePath => $this->normaliseText(
					pathinfo($markdownFilePath, PATHINFO_FILENAME),
				),
			],
		];

		foreach(self::SCORE_SELECTOR_MAP as $selector => $scoreIncrement) {
			foreach($document->querySelectorAll($selector) as $element) {
				$headingElement = null;
				$anchorId = null;
				if(str_starts_with($element->tagName, "h")) {
					$headingElement = $element;
				}
				else {
					$currentElement = $element;
					$count = 0;
					while($currentElement && $count < 100) {
						$currentElement = $currentElement->previousElementSibling;
						if($currentElement
							&& str_starts_with($currentElement->tagName, "h")) {
							$headingElement = $currentElement;
							break;
						}
						$count++;
					}
				}
				if($anchor = $headingElement?->querySelector(".heading-permalink")) {
					$anchorId = $anchor->id;
				}

				$markdownId = $markdownFilePath;
				if($anchorId) {
					$markdownId .= "#$anchorId";
				}

				if(str_starts_with($element->tagName, "h")) {
					$scores["titles"][$markdownId] = $this->normaliseText(
						$element->textContent,
					);
				}

				foreach($this->extractTerms($element->textContent) as $term) {
					$this->incrementScore(
						$scores["terms"],
						$term,
						$markdownId,
						$scoreIncrement,
					);
					$this->incrementScore(
						$scores["metaphones"],
						metaphone($term),
						$markdownId,
						$scoreIncrement,
					);
				}
			}
		}

		return $scores;
	}

	private function extractTerms(string $text):array {
		$termList = [];
		preg_match_all("/[\\p{L}\\p{N}]+/u", mb_strtolower($text), $matches);
		foreach($matches[0] as $word) {
			if(in_array($word, self::SKIP_SCORING_WORD_LIST, true)) {
				continue;
			}

			if(!metaphone($word)) {
				continue;
			}

			array_push($termList, $word);
		}

		return $termList;
	}

	private function normaliseText(string $text):string {
		preg_match_all("/[\\p{L}\\p{N}]+/u", mb_strtolower($text), $matches);
		return implode(" ", $matches[0]);
	}

	private function incrementScore(
		array &$index,
		string $term,
		string $markdownId,
		int $increment,
	):void {
		$index[$term] ??= [];
		$index[$term][$markdownId] ??= 0;
		$index[$term][$markdownId] += $increment;
	}

}
