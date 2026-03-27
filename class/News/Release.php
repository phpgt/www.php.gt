<?php
namespace GT\Website\News;

use DateTime;

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
}
