<?php
/**
 * Kallas via .htaccess, RESTful sökväg finns i $_SERVER['PATH_INFO'] som parse:as av Request() och hamnar i
 * $request->url_elements.
 * 
 * Om anropad sökväg med metod GET är /books/123, kommer $request->url_elements att innehålla ['books', '123'].
 * Om då klassen BooksHandler finns, kommer dennas get() att köras med $request som parameter.
 * 
 * Alla *Handler-klasser ska ärva från RESTHandler. Dennas render() matar ut get():s resultat i JSON-format.
 * 
 * (Skiter alltså i MVC här och bakar ihop allting i något jag i brist på bättre kallar Handler.)
 */
require_once(__DIR__.'/../php/config.php');
require_once(__DIR__.'/../php/util.php');
require_once(__DIR__.'/Request.php');
require_once(__DIR__.'/../php/DBSession.php');

spl_autoload_register('apiAutoload');
function apiAutoload($classname) {
	if (preg_match('/[a-zA-Z]+Handler$/', $classname)) {
		include __DIR__.'/handlers/' . $classname . '.php';
		return true;
	}
}

$request = new Request();

$handler_name = ucfirst($request->url_elements[0]) . 'Handler';  // Sökväg /cafes/1234 blir 'CafesHandler'
if (class_exists($handler_name)) {
	$handler = new $handler_name();  // Sökväg /cafes/1234 ger new CafesHandler()
	$action_name = strtolower($request->verb);   // 'get', 'post', 'put' eller 'delete', tas från $_SERVER['REQUEST_METHOD']
	$result = $handler->$action_name($request);  // GET /cafes/1234 ger CafesHandler::get(Request)
	$handler->render($result);
}
else {
	include '../index.php';  // ?? Denna fil finns inte. Menas index.html månne?
}
