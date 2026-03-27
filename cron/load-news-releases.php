<?php
use Gt\Config\ConfigFactory;
use Gt\Config\ConfigSection;
use Gt\Fetch\Http;
use Gt\FileCache\Cache;
use GT\Website\News\NewsUpdateItem;
use GT\Website\News\NewsUpdateLoader;

function go(ConfigSection $githubConfig):void {
	$loader = new NewsUpdateLoader(
		new Cache(),
		new Http(),
		$githubConfig->getString("access_token"),
	);
	$announcementList = $loader->sort(
		...$loader->loadAnnouncementList(),
	);
	$releaseList = $loader->sort(
		...$loader->loadReleaseList(),
	);
	file_put_contents("data/content/announcement-list.dat", serialize($announcementList));
	file_put_contents("data/content/release-list.dat", serialize($releaseList));
}

// TODO: Automate when cron is implemented.
chdir(__DIR__ . "/..");
require "vendor/autoload.php";

$configFactory = new ConfigFactory();
$config = $configFactory->createForProject(getcwd());

go($config->getSection("github"));
