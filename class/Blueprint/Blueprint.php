<?php
namespace GT\Website\Blueprint;

class Blueprint {
	public function __construct(
		public string $name,
		public string $title,
		public string $description,
		public string $path,
		public string $githubUrl,
		public string $cloneUrl,
		public string $defaultBranch,
	) {}
}
