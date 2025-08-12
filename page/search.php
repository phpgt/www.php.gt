<?php

use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Input\Input;
use GT\Website\Search\Query;

function go(
	Binder $binder,
	Input $input,
	Response $response,
):void {
	if(!$input->getString("query")) {
		$response->redirect("/");
	}

	$search = new Query($input->getString("query"));
	$binder->bindList($search);
}
