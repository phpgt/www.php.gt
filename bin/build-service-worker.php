<?php
// Make application asset changes visible to the browser's worker update check.
chdir(dirname(__DIR__));
$hash = hash_init("sha256");
foreach(["www/script.js", "www/style.css", "script/service-worker/sw.js"] as $file) {
	if(!hash_update_file($hash, $file)) throw new RuntimeException("Cannot hash $file");
}
$worker = file_get_contents("script/service-worker/sw.js");
$worker = str_replace("__APP_BUILD__", hash_final($hash), $worker);
if(file_put_contents("www/sw.js", $worker) === false) throw new RuntimeException("Cannot build service worker");
