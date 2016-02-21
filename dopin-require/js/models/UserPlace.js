/**
 * Sätter igång geolocation som bevakar användarens position.
 * Attribut:
 * 	'latlng' : google.maps.LatLng med användarens position
 * Events:
 * 	Backbone.'userplace:change' : CafeListView::fetchCafes() körs
 * 	'change:latlng' : UserPlaceView::move() körs
 */
define(['backbone', 'underscore', 'google'], function(Backbone, _, google) {
	UserPlace = Backbone.Model.extend({
		defaults : {
			zoom : true,
			pan : true
		},
		initialize : function() {
			this.on('change:latlng', function() {
				Backbone.trigger('userplace:change', this.get('latlng'));
			}, this);
			if ("geolocation" in navigator) {
				var watchSuccessHandler = function(pos) {
					this.set({
						latlng : new google.maps.LatLng(pos.coords.latitude, pos.coords.longitude)
					});
				};
				var errorHandler = function(err) {
					this.trigger('error', err);
				};
				watchSuccessHandler = _.bind(watchSuccessHandler, this);
				errorHandler = _.bind(errorHandler, this);
				this.gcid = navigator.geolocation.watchPosition(watchSuccessHandler, errorHandler, {
					enableHighAccuracy : true,
				});
			}
		},
		disableGeolocation : function() {
			if (this.gcid)
				navigator.geolocation.clearWatch(this.gcid);
		}
	});
	//dopin.userplace = new UserPlace();
	return new UserPlace();
});
