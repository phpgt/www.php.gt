<?php
namespace GT\Website\News;

use DateTime;
use GT\Website\Content\MarkdownToHTML;
use Gt\DomTemplate\BindGetter;

class Announcement implements NewsUpdateItem {
	private const array AUTHOR_USERNAME = [
		"g105b" => "Greg Bowler",
	];

	public function __construct(
		public int $id,
		public string $title,
		public string $body,
		public DateTime $dateTime,
		public ?string $author,
	) {
	}

	public function getDateTime():DateTime {
		return $this->dateTime;
	}

	#[BindGetter]
	public function getPublishedDateString():string {
		return $this->dateTime->format("jS F Y");
	}

	#[BindGetter]
	public function getAuthorName():?string {
		if(!$this->author) {
			return null;
		}

		[$username, $name] = array_pad(explode(":", $this->author, 2), 2, null);
		return $name ?? (self::AUTHOR_USERNAME[$username] ?? null);
	}

	#[BindGetter]
	public function getAuthorUsername():?string {
		if(!$this->author) {
			return null;
		}

		return explode(":", $this->author)[0];
	}

	#[BindGetter]
	public function getHtmlContent():string {
		return new MarkdownToHTML($this->body)->outputHTML();
	}
}
