<?php
use GT\Http\Response;

function go(Response $response):void {
	$response->redirect("./Home/");
}
