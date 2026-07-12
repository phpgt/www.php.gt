<?php
use Gt\Http\Response;
use GT\Routing\Path\DynamicPath;

function go(DynamicPath $dynamicPath, Response $response):void {
	$repo = $dynamicPath->get();
	$response->redirect("https://github.com/phpgt/$repo");
}
