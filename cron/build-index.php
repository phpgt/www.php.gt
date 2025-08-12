<?php
use GT\Website\Search\SearchIndex;

##[Cron(CronEveryHour())]
function go():void {
	$index = new SearchIndex("data/content");
	$index->generate();
}

// TODO: Automate when cron is implemented.
chdir(__DIR__ . "/..");
require "vendor/autoload.php";
go();
