<?php
use GT\DomTemplate\Binder;
use GT\Input\Input;

function go(Input $input, Binder $binder):void {
	if($query = $input->getString("query")) {
		$binder->bindKeyValue("query", $query);
	}
}
