<?php
namespace GT\Website\Test\Search;

use GT\Website\Search\Query;
use GT\Website\Search\SearchIndex;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase {
	private string $contentDir;

	protected function setUp():void {
		$this->contentDir = sys_get_temp_dir() . "/" . uniqid("search-test-", true);
		mkdir("$this->contentDir/WebEngine", recursive: true);

		file_put_contents(
			"$this->contentDir/WebEngine/gt-command-overview.md",
			"A guide to every command.",
		);
		file_put_contents(
			"$this->contentDir/WebEngine/tutorials.md",
			"# Tutorials\n\nChoose the right tutorial for your project.",
		);
		file_put_contents(
			"$this->contentDir/WebEngine/other.md",
			"# Other documentation\n\nThe gt command is mentioned here.",
		);

		(new SearchIndex($this->contentDir))->generate();
	}

	protected function tearDown():void {
		foreach(glob("$this->contentDir/WebEngine/*") as $path) {
			unlink($path);
		}
		rmdir("$this->contentDir/WebEngine");
		unlink("$this->contentDir/index.dat");
		rmdir($this->contentDir);
	}

	public function testPartialWordMatches():void {
		$results = new Query("tutor", $this->contentDir);

		self::assertSame("tutorials", $results["WebEngine"][0]["title"]);
	}

	public function testTitlePhraseRanksAboveBodyMention():void {
		$results = new Query("gt command", $this->contentDir);

		self::assertSame(
			"gt command overview",
			$results["WebEngine"][0]["title"],
		);
	}

	public function testExactMatchRanksAbovePrefixMatch():void {
		$results = new Query("tutorial", $this->contentDir);

		self::assertSame("tutorials", $results["WebEngine"][0]["title"]);
	}
}
