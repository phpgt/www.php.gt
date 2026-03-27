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
	/** @var array<NewsUpdateItem> $newsUpdateList */
	$newsUpdateList = $loader->sort(
		...$loader->loadReleaseList(),
		...$loader->loadAnnouncementList(),
	);
}

// TODO: Automate when cron is implemented.
chdir(__DIR__ . "/..");
require "vendor/autoload.php";

$configFactory = new ConfigFactory();
$config = $configFactory->createForProject(getcwd());

go($config->getSection("github"));
