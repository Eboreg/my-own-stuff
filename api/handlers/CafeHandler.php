<?php
class CafeHandler extends RESTHandler
{
	public function render() {
		include '../index.php';
		//header('Content-Location: /dopin/index.php');
	}
}
