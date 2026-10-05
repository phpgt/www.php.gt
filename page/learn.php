<?php

use GT\DomTemplate\Binder;
use GT\Http\Response;
use GT\Http\ResponseStatusException\ServerError\HttpInternalServerError;
use GT\Http\ServerInfo;
use GT\Input\Input;

function go(Input $input, Binder $binder):void {
	if($input->contains("thanks")) {
		$binder->bindKeyValue("complete", true);
	}
}

function do_signup(Input $input, Response $response, ServerInfo $serverInfo):void {
	$ip = $serverInfo->getRemoteAddress();

	$email = $input->getString("email");
	if($spam = $input->getString("address")) {
		file_put_contents("log-spam.txt", implode("\t", [
				date("Y-m-d H:i:s"),
				$ip,
				$email,
				$spam,
			]) . PHP_EOL, FILE_APPEND);

		$response->redirect("https://www.youtube.com/watch?v=JTi6LG8aeK8");
	}

	file_put_contents("log-email.txt", implode("\t", [
			date("Y-m-d H:i:s"),
			$ip,
			$email,
		]) . PHP_EOL, FILE_APPEND);

	$response->redirect("?thanks");
}
