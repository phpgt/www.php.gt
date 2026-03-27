<?php
namespace GT\Website\News;

use DateTime;

interface NewsUpdateItem {
	public function getDateTime():DateTime;
}
