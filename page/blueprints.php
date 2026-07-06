<?php
use Gt\DomTemplate\Binder;

function go(
	Binder $binder,
):void {
	$blueprintListFile = "data/content/blueprint-list.dat";
	if(!is_file($blueprintListFile)) {
		return;
	}

	$blueprintList = unserialize(file_get_contents($blueprintListFile));
	$binder->bindList($blueprintList);
}
