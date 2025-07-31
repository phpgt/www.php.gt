<?php

use Gt\Http\Response;
use Gt\Input\Input;

function go(Input $input, Response $response):void {
	if(!$input->getString("query")) {
		$response->redirect("/");
	}
}
