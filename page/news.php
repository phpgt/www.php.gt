<?php
use Gt\DomTemplate\Binder;

function go(
	Binder $binder,
):void {
	$announcementListFile = "data/content/announcement-list.dat";
	if(!is_file($announcementListFile)) {
		return;
	}

	$announcementList = unserialize(file_get_contents($announcementListFile));
	$binder->bindList($announcementList);
}
