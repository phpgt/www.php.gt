<?php
use Gt\DomTemplate\Binder;
use Gt\Input\Input;

function go(Input $input, Binder $binder):void {
	if($query = $input->getString("query")) {
		$binder->bindKeyValue("query", $query);
	}
}
