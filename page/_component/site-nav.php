<?php
use Gt\Dom\Element;
use Gt\DomTemplate\Binder;
use Gt\Http\Response;
use Gt\Http\Uri;
use Gt\Input\Input;
use Gt\Session\Session;
use GT\Website\Content\Markdown;
use GT\Website\Content\RepoHasNoSidebarException;
use Gt\Website\Content\RepoList;
use GT\Website\Content\SidebarMarkdown;

function go(
	Element $element,
	Binder $binder,
	Input $input,
	Session $session,
	Uri $uri,
	Response $response,
):void {
	$path = $uri->getPath();

	/** @var Element $anchor */
	foreach($element->querySelectorAll("a") as $anchor) {
		$li = $anchor->closest("li");

		if($anchor->href === "/") {
			if($path === "/") {
				$li->classList->add("selected");
			}
		}
		else {
			if(str_starts_with($path, $anchor->href)) {
				$li->classList->add("selected");
			}
		}
	}

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
			"docs/$currentRepo"
		)
	);
}
