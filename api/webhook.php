<?php
use Gt\Config\Config;
use Gt\Http\Request;
use Gt\Input\Input;

function go(
	Config $config,
	Input $input,
	Request $request,
):void {
	$secret = $config->getString("github.secret");

	$bodyString = $input->getStream()->getContents();
	$requestSignature = $request->getHeaderLine("X-Hub-Signature-256");
	$computedSignature = "sha256=" . hash_hmac("sha256", $bodyString, $secret);

	if(!hash_equals($computedSignature, $requestSignature)) {
		http_response_code(401);
// TODO: Set error on JsonDocument
		die("Webhook signature verification failed");
	}

	$json = $input->getBodyJson();
	$orgRepo = $json->getObject("repository")
		->getString("full_name");
	[$orgName, $repoName] = explode("/", $orgRepo);
	$repoPath = "data/content/$repoName";
// TODO: Actually update the repo!
	die($repoPath);
}
