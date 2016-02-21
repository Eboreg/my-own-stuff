/**
 * Agerar både view åt en Cafe-modell och under-view åt MapView.
 * this.model : Cafe
 * this.mapview : MapView-objektet
 * Klassen instansieras i MapView::addMarker().
 * Events som kan triggas:
 * 	'remove' -> ta bort markören, lyssnas av MapView 
 */
define([
	'backbone',
	'underscore',
	'google',
	'utils',
	'models/Cafe',
	'views/MapView',
], function(Backbone, _, google, utils) {
	var CafeMarkerView = Backbone.View.extend({
		markerEvents : {
			'mouseover' : 'mouseover',
			'mouseout' : 'mouseout',
			'click' : 'toggleInfoWindow'
		},
		infoWindowEvents : {
			'closeclick' : 'closeInfoWindow'
		},
		infoWindowIsOpen : false,
		
		initialize : function() {
			this.marker = new google.maps.Marker(this.getMarkerOptions());
			this.bindMarkerEvents();
			this.listenTo(this.model, 'change:katMatches', this.redraw);
			this.listenTo(this.model, 'change:openNow', this.redraw);
			this.listenTo(this.model, 'remove', this.clear);
			this.listenTo(this.model, 'change:highlight', this.toggleAnimate);
		},
		
		/**
		 * Ritar om markören. Körs när något i modellen ändrats (typiskt sett openNow).
		 */
		redraw : function() {
			console.log('CafeMarkerView:change', this.model.get('namn'));
			this.marker.setOptions(this.getMarkerOptions());
		},
		clear : function() {
			this.trigger('remove');
			this.remove();
		},
		toggleAnimate : function() {
			switch(this.model.get('highlight')) {
				case true:
				this.animate();
				break;
				case false:
				this.animateStop();
			}
		},
		animate : function() {
			this.mapview.markercluster.removeMarker(this.marker);
			this.marker.setMap(this.mapview.map);
			this.marker.setAnimation(google.maps.Animation.BOUNCE);
		},
		animateStop : function() {
			this.marker.setAnimation(null);
			this.marker.setMap(null);
			this.mapview.markercluster.addMarker(this.marker);
			this.mapview.markercluster.resetViewport();
			this.mapview.markercluster.redraw();
		},
		getMarkerOptions : function() {
			return {
				position : new google.maps.LatLng(this.model.get('lat'), this.model.get('lng')),
				draggable : false,
				icon : this.getMarkerImage()
			};
		},
	    getMarkerImage : function() {
	        var icon;
	        var katMatches = parseInt(this.model.get('katMatches'));
	        if (katMatches > 4) {
	            katMatches = 4;
	        }
			switch (this.model.get('openNow')) {
				case 0 :
				case '0' :
				icon = utils.markerImages.cafe.stangt[katMatches];
				break;
				case 2 :
				case '2' :
				icon = utils.markerImages.cafe.oppet[katMatches];
				break;
				default :
				icon = utils.markerImages.cafe.okantoppet[katMatches];
			}		
	        return icon;
	    },
	    
	    mouseover : function() {
			console.log('CafeMarkerView:mouseover', this.model.id, this.model.get('namn'));
			Backbone.trigger('cafemarkerview:mouseover', this.model.id);
	    },
	    mouseout : function() {
			Backbone.trigger('cafemarkerview:mouseout', this.model.id);
	    },
	    
	    /**
	     * INFOWINDOW
	     */
		toggleInfoWindow : function() {
			if (this.infoWindowIsOpen) {
				this.closeInfoWindow();
			} else {
				this.openInfoWindow();
			}
		},
	    openInfoWindow : function() {
	    	this.mapview.infoWindowZIndex = this.mapview.infoWindowZIndex || 0;
			this.mapview.markercluster.removeMarker(this.marker);
			this.marker.setMap(this.mapview.map);
	    	if (!this.infoWindow) {
				this.infoWindowTemplate = _.template($("#cafeInfoWindowText").html());
	   			this.infoWindow = new google.maps.InfoWindow({ content : 'Hämtar data ...' });
	    		this.listenTo(this.model, 'change', this.updateInfoWindow);
	    		// Bugg-workaround (redan öppna infowindows töms vid switch mapview -> cafeview o tillbaka):
	    		this.listenTo(Backbone, 'router:showmap', this.updateInfoWindow);
	    		// Denna bindning sker här eftersom det är alltför resurskrävande att köra den på alla
	    		// Cafe-modeller, så vi kör den bara på dem med öppna InfoWindows:
	    		this.listenTo(Backbone, 'userplace:change', this.model.getDistances);
	    		this.bindInfoWindowEvents();
	    		this.model.fetch();
	    	}
	    	this.infoWindow.setZIndex(this.mapview.infoWindowZIndex++);
	    	this.infoWindow.open(this.mapview.map, this.marker);
	    	this.infoWindowIsOpen = true;
	    },
	    closeInfoWindow : function() {
			console.log('CafeMarkerView::closeInfoWindow', this.model.get('namn'));
			this.stopListening(Backbone, 'userplace:change');
			this.infoWindow.close();
			this.marker.setAnimation(null);
			this.marker.setMap(null);
			this.mapview.markercluster.addMarker(this.marker);
			this.mapview.markercluster.resetViewport();
			this.mapview.markercluster.redraw();
			this.infoWindowIsOpen = false;
	    },
	    updateInfoWindow : function() {
	    	if (!this.model.get('bird')) {
	    		this.model.getDistances();
	    	}
	    	this.infoWindow.setContent(this.infoWindowTemplate(this.model.toJSON()));
	    },
	
	    /**
		 * Delegerar valda Google Maps-events till View-events med namn 'marker:<gmaps-eventnamn>'.
		 * Binder även explicit angivna lyssnare till Google Maps-events via this.markerEvents.
		 */
		bindMarkerEvents : function() {
			var markerEventNames = ['click', 'dblclick', 'mouseover', 'mouseout'];
			_.each(markerEventNames, function(markerEventName) {
				var handler = function() {
					this.trigger('marker:'+markerEventName);
				};
				handler = _.bind(handler, this);
				google.maps.event.addListener(this.marker, markerEventName, handler);
			}, this);
			_.each(this.markerEvents, function(handler, event) {
				handler = _.isString(handler) ? this[handler] : handler;
				if (_.isFunction(handler)) {
					handler = _.bind(handler, this);
					google.maps.event.addListener(this.marker, event, handler);
				}
			}, this);
		},
	    
	    /**
		 * Delegerar valda Google Maps-events till View-events med namn 'infoWindow:<gmaps-eventnamn>'.
		 * Binder även explicit angivna lyssnare till Google Maps-events via this.infoWindowEvents.
		 */
		bindInfoWindowEvents : function() {
			var infoWindowEventNames = ['closeclick', 'content_changed', 'domready'];
			_.each(infoWindowEventNames, function(infoWindowEventName) {
				var handler = function() {
					this.trigger('infoWindow:'+infoWindowEventName);
				};
				handler = _.bind(handler, this);
				google.maps.event.addListener(this.infoWindow, infoWindowEventName, handler);
			}, this);
			_.each(this.infoWindowEvents, function(handler, event) {
				handler = _.isString(handler) ? this[handler] : handler;
				if (_.isFunction(handler)) {
					handler = _.bind(handler, this);
					google.maps.event.addListener(this.infoWindow, event, handler);
				}
			}, this);
		},
	});
	return CafeMarkerView;
});
