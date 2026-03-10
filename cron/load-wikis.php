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
	$existingDirectoryList = array_filter(
		$existingDirectoryList,
		fn(string $directoryName):bool => is_dir("$markdownPage->dir/$directoryName")
	);

	sort($repoNameList);
	sort($existingDirectoryList);

	$fullMarkdown = "";

	$originalCwd = getcwd();
	$contentRoot = realpath($markdownPage->dir);
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

		$markdownHomeFile = "$contentRoot/$existingDirectory/Home.md";
		if(file_exists($markdownHomeFile)) {
			$fullMarkdown .= "\n\n---\n\n# Documentation: $existingDirectory\n\n";
			$pageList = [$markdownHomeFile];

			$sidebarFile = "$contentRoot/$existingDirectory/_Sidebar.md";
			if(file_exists($sidebarFile)) {
				$pageList = array_merge(
					$pageList,
					getPageListFromSidebar($sidebarFile, dirname($markdownHomeFile))
				);
			}

			$seenPageList = [];
			foreach($pageList as $markdownFile) {
				$realPath = realpath($markdownFile);
				if(!$realPath || isset($seenPageList[$realPath])) {
					continue;
				}

				$seenPageList[$realPath] = true;
				$fullMarkdown .= file_get_contents($realPath);
				$fullMarkdown .= "\n\n";
			}
		}
	}

	$missingDirectoryList = array_diff($repoNameList, $existingDirectoryList);

	chdir($contentRoot);
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

	chdir($originalCwd);
	file_put_contents("asset/_www/llms-full.txt", trim($fullMarkdown) . PHP_EOL);
}

function loadRepoList():JsonArrayPrimitive {
	$http = new Http();
	$response = $http->awaitFetch("https://api.github.com/orgs/phpgt/repos");
	/** @var JsonArrayPrimitive $json */
	$json = $response->awaitJson();
	return $json;
}

function getPageListFromSidebar(string $sidebarFile, string $documentationDir):array {
	$pageList = [];
	$seenPageList = [];
	$availablePageMap = getAvailableMarkdownPageMap($documentationDir);

	foreach(file($sidebarFile, FILE_IGNORE_NEW_LINES) as $line) {
		$pageReference = getSidebarPageReference($line);
		if(!$pageReference) {
			continue;
		}

		$pageFile = resolveMarkdownPagePath($pageReference, $documentationDir, $availablePageMap);
		if(!$pageFile) {
			continue;
		}

		$realPath = realpath($pageFile);
		if(!$realPath || isset($seenPageList[$realPath])) {
			continue;
		}

		$seenPageList[$realPath] = true;
		$pageList[] = $pageFile;
	}

	return $pageList;
}

function getAvailableMarkdownPageMap(string $documentationDir):array {
	$pageMap = [];

	foreach(glob("$documentationDir/*.md") as $markdownFile) {
		$filename = basename($markdownFile);
		if($filename[0] === "_") {
			continue;
		}

		$pageMap[normalizeMarkdownReference(pathinfo($filename, PATHINFO_FILENAME))] = $markdownFile;
	}

	return $pageMap;
}

function getSidebarPageReference(string $line):?string {
	if(preg_match('/\[\[([^\]]+)\]\]/', $line, $matches)) {
		return trim(explode("|", $matches[1], 2)[0]);
	}

	if(preg_match('/\[[^\]]+\]\(([^)#]+)(?:#[^)]+)?\)/', $line, $matches)) {
		return trim($matches[1]);
	}

	return null;
}

function resolveMarkdownPagePath(
	string $pageReference,
	string $documentationDir,
	array $availablePageMap,
):?string {
	$pageReference = explode("#", $pageReference, 2)[0];
	$pageReference = trim($pageReference);
	if($pageReference === "") {
		return null;
	}

	$directPath = "$documentationDir/" . ltrim($pageReference, "/");
	if(pathinfo($directPath, PATHINFO_EXTENSION) !== "md") {
		$directPath .= ".md";
	}

	if(file_exists($directPath)) {
		return $directPath;
	}

	$normalizedReference = normalizeMarkdownReference($pageReference);
	return $availablePageMap[$normalizedReference] ?? null;
}

function normalizeMarkdownReference(string $reference):string {
	$reference = rawurldecode($reference);
	$reference = pathinfo($reference, PATHINFO_FILENAME);
	$reference = str_replace(
		[" ", "_", "?", "'", "`", "’", "‐", "–", "—"],
		["-", "-", "", "", "", "", "-", "-", "-"],
		$reference
	);
	$reference = preg_replace('/[^a-z0-9&.-]+/i', "-", $reference);
	$reference = preg_replace('/-+/', "-", $reference);
	return strtolower(trim($reference, "-."));
}

// TODO: Automate when cron is implemented.
chdir(__DIR__ . "/..");
require "vendor/autoload.php";
go(new MarkdownPage("data/content"));
