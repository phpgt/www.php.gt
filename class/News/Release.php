<?php
namespace GT\Website\News;

use DateTime;
use GT\DomTemplate\BindGetter;
use GT\Website\Content\MarkdownToHTML;

class Release implements NewsUpdateItem {
	public function __construct(
		public string $repo,
		public DateTime $dateTime,
		public string $version,
		public string $title,
		public string $body,
	) {}

	public function getDateTime():DateTime {
		return $this->dateTime;
	}

	#[BindGetter]
	public function getPublishedDateString():string {
		return $this->dateTime->format("jS F Y");
	}

	#[BindGetter]
	public function getHtmlContent():string {
		$content = new MarkdownToHTML($this->body, "cache/markdown/" . md5(implode("|", [
			$this->repo,
			$this->dateTime->format("Y-m-d"),
			$this->title,
		])));

		return $content->outputHTML();
	}
}
