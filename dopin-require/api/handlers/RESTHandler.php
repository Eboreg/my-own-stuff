<?php
/**
 * Über-klass med gemensamma metoder. Finns till för att implementeras av "riktiga" Handler-klasser.
 * 
 * @todo Bygg ut möjligheten till custom HTTP-felkoder
 */
class RESTHandler 
{
	protected $db;
	
	public function __construct() {
		DBSession::sessionStart();
		$this->db = $_SESSION['dbSession'];
	}
	
	public function render($content) {
		//header('Content-Type: application/json; charset=utf8');
		if (false === $content) {
			header("HTTP/1.0 404 Not Found");
			return false;
		} else {
			echo json_encode($content);
			return true;
		}
	}
	
	/**
	 * MySQL-escape:ar $var rekursivt. $var kan vara sträng eller array.
	 */
	public function safe($var) {
		if (is_array($var)) {
			foreach ($var as &$val) {
				$val = $this->safe($val);
			}
		}
		else {
			$var = $this->db->safe($var);
		}
		return $var;
	}
	
	// Alla Handlers bör göra egna implementationer av dessa. Annars returneras 404.
	public function get() { return false; }
	public function post() { return false; }
	public function put() { return false; }
	public function delete() { return false; }
}
?>