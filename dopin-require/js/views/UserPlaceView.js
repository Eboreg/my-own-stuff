/**
 * Properties:
 * 'model' : dopin.UserPlace()
 * 'mapview' : MapView-objektet
 * 'icon' : "Min position"-plupp ('shadow' är deprecated!)
 * Events:
 * 'moved' : när positionen ändrats (även när den första gången ritas ut)
 */
define([
	'backbone',
	'underscore',
	'google',
	'utils',
	'views/NotificationView',
	'jqueryui',
	'views/MapView',
	'models/UserPlace',
], function(Backbone, _, google, utils, notification) {
	var UserPlaceView = Backbone.View.extend({
		id : "set-position-overlay",
		infoWindowTemplate : _.template($("#userInfoWindowText").html()),
		manualTemplate : _.template($("#manualPositionOverlay").html()),
		markerEvents : {},
		
		initialize : function(options) {
			//notification.info("Hämtar din position...");
			this.mapview = options.mapview || {};
			this.listenTo(this.model, 'change:latlng', this.move);
			this.listenToOnce(this.model, 'error', this.showError);
			this.listenToOnce(this.model, 'error', this.renderManual);
			_.bindAll(this, 'moveToAddress', 'drop', 'gotoMyPositionClicked');
			if (!this.mapview.ready) {
				this.listenToOnce(this.mapview, 'map:idle', function() {
					this.renderMarker();
				}, this);
			} else {
				this.renderMarker();
			}
		},
		/**
		 * Skapar markören. Körs av this.move() om this.marker ej existerar än.
		 */
		renderMarker : function(options) {
			if (this.mapview.ready && this.model.get('latlng') instanceof google.maps.LatLng) {
				if (this.model.get('pan')) {
					this.mapview.panTo(this.model.get('latlng'));
				}
				if (this.model.get('zoom')) {
					this.mapview.zoomIn(utils.zoom);
				}
				this.marker = new google.maps.Marker({
					map : this.mapview.map,
					position : this.model.get('latlng'),
					draggable : true,
					icon : utils.markerImages.user,
					optimized : false,
					animation : google.maps.Animation.DROP
				});
				console.log('UserPlaceView : ny markör', this.marker);
				this.bindMarkerEvents();
				this.on('marker:click', this.showInfoWindow);
				this.on('marker:dragend', this.markerDragged);
				this.$el.html('<p style="cursor:pointer">Gå till min position</p>');
				this.$el.click(this.gotoMyPositionClicked);
				this.mapview.$el.append(this.el);
			}
			return this;
		},
		/**
		 * Skapar overlay för att ange sin egen position, om den ej lyckats hämtas.
		 */
		renderManual : function() {
			this.$el.html(this.manualTemplate({ markerImage : utils.markerImages.user.url }));
			this.mapview.$el.append(this.el);
			this.$("#user-marker-image").draggable({
				containment : this.mapview.$el,
				appendTo : this.mapview.$el
			});
			this.mapview.$el.droppable({
				accept : "#user-marker-image",
				drop : this.drop
			});
			var onAutocompleteSelect = function(event, ui) {
				this.moveToAddress(event, ui);
				this.$el.hide();
			};
			onAutocompleteSelect = _.bind(onAutocompleteSelect, this);
			this.$("#user-address-search-string").autocomplete({
				minLength : 3,
				source : 'api/addressSearch',
				select : onAutocompleteSelect
			});
		},
		/**
		 * Körs av jqueryui:s droppable-widget via this.renderManual(), när markör dragits o släppts på kartan
		 */
		drop : function(event, ui) {
			var x = ui.offset.left - this.mapview.$el.offset().left + 10;
			var y = ui.offset.top - this.mapview.$el.offset().top + 10;
			var overlay = new google.maps.OverlayView();
			overlay.draw = function() {};
            overlay.setMap(this.mapview.map);
			var pos = overlay.getProjection().fromContainerPixelToLatLng(new google.maps.Point(x, y));
			this.model.set({ latlng : pos, zoom : false, pan : false });
			this.$el.hide();
		},
		/**
		 * Bunden till modellens event change:latlng.
		 * Begär flytt av markören enligt koordinater från this.model. Skapar den först med this.render() om den ej existerar.
		 * Triggar annars eventet 'moved'.
		 */
		move : function() {
			console.log('UserPlaceView : move');
			if (!this.marker) {
				this.renderMarker();
			}
			else {
				this.marker.setPosition(this.model.get('latlng'));
				this.trigger('moved');
			}
		},
		/**
		 * Uppdaterar modellen (UserPlace) med markörens koordinater. Används då användaren själv dragit markören.
		 */
		markerDragged : function() {
			this.model.disableGeolocation();
			this.model.set({ latlng : this.marker.getPosition() });
		},
		/**
		 * Skapar infowindow om det ej finns redan. Öppnar detta. Binder autocomplete till RESTful /dopin/addressSearch?term=xxx 
		 */
		showInfoWindow : function() {
			if (!this.infowindow) {
				this.infowindow = new google.maps.InfoWindow({
					zIndex : 100,
					content : this.infoWindowTemplate(this.model.toJSON())
				});
				var onAutocompleteSelect = function(event, ui) {
					this.moveToAddress(event, ui);
					this.infowindow.close();
				};
				onAutocompleteSelect = _.bind(onAutocompleteSelect, this);
				var onDomReady = function() {
					console.log($("#user-address-search-string"));
					$("#user-info-window #user-address-search-string").autocomplete({
						minLength : 3,
						source : 'api/addressSearch',
						select : onAutocompleteSelect
					});
				};
				onDomReady = _.bind(onDomReady, this);
				google.maps.event.addListener(this.infowindow, 'domready', onDomReady);
			}
			this.infowindow.open(this.mapview.map, this.marker);
		},
		/**
		 * Flytta markör, panorera karta och stäng infowindow då användare angivit adress.
		 * Uppdaterar modellen (dopin.userplace) 
		 */	
		moveToAddress : function(event, ui) {
			var latlng = new google.maps.LatLng(ui.item.location.lat, ui.item.location.lng);
			this.model.set({latlng : latlng});
			this.model.disableGeolocation();
			this.mapview.panTo(latlng);
			this.mapview.zoomIn(utils.zoom);
		},
		gotoMyPositionClicked : function() {
			this.mapview.panTo(this.model.get('latlng'));
			this.mapview.zoomIn(utils.zoom);					
		},
		/**
		 * err = PositionError https://developer.mozilla.org/en-US/docs/Web/API/PositionError
		 */ 
		showError : function(err) {
			if (err && err.code) {
				switch (err.code) {
					case err.PERMISSION_DENIED:
						notification.error("Tilläts inte hämta position!");
						break;
					case err.POSITION_UNAVAILABLE:
						notification.error("Kunde inte avgöra din position!");
						break;
					case err.TIMEOUT:
						notification.error("Timeout vid hämtning av position!");
						break;
					default:
						notification.error("Okänt fel inträffade vid avläsning av din position.");
				}
			}
			else {
				notification.error("Okänt fel inträffade vid avläsning av din position.");
			}
		},

		/**
		 * Delegerar valda Google Maps-events till View-events med namn 'marker:<gmaps-eventnamn>'.
		 * Binder även explicit angivna lyssnare till Google Maps-events via this.markerEvents.
		 */
		bindMarkerEvents : function() {
			var markerEventNames = ['click', 'dblclick', 'dragend'];
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
	});
	return UserPlaceView;
});
