<?php
use Gt\Routing\Path\DynamicPath;

function go(
	DynamicPath $dynamicPath,
):void {
		$repo = $dynamicPath->get("repo");
		$doc =  $dynamicPath->get("doc");
}
