/**
 * Events:
 * 	'change' : CafeListItemView::render() körs
 * 	'change:katMatches' : CafeMarkerView::redraw() körs
 * 	'change:openNow' : CafeMarkerView::redraw() körs
 *  'remove' : CafeMarkerView::remove() körs
 * 	'change' : CafeMarkerView::updateInfoWindow() körs
 * Specialproperties (lagras ej):
 * 	'highlight' (bool) : Kan avlyssnas av olika views för att markera cafét på lämpligt sätt
 */
define([
	'backbone',
	'underscore',
	'google',
	'models/UserPlace',
	'utils'
], function(Backbone, _, google, userplace, utils) {
	var Cafe = Backbone.Model.extend({
		initialize : function() {
			_.bindAll(this, 'getDistances', 'getDistanceFromGoogle', 'formatOppettider');
//			this.on('sync', this.formatOppettider);
		},
		formatOppettider : function() {
			if (this.get('oppettider')) {
				console.log(this.get('oppettider'));
				var oppettider = new Array();
				_.each(this.get('oppettider'), function(oppettid, idx) {
					oppettider[idx] = {};
					oppettider[idx].startdatum = oppettid.startdatum;
					oppettider[idx].slutdatum = oppettid.slutdatum;
					oppettider[idx].veckodagar = new Array();
					_.each(utils.veckodagar, function(veckodag, dagnr) {
						oppettider[idx].veckodagar[dagnr] = {};
						oppettider[idx].veckodagar[dagnr].namn = veckodag;
						oppettider[idx].veckodagar[dagnr].starttid = oppettid.starttid[dagnr];
						oppettider[idx].veckodagar[dagnr].sluttid = oppettid.sluttid[dagnr];
						oppettider[idx].veckodagar[dagnr].ospec = (oppettid.oppetOspec[dagnr] > 0 ? true : false);
					});
				});
				this.set('oppettider', oppettider);
			}
		},
		/**
		 * Bör av belastnings- och kvotaskäl bara köras när det finns en öppen InfoWindow knuten till objektet.
		 * Binds därför till userplace:'change:latlng' av CafeMarkerView::openInfoWindow() (samt körs en initial gång).
		 */
		getDistances : function() {
			if (userplace.get('latlng')) {
				this.dms = this.dms || new google.maps.DistanceMatrixService();
				this.set('latlng', new google.maps.LatLng(this.get('lat'), this.get('lng')));
				this.set('distance', utils.distanceFromPos(this.get('lat'), this.get('lng'), userplace.get('latlng').lat(), userplace.get('latlng').lng())); 
				this.set('bird', utils.metersToString(this.get('distance')));
				this.getDistanceFromGoogle(google.maps.TravelMode.WALKING, function(distance, duration, status) {
					if (status === true) {
						this.set('walking', { distance : distance, duration : duration });
					}
					else {
						this.unset('walking');
					}
				});
				this.getDistanceFromGoogle(google.maps.TravelMode.BICYCLING, function(distance, duration, status) {
					if (status === true) {
						this.set('bicycling', { distance : distance, duration : duration });
					}
					else {
						this.unset('bicycling');
					}
				});
				this.getDistanceFromGoogle(google.maps.TravelMode.DRIVING, function(distance, duration, status) {
					if (status === true) {
						this.set('driving', { distance : distance, duration : duration });
					}
					else {
						this.unset('driving');
					}
				});
			}
		},
		getDistanceFromGoogle : function(travelMode, callback) {
			callback = _.bind(callback, this);
			this.dms.getDistanceMatrix({
				origins : [userplace.get('latlng')],
				destinations : [this.get('latlng')],
				travelMode : travelMode,
				unitSystem : google.maps.UnitSystem.METRIC
			}, function(result, status) {
	            var row;
	            var element;
	            var ok = false;
	            for (r in result.rows) {
	                for (e in result.rows[r].elements) {
	                    if (result.rows[r].elements[e].status === google.maps.DistanceMatrixElementStatus.OK) {
	                        callback(result.rows[r].elements[e].distance.text, result.rows[r].elements[e].duration.text, true);
	                        ok = true;
	                    }
	                    if (ok) break;
	                }
	                if (ok) break;
	            }
	            if (!ok) callback('', '', false);
	        });
		},
	});
	return Cafe;
});
