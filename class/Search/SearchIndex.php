<?php
namespace GT\Website\Search;

use Gt\Dom\HTMLDocument;
use GT\Website\Content\Markdown;

class SearchIndex {
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
		$index = [];

		foreach($this->repoDirList as $repoDir) {
			foreach(glob("$repoDir/*.md") as $markdownFilePath) {
				$markdownIndex = $this->generateScores(
					$markdownFilePath,
				);
				$index = array_merge_recursive($index, $markdownIndex);
			}
		}

		ksort($index);
		$this->index = $index;
		file_put_contents($this->indexFilePath, serialize($this->index));
	}

	private function generateScores(string $markdownFilePath):array {
		$markdown = new Markdown($markdownFilePath);
		$html = $markdown->getHtml();
		$document = new HTMLDocument($html);

		$scores = [];

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
						if(str_starts_with($currentElement?->tagName, "h")) {
							$headingElement = $currentElement;
							break;
						}
						$count++;
					}
				}
				if($anchor = $headingElement?->querySelector(".heading-permalink")) {
					$anchorId = $anchor->id;
				}

				foreach($this->extractMetaphones($element->textContent) as $metaphone) {
					$markdownId = $markdownFilePath;
					if($anchorId) {
						$markdownId .= "#$anchorId";
					}

					if(!isset($scores[$metaphone])) {
						$scores[$metaphone] = [];
					}
					if(!isset($scores[$metaphone][$markdownId])) {
						$scores[$metaphone][$markdownId] = 0;
					}

					$scores[$metaphone][$markdownId] += $scoreIncrement;
				}
			}
		}

		return $scores;
	}

	private function extractMetaphones(string $text):array {
		$metaphoneList = [];
		foreach(explode(" ", $text) as $word) {
			if(in_array($word, self::SKIP_SCORING_WORD_LIST)) {
				continue;
			}

			$metaphone = metaphone($word);
			if(!$metaphone) {
				continue;
			}

			array_push($metaphoneList, $metaphone);
		}

		return $metaphoneList;
	}

}
