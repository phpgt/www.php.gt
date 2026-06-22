<?php
use GT\Website\Search\SearchIndex;

function go():void {
	$index = new SearchIndex("data/content");
	$index->generate();
}
