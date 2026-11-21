<?php
use GT\Config\Config;
use GT\Website\Offline\StaticBundler;

function go(Config $config):void {
	new StaticBundler($config->get("app.origin") ?? "https://www.php.gt")->build();
}
