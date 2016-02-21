<?php
class KategorierHandler extends RESTHandler
{
	public function get($request) {
		return $this->db->call('getKategorier');
	}
}
