<?php
use GT\DomTemplate\Binder;
use GT\Input\Input;

function go(
	Binder $binder,
	Input $input,
):void {
	$releaseListFile = "data/content/release-list.dat";
	if(!is_file($releaseListFile)) {
		return;
	}

	$page = max(0, $input->getInt("page") ?? 0);
	$numPerPage = 25;

	$releaseList = unserialize(file_get_contents($releaseListFile));
	$releaseList = array_splice(
		$releaseList,
		$page * $numPerPage,
		$numPerPage,
	);

	$binder->bindList($releaseList);
	if($page > 0) {
		$binder->bindKeyValue("prevPage", $page - 1);
	}
	$binder->bindKeyValue("nextPage", $page + 1);
}
