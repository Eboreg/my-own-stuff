<?php
class CafesHandler extends RESTHandler 
{
	/**
	 * Hämtar lista över caféer ELLER data om enskilt café.
	 * $request : Request-objekt.
	 * 
	 * Vid hämtning av data om enskilt café, ska $request->url_elements[1] vara satt till ett cafe-id. 
	 * 
	 * Vid hämtning av lista är möjliga värden för $request->parameters:
	 * 'fritext'
	 * 'kategorier' : Kommaseparerad lista med kategori-ID:s
	 * 'bounds' : Geografisk avgränsning enligt format "syd,väst,nord,ost"
	 * 'pos' : Returnerar de kaféer som är närmast denna position, upp till ett bestämt antal
	 * Antingen 'bounds' eller 'pos' måste vara angivet (vid hämtning av lista), annars tomt resultat.
	 */
	public function get($request) {
		// Om data om enskilt kafé ska hämtas (URL = /cafes/[id eller slug]) : 
		if (isset($request->url_elements[1])) {
			$cafe = array();
			$id = $request->url_elements[1];
			$res = $this->db->call('getCafe', $id);
			if (isset($res[0]['id'])) {
				$cafe = $res[0];
    			$otA = $this->db->call('getOppettiderUser', $cafe['id']);
				$oppettider = array();
			    foreach ($otA as $idx => $ot) {
			    	$oppettider[$idx] = array('startdatum' => $ot['startdatum'], 'slutdatum' => $ot['slutdatum']);
					$oppettider[$idx]['veckodagar'] = array();
					for ($i = 0; $i < 7; $i++) {
				        $dagnr = explode(DELIMITER, $ot['dagnr']);
				        $ospec = explode(DELIMITER, $ot['oppetOspec']);
				        $starttid = explode(DELIMITER, $ot['starttid']);
				        $sluttid = explode(DELIMITER, $ot['sluttid']);
						if ($dagnr[$i] != $i) {
							$oppettider[$idx]['veckodagar'][$i] = array('ospec' => 0, 'starttid' => '', 'sluttid' => '');
						}
						else {
							$oppettider[$idx]['veckodagar'][$i] = array('ospec' => $ospec[$i], 'starttid' => $starttid[$i], 'sluttid' => $sluttid[$i]);
						} 
					}
			    }
    			$cafe['oppettider'] = $oppettider;
			}
			return $cafe;
		}
		// Om lista över kaféer ska hämtas (URL = /cafes):
		else {
			$fritext = (isset($request->parameters['fritext']) ? $request->parameters['fritext'] : '');
			if (isset($request->parameters['kategorier'])) {
				$kategorier = $request->parameters['kategorier'];
			}
			else {
				$kategorier = array();
			} 
			// För MapView :
			if (isset($request->parameters['bounds'])) {
				$bounds = explode(',', $request->parameters['bounds']);
				return $this->db->call('getCafesByBounds', $bounds[0], $bounds[1], $bounds[2], $bounds[3], $fritext, $kategorier);
			}
			// För CafeListView :
			elseif (isset($request->parameters['pos'])) {
				$pos = explode(',', $request->parameters['pos']);
				//$pos = array(̈́'lat' => $pos[0], 'lng' => $pos[1]);
				$pos['lat'] = $pos[0];
				$pos['lng'] = $pos[1];
				$limit = 40;
	            $cafelist = $this->db->call('getCafesByPos', $pos['lat'], $pos['lng'], $limit, $fritext, $kategorier);
	            foreach ($cafelist as &$cafe) {
	                $cafe['distance'] = distance($pos, $cafe);
	            }
	            return $cafelist;
			}
			else {
				return array();
			}
		}
	}
	
	/**
	 * Liten hjälpfunktion som tar bort ev. 'kategori'-prefix från kategori-ID
	 * Används ej f.n.
	 */
	public function trimKatId($id) {
		return str_replace('kategori', '', $id);
	}
}
?>