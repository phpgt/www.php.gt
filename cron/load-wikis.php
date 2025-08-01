<?php
use Gt\Fetch\Http;
use Gt\Json\JsonKvpObject;
use Gt\Json\JsonPrimitive\JsonArrayPrimitive;
use GT\Website\Content\MarkdownPage;

##[Cron(CronEveryDayAt("00:00"))]
function go(MarkdownPage $markdownPage):void {
	$repoNameList = [];

	$cache = new Gt\FileCache\Cache($markdownPage->dir);
	$repositoryList = $cache->getInstance(
		"repo-list",
		JsonArrayPrimitive::class, loadRepoList(...)
	);

	/** @var JsonKvpObject $item */
	foreach($repositoryList->getPrimitiveValue() as $item) {
		array_push($repoNameList, $item->getString("name"));
	}
	array_push($repoNameList, ".github");

	$existingDirectoryList = glob("$markdownPage->dir/{*,.*}", GLOB_BRACE);
	$existingDirectoryList = array_map(function($dir) use ($markdownPage) {
		$dir = substr($dir, strlen("$markdownPage->dir/"));
		return trim($dir, "/");
	}, $existingDirectoryList);
	$existingDirectoryList = array_filter($existingDirectoryList, fn(string $directoryName) => $directoryName[0] !== "." || strlen($directoryName) > 2);

	sort($repoNameList);
	sort($existingDirectoryList);

	$originalCwd = getcwd();
	foreach($existingDirectoryList as $existingDirectory) {
		chdir($originalCwd);
		$dirToPull = realpath("data/content/$existingDirectory");

		if(!is_dir($dirToPull)) {
			echo "No directory for $existingDirectory", PHP_EOL;
			continue;
		}
		chdir($dirToPull);
		exec("git stash && git pull", $output, $exitCode);

		if($exitCode === 0) {
			echo "Successfully updated $existingDirectory", PHP_EOL;
		}
		else {
			echo "Error updating $existingDirectory", PHP_EOL;
		}
	}
	chdir($originalCwd);

	$missingDirectoryList = array_diff($repoNameList, $existingDirectoryList);

	chdir($markdownPage->dir);
	foreach($missingDirectoryList as $dirToClone) {
		$repoName = "$dirToClone.wiki.git";
// The homepage content is stored in a special repo called ".github"
		if($dirToClone[0] === ".") {
			$repoName = "$dirToClone.git";
		}

		$cmd = "GIT_ASKPASS=/bin/true git clone https://github.com/phpgt/$repoName $dirToClone 2>&1";
		exec($cmd, $output, $exitCode);

		if($exitCode === 0) {
			echo "Successfully cloned $dirToClone", PHP_EOL;
		}
		else {
			echo "No wiki for $dirToClone", PHP_EOL;
		}
	}
}

function loadRepoList():JsonArrayPrimitive {
	$http = new Http();
	$response = $http->awaitFetch("https://api.github.com/orgs/phpgt/repos");
	/** @var JsonArrayPrimitive $json */
	$json = $response->awaitJson();
	return $json;
}

// TODO: Automate when cron is implemented.
chdir(__DIR__ . "/..");
require "vendor/autoload.php";
go(new MarkdownPage("data/content"));
