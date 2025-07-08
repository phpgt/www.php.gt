<?php
namespace GT\Website\Content;

use ArrayIterator;
use Gt\Json\JsonKvpObject;
use Gt\Json\JsonPrimitive\JsonArrayPrimitive;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<string> */
class RepoList implements IteratorAggregate {
	private array $repoNameArray;

	public function __construct() {
		$this->repoNameArray = $this->loadRepoList();
	}

	public function getIterator():Traversable {
		return new ArrayIterator($this->repoNameArray);
	}

	/** @return array<string> */
	private function loadRepoList():array {
		$array = [];

		$repoListJsonFile = "data/content/repo-list";

		if(!is_file($repoListJsonFile)) {
			return $array;
		}

		/** @var JsonArrayPrimitive $json */
		$json = unserialize(file_get_contents($repoListJsonFile));
		/** @var JsonKvpObject $repoObject */
		foreach($json->getPrimitiveValue() as $repoObject) {
			array_push($array, $repoObject->getString("name"));
		}

		$array = array_filter($array, fn(string $a) => $a !== "www.php.gt");
		usort($array, function(string $a, string $b):int {
			if($a === "WebEngine") {
				return -1;
			}
			if($b === "WebEngine") {
				return 1;
			}
			return strcmp($a, $b);
		});

		return $array;
	}

}
