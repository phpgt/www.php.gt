<?php
namespace GT\Website\News;

use DateTime;

class Announcement implements NewsUpdateItem {
	public function __construct(
		public string $title,
		public string $body,
		public DateTime $dateTime,
		public ?string $username,
	) {
	}

	public function getDateTime():DateTime {
		return $this->dateTime;
	}
}
