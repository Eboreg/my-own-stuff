<?php
//include_once('phpcoord-2.3.php');
include_once(__DIR__.'/PHPCoord/LatLng.php');
include_once(__DIR__.'/PHPCoord/RefEll.php');

function rprint($var) {
	echo '<pre>';
	print_r($var);
	echo '</pre>';
}

/**
 * $from, $to = array('lat', 'lng')
 * Returnerar avstånd i m.
 */
function distance($from, $to) {
    $fromll = new PHPCoord\LatLng($from['lat'], $from['lng']);
    $toll = new PHPCoord\LatLng($to['lat'], $to['lng']);
    return $fromll->distance($toll);
}

?>