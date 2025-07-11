<?php
use Gt\Dom\Element;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Http\Uri;
use Gt\Input\Input;
use Gt\Session\Session;
use GT\Website\Content\Markdown;
use Gt\Website\Content\RepoList;

function go(
	Element $element,
	Binder $binder,
	Input $input,
	Session $session,
	Uri $uri,
	Response $response,
):void {
	$repoList = new RepoList();
	$binder->bindList($repoList);

	if($currentRepo = $session->getString("repo") ?? "WebEngine") {
		$binder->bindKeyValue("repo", $currentRepo);
	}

	if($switchRepo = $input->getString("repo")) {
		$session->set("repo", $switchRepo);
		$response->redirect($uri->withoutQueryValue("repo"));
	}

	$binder->bindKeyValue(
		"sidebarContent",
		new Markdown(
			"data/content/$currentRepo/_Sidebar.md",
			"/docs/$currentRepo"
		)
	);

	foreach($element->querySelectorAll(".pageLinks > h1") as $pageHeading) {
		if($pageHeading->firstElementChild) {
			continue;
		}

		$link = $element->ownerDocument->createElement("a");
		$link->textContent = $pageHeading->textContent;
		$pageHeading->innerText = "";
		$pageHeading->appendChild($link);

		if($nextLink = $pageHeading->nextElementSibling->querySelector("a")) {
			$link->href = $nextLink->href;
		}
	}

	$path = $uri->getPath();

	/** @var Element $anchor */
	foreach($element->querySelectorAll("a") as $anchor) {
		$li = $anchor->closest("li,h1");

		if(rtrim($anchor->href, "/") === rtrim($path, "/")) {
			$element->querySelectorAll(".selected")->forEach(fn(Element $selectedElement) => $selectedElement->classList->remove("selected"));
			$li->classList->add("selected");
		}
//		if($anchor->href === "/") {
//			if($path === "/") {
//				$li->classList->add("selected");
//			}
//		}
//		else {
//			if(str_starts_with($path, $anchor->href)) {
//				$li->classList->add("selected");
//			}
//		}
	}
}
