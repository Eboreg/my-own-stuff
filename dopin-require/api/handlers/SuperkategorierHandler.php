<?php
class SuperkategorierHandler extends RESTHandler {
	public function get($request) {
		// Om superkategorier/{id}/subs är anropat, efterfrågas subkategorier till {id}
		if (isset($request->url_elements[2]) && $request->url_elements[2] == 'subs') {
			return $this->db->call('getSubkategorier', $request->url_elements[1]);
		}
		// En specifik superkategori efterfrågad:
		elseif (isset($request->url_elements[1])) {
			$ret = $this->db->call('getSuperkategori', $request->url_elements[1]);
			return $ret[0];
		} 
		// Annars (bara superkategorier/ anropat) efterfrågas samtliga superkategorier:
		else {
			return $this->db->call('getSuperkategorier');
		}
	}
	public function put($request) {
		// Uppdatera subkategori?
		if (isset($request->url_elements[3]) && $request->url_elements[2] == 'subs') {
		}
		// Uppdatera superkategori?
		else {
			$params = $request->parameters;
			$ret = $this->db->call('updateSuperkategori', $params['id'], $params['namn'], $params['order'], $params['synlig']);
			return $ret[0];
		}
	}
}
?>