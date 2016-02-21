/**
 * Events som andra lyssnar på:
 * 		Backbone.'router:showMap' : AppView visar kartan 
 */
define([
	'backbone',
	'views/AppView'
], function(Backbone, App) {
	var Router = Backbone.Router.extend({
		routes : {
			'cafe/:slug' : 'showCafe',
			'*default' : 'showMap',
		},
		showCafe : function(slug) {
			console.log('Router::showCafe()', slug);
			Backbone.trigger('router:showcafe');
			App.showCafe(slug);
		},
		showMap : function() {
			console.log('Router::showMap()');
			Backbone.trigger('router:showmap');
			App.showMap();
		},
	});
	return Router;
});