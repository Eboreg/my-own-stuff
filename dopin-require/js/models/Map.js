/**
 * Används ej!
 * Kanske borde användas som wrapper för google.maps.Map?
 * Nej, kanske inte går då kartan ritas ut i samma ögonblick som dess objekt skapas...
 */
define([
	'backbone',
	'google'
], function(Backbone, google) {
	var Map = Backbone.Model.extend({
		defaults : {
			mapOptions : {
				center : new google.maps.LatLng(63, 14),
				zoom : 5,
				mapTypeId : google.maps.MapTypeId.ROADMAP
			}
		}
	});
	return Map;
});