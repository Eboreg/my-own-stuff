/**
 * Ritar ut både listan och dess items.
 * this.render() körs varje gång dopin.cafecollection avfyrat eventet 'sort', vilket alltså gör så att listan ritas om även
 * då kaféerna av någon anledning bara sorterats om.
 */
define([
	'backbone',
	'underscore',
	'google',
	'collections/CafeCollection',
	'views/CafeListItemView'
], function(Backbone, _, google, CafeCollection, CafeListItemView) {
	var CafeListView = Backbone.View.extend({
		el : '#cafe-list',
		
		initialize : function() {
			this.collection = new CafeCollection();
			this.selectedKats = new Array();
			this.latlng = {};
			this.listenTo(Backbone, 'userplace:change', this.fetchCafes);
			this.listenTo(this.collection, 'sort', this.render);
			this.listenTo(Backbone, 'cafemarkerview:mouseover', this.highlight);
			this.listenTo(Backbone, 'cafemarkerview:mouseout', this.highlightStop);
			this.listenTo(Backbone, 'kategorier:selected', this.setSelectedKats);
		},
		render : function() {
			var i = 0;
			this.$el.html("");
			this.collection.each(function(item) {
				if (++i <= 30)
					this.renderItem(item);
			}, this);
		},
		highlight : function(cafeid) {
			var model = this.collection.get(cafeid);
			if (model)
				model.set('highlight', true);
		},
		highlightStop : function(cafeid) {
			var model = this.collection.get(cafeid);
			if (model)
				model.set('highlight', false);
		},
		setSelectedKats : function(kats) {
			this.selectedKats = kats;
			this.fetchCafes();
		},
		fetchCafes : function(latlng) {
			this.latlng = latlng || this.latlng;
			this.collection.fetch({ data : { pos : this.latlng.toUrlValue(), kategorier : this.selectedKats }, merge : true, remove : true });
		},	
		renderItem : function(item) {
			var itemView = new CafeListItemView({
				model : item
			});
			this.$el.append(itemView.render().$el);
		},
	});
	return new CafeListView();
});
