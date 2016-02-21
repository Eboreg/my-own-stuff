/**
 * Vyn har direkt kännedom om modellen då det ej finns anledning att ha någon Collection (vi använder bara en karta).
 * Förändringar i geolocation räknas som en användarinteraktion och tas omhand här.
 * 
 * Properties
 * el : kartelementet
 * model : dopin.Map (används ännu bara för att ange defaultvärden; kan ej hämta eller lagra några data någonstans)
 * map : google.maps.Map
 * mapEvents : lyssnar efter events på 'map'
 * collection : CafeCollection
 * 
 * Events:
 * 	Backbone.'mapview:map:idle' : UserPlaceView ritar ut användarmarkör (om position finns)
 */
define([
	'backbone',
	'underscore',
	'google',
	'markerclusterer',
	'views/CafeMarkerView',
	'utils',
	'collections/KategoriCollection',
], function(Backbone, _, google, MarkerClusterer, CafeMarkerView, utils) {
	var MapView = Backbone.View.extend({
		el : '#map-element',
		mapEvents : {},
		ready : false,
	
		initialize : function(options) {
			// Ett KategoriCollection-objekt:
			this.kategorier = options.kategorier;
			_.bindAll(this, 'render', 'panToUser', 'addMarker', 'fetchCafes', 'cron15min');
			this.once('map:idle', function() { this.ready = true; }, this);
			this.on('map:idle', function() { Backbone.trigger('mapview:map:idle'); });
			this.on('map:idle', this.fetchCafes);
			this.listenTo(this.kategorier, 'change:selected', this.fetchCafes);
			this.listenTo(this.collection, 'add', this.addMarker);
		},
		render : function() {
			this.map = new google.maps.Map(this.el, utils.mapOptions);
			this.markercluster = new MarkerClusterer(this.map, [], {gridSize : 40, minimumClusterSize : 3});
			this.bindMapEvents();
			this.listenTo(Backbone, 'cafelistitemview:mouseenter', this.animateMarker);
			this.listenTo(Backbone, 'cafelistitemview:mouseleave', this.animateMarkerStop);
			window.setInterval(this.cron15min, 60000);
			return this;
		},
		fetchCafes : function() {
			if (this.ready) {
				this.collection.fetch({ data : { bounds : this.map.getBounds().toUrlValue(), kategorier : this.kategorier.selected() }, merge : true, remove : false, sort : false });
			}
		},
		panTo : function(latlng) {
			this.map.panTo(latlng);
		},
		zoomIn : function(val) {
			if (this.map.getZoom() < val) {
				this.map.setZoom(val);
			}
		},
		/**
		 * Skapar en ny dopin.CafeMarkerView och ger denna en hänvisning till Modellen samt vice versa.
		 * Bunden till 'add' hos dopin.cafecollection (se initialize() ovan).
		 * Modellen är nu alltså kopplad till två Views (CafeMarkerView och CafeListItemView).
		 */
		addMarker : function(item) {
			var cafemarkerview = new CafeMarkerView({
				model : item,
			});
			cafemarkerview.mapview = this;
			this.listenTo(cafemarkerview, 'remove', function() {
				cafemarkerview.marker.setMap(null);
				this.markercluster.removeMarker(cafemarkerview.marker);
			});
			this.markercluster.addMarker(cafemarkerview.marker);
		},
		/**
		 * Triggas av CafeListItemView via Backbone-event
		 */
		animateMarker : function(cafeid) {
			model = this.collection.get(cafeid);
			if (model)
				model.set('highlight', true);
		},
		animateMarkerStop : function(cafeid) {
			model = this.collection.get(cafeid);
			if (model)
				model.set('highlight', false);
		},
		/**
		 * Läs om kaféerna på den aktuella kartvyn varje hel kvart.
		 * (Kaféer som är utritade, men ligger utanför den aktuella vyn, läses automatiskt om då kartan panoreras.)
		 */
		cron15min : function() {
			var d = new Date();
			if (d.getMinutes() % 15 === 0) {
				console.log(d.toString());
				this.fetchCafes();
			}
		},
		/**
		 * Delegerar alla Google Maps-events till View-events med namn 'map:<gmaps-eventnamn>'.
		 * Binder även explicit angivna lyssnare till Google Maps-events via this.mapEvents.
		 */
		bindMapEvents : function() {
			var mapEventNames = ['bounds_changed', 'center_changed', 'click', 'dblclick', 'drag', 'dragend', 'dragstart', 'heading_changed', 'idle', 'maptypeid_changed', 'mousemove', 'mouseout', 'mouseover', 'projection_changed', 'resize', 'rightclick', 'tilesloaded', 'tilt_changed', 'zoom_changed'];
			_.each(mapEventNames, function(mapEventName) {
				var handler = function() {
					this.trigger('map:'+mapEventName);
				};
				handler = _.bind(handler, this);
				google.maps.event.addListener(this.map, mapEventName, handler);
			}, this);
			_.each(this.mapEvents, function(handler, event) {
				handler = _.isString(handler) ? this[handler] : handler;
				if (_.isFunction(handler)) {
					handler = _.bind(handler, this);
					google.maps.event.addListener(this.map, event, handler);
				}
			}, this);
		},
	});
	return MapView;
});

