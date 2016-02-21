/**
 * Används av både MapView och CafeListView, fast i två olika instanser.
 * Hämtning av kaféer initieras dels i MapView::initialize() (vid 'map:idle' och var 15:e minut), 
 * dels i CafeListView::initialize() (vid userplace:'change:latlng').
 * Men även i denna collections initialize() (uppdatering av data varje hel kvart).
 * Events:
 * 	'sort' : CafeListView::render() körs
 * 	'add' : MapView::addMarker() körs
 */
define([
	'backbone',
	'underscore',
	'models/Cafe',
	'models/UserPlace',
	'views/MapView',
], function(Backbone, _, Cafe) {
	var CafeCollection = Backbone.Collection.extend({
		model : Cafe,
		url : 'api/cafes',
		
		initialize : function() {
			_.bindAll(this, 'fetchListCafes');
		},
	
		/**
		 * Sorterar först enligt kategoriträffar, sedan i avståndsordning, med kaféer utan känt avstånd sist
		 */
		comparator : function(m1, m2) {
			var m1dist = m1.get('distance');
			var m2dist = m2.get('distance');
			var m1kats = m1.get('katMatches');
			var m2kats = m2.get('katMatches');
			if (m1kats != m2kats) {
				return m2kats - m1kats;
			}
			else {
				return m1dist - m2dist;
			}
		},
		
		/**
		 * Binds till userplace:'change:latlng' i CafeListView::initialize()
		 * UPPD: Nej, sådan coupling ska vi ej ha -- gör om gör rätt
		 * Körs alltså varje gång användarens position förändrats.
		 * Hämtar data via SQL-prodecuren getCafesByPos()
		 */
		fetchListCafes : function() {
			this.forEach(function rensa(cafe) {
				cafe.unset('distance', {silent : true});
			});
			this.fetch({data : {pos : dopin.userplace.get('latlng').toUrlValue()}, merge : true, remove : false});
		},
	});
	return CafeCollection;
});
