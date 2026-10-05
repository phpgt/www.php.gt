<?php

use GT\DomTemplate\Binder;
use GT\Http\Response;
use GT\Input\Input;
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
