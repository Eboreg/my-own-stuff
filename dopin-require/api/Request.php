<?php
/**
 * Tar hand om och formaterar en RESTful HTTP-request. Anropas från ./index.php.
 * Allt görs i konstruktorn och relevant data läggs i följande:
 * 
 * $url_elements: Sökvägen i den efterfrågade URL:en, splittad i sina beståndsdelar t.ex. ['books', '123', 'edit'] för /books/123/edit
 * $verb: GET, POST, PUT eller DELETE
 * $parameters: Assoc. array med parametrar från webbformulär (t.ex. vanliga POST-data) eller JSON-sträng
 * $format: 'json' eller 'html' 
 */
class Request 
{
    public $url_elements;
	public $verb;
	public $parameters;
	public $format;
	
	public function __construct() {
		$this->verb = $_SERVER['REQUEST_METHOD'];
		// Denna variant tar bort ev. inledande och avslutande snedstreck i sökvägen:
		$path_info = preg_replace('#^/?([^/].*[^/])/?$#', '$1', $_SERVER['PATH_INFO']);
		if ($path_info == '/') $path_info = '';
		$this->url_elements = explode('/', $path_info); 
		// Denna variant rensar alla URL-komponenter som är tomma strängar, men är det önskvärt? :
		//$this->url_elements = array_values(array_filter(explode('/', $path_info)));
		$this->format = 'json'; // default
		$this->parseIncomingParams();
		return true;
	}
	
	/**
	 * Går igenom GET-, PUT- och POST-variabler samt inskickad JSON-data. Lägger dessa i $this->parameters.
	 * Sätter $this->format till 'json' eller 'html'.
	 */
	private function parseIncomingParams() {
		$parameters = array();
		
		// Behandla GET-variabler
		if (isset($_SERVER['QUERY_STRING'])) {
			parse_str($_SERVER['QUERY_STRING'], $parameters);
		}
		
		// Behandla PUT/POST/JSON (override:ar GET-variabler)
		$body = file_get_contents("php://input");
		if (isset($_SERVER['CONTENT_TYPE'])) {
			if (false !== strpos($_SERVER['CONTENT_TYPE'], ';')) {
				// Strippar content-type från ev. tillägg efter semikolon
				$content_type = substr($_SERVER['CONTENT_TYPE'], 0, strpos($_SERVER['CONTENT_TYPE'], ';'));
			} else {
				$content_type = $_SERVER['CONTENT_TYPE'];
			}
		} else {
			$content_type = false;
		}
		switch ($content_type) {
			case "application/json":
				$body_params = json_decode($body);
				if ($body_params) {
					foreach ($body_params as $param_name => $param_value) {
						$parameters[$param_name] = $param_value;
					}
				}
				$this->format = 'json';
				break;
			case "application/x-www-form-urlencoded":
				parse_str($body, $postvars);
				foreach ($postvars as $field => $value) {
					$parameters[$field] = $value;
				}
				$this->format = 'html';
				break;
			default:
				break;
		}
		$this->parameters = $parameters;
	}
}
