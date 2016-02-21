<?php
/**
 * Används i autocomplete-ruta i js/views/UserPlaceView.js
 * Skickar sökterm till Google Maps och returnerar resultat i format lämpligt för användning i JqueryUI.autocomplete()
 *
 * Parameter: 'term' (adress-sökterm)
 * Retur: array('label' => formatted_address, 'location' => objekt med attribut 'lat' och 'lng')
 */
class AddressSearchHandler extends RESTHandler
{
	static private $url = "http://maps.google.com/maps/api/geocode/json?sensor=false&language=sv&region=se&address=";

	/**
	 * Returnerar getLocation(), men bara om sökterm är 3 tecken eller längre
	 */
	public function get($request) {
		if (isset($request->parameters['term']) && strlen($request->parameters['term']) >= 3) {
			return self::getLocation($request->parameters['term']);
		}
	}

	/**
	 * Hämtar location-data från Google. Returnerar som array enl. ovan, eller false om misslyckat.
	 */
	static private function getLocation($address) {
		$ret = array();
		$url = self::$url . urlencode($address);
		$resp_json = self::curl_file_get_contents($url);
		$resp = json_decode($resp_json, true);
		if ($resp['status'] = 'OK') {
			foreach ($resp['results'] as $row) {
				$ret[] = array('label' => $row['formatted_address'], 'location' => $row['geometry']['location']);
			}
			return $ret;
		} else {
			return false;
		}
	}

	static private function curl_file_get_contents($URL) {
		$c = curl_init();
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_URL, $URL);
		$contents = curl_exec($c);
		curl_close($c);
		if ($contents)
			return $contents;
		else
			return FALSE;
	}
}
