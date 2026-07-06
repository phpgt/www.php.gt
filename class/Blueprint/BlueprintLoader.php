<?php
namespace GT\Website\Blueprint;

use RuntimeException;

class BlueprintLoader {
	public function __construct(
		private readonly string $sourceDir,
		private readonly string $targetDir = "data/blueprint",
	) {}

	public function loadBlueprintList():BlueprintList {
		$this->assertSourceDirExists();
		$this->ensureTargetSymlink();

		$blueprintList = [];
		$sourceRepoList = $this->getSourceRepoList();
		foreach($sourceRepoList as $sourceRepoDir) {
			$name = basename($sourceRepoDir);
			$targetRepoDir = "$this->targetDir/$name";
			$blueprintList[] = $this->createBlueprint($sourceRepoDir, $targetRepoDir);
		}

		usort(
			$blueprintList,
			fn(Blueprint $a, Blueprint $b):int => strcmp($a->name, $b->name)
		);

		return new BlueprintList(...$blueprintList);
	}

	private function assertSourceDirExists():void {
		if(!is_dir($this->sourceDir)) {
			throw new RuntimeException("Blueprint source directory not found: $this->sourceDir");
		}
	}

	private function ensureTargetSymlink():void {
		$targetParentDir = dirname($this->targetDir);
		if(!is_dir($targetParentDir)) {
			mkdir($targetParentDir, recursive: true);
		}

		if(is_link($this->targetDir)) {
			if(readlink($this->targetDir) === $this->sourceDir) {
				return;
			}

			unlink($this->targetDir);
		}
		elseif(file_exists($this->targetDir)) {
			$this->removeGeneratedTargetDir();
		}

		symlink($this->sourceDir, $this->targetDir);
	}

	private function removeGeneratedTargetDir():void {
		if(!is_dir($this->targetDir)) {
			throw new RuntimeException("Blueprint target exists and is not a directory: $this->targetDir");
		}

		foreach(glob("$this->targetDir/*") ?: [] as $targetRepoDir) {
			if(!is_link($targetRepoDir)) {
				throw new RuntimeException("Blueprint target directory contains non-symlink content: $targetRepoDir");
			}

			unlink($targetRepoDir);
		}

		rmdir($this->targetDir);
	}

	/** @return array<string> */
	private function getSourceRepoList():array {
		return array_filter(
			glob("$this->targetDir/*", GLOB_ONLYDIR) ?: [],
			fn(string $dir):bool => is_dir("$dir/.git")
		);
	}

	private function createBlueprint(
		string $sourceRepoDir,
		string $targetRepoDir,
	):Blueprint {
		$name = basename($sourceRepoDir);
		return new Blueprint(
			$name,
			$this->formatBlueprintTitle($name),
			$this->getDescription($sourceRepoDir),
			$targetRepoDir,
			$this->getGithubUrl($sourceRepoDir),
			$this->getCloneUrl($sourceRepoDir),
			$this->getDefaultBranch($sourceRepoDir),
		);
	}

	private function formatBlueprintTitle(string $name):string {
		return ucwords(str_replace(["-", "_"], " ", $name));
	}

	private function getDescription(string $repoDir):string {
		$composerJsonPath = "$repoDir/composer.json";
		if(!is_file($composerJsonPath)) {
			return "";
		}

		$composerJson = json_decode(file_get_contents($composerJsonPath), true);
		return trim($composerJson["description"] ?? "");
	}

	private function getGithubUrl(string $repoDir):string {
		$cloneUrl = $this->getCloneUrl($repoDir);
		if(str_starts_with($cloneUrl, "git@github.com:")) {
			$cloneUrl = "https://github.com/" . substr($cloneUrl, 15);
		}

		if(str_ends_with($cloneUrl, ".git")) {
			return substr($cloneUrl, 0, -4);
		}

		return $cloneUrl;
	}

	private function getCloneUrl(string $repoDir):string {
		return trim(shell_exec("git -C " . escapeshellarg($repoDir) . " remote get-url origin") ?? "");
	}

	private function getDefaultBranch(string $repoDir):string {
		$defaultBranch = trim(shell_exec("git -C " . escapeshellarg($repoDir) . " symbolic-ref refs/remotes/origin/HEAD --short") ?? "");
		if($defaultBranch) {
			return substr($defaultBranch, strlen("origin/"));
		}

		return trim(shell_exec("git -C " . escapeshellarg($repoDir) . " branch --show-current") ?? "");
	}
}
