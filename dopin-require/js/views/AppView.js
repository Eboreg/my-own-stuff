/**
 * UserPlace och CafeListView instansierar och initialiserar sig själva.
 */

// Av debug-skäl:
var dopin = dopin || {};
define([
	'backbone',
	'views/MapView',
	'models/UserPlace',
	'views/UserPlaceView',
	'views/CafeView',
	'models/Cafe',
	'collections/KategoriCollection',
	'collections/CafeCollection',
	'views/KategoriView',
	'views/KategorierView',
	'views/CafeListView',
], function(Backbone, MapView, userplace, UserPlaceView, CafeView, Cafe, kategoricollection, CafeCollection, KategoriView, KategorierView) {
	var AppView = Backbone.View.extend({
		el: '#app',
		
		initialize : function() {
			this.kategorier = kategoricollection;
			dopin.kategorierview = new KategorierView({ collection : kategoricollection });
			this.kategorier.fetch();
		},
		showMap : function() {
			console.log('AppView::showMap()');
			if (!this.mapview) {
				this.mapview = dopin.mapview = new MapView({ collection : new CafeCollection(), kategorier : kategoricollection });
				this.mapview.render();
			}
			if (!this.userplaceview) {
				this.userplaceview = new UserPlaceView({ model : userplace, mapview : this.mapview });
			}
			this.$("#map-element").show();
			this.$("#cafe-element").hide();
		},
		showCafe : function(slug) {
			this.cafe = new Cafe();
			this.cafe.url = 'api/cafes/'+slug;
			this.cafe.fetch();
			this.cafeview = new CafeView({ model : this.cafe });
			this.$("#cafe-element").show();
			this.$("#map-element").hide();
		},
	});
	dopin.appview = new AppView();
	return dopin.appview;
});
