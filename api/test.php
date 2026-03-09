<?php
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Http\StatusCode;
use Gt\Input\Input;
use GT\WebEngine\DataDocument\DataDocument;

function go(
	Binder $binder,
	Input $input,
	DataDocument $dataDocument,
	Response $response,
):void {
	if(!$input->contains("name")) {
		$dataDocument->set("error", "Missing name");
		$response->abort(StatusCode::BAD_REQUEST);
	}

	$name = $input->getString("name");
	$dataDocument->set("message", "Hello, $name!");
	$dataDocument->set("input.name", $name);
	$dataDocument->set("input.length", strlen($name));

	// or we could replace {{name}} with $binder->bind("name", $name);
	// but that would require a new Binder object. This is more complex than needed right now.
}
