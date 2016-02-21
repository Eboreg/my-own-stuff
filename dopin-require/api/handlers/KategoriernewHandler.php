<?php
class KategoriernewHandler extends RESTHandler {
	public function get($request) {
		return $this->db->call('getKategorier');
	}
}
?>