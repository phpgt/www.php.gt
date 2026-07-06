<?php
use Gt\Config\Config;
use GT\Website\Blueprint\BlueprintLoader;

function go(Config $config):void {
	$blueprintConfig = $config->getSection("blueprint");

	$loader = new BlueprintLoader(
		$blueprintConfig->getString("path"),
	);
	$blueprintList = $loader->loadBlueprintList();
	file_put_contents("data/content/blueprint-list.dat", serialize($blueprintList));

	echo "Loaded " . count($blueprintList) . " blueprints", PHP_EOL;
}
