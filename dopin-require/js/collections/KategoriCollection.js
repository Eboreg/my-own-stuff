define([
	'backbone',
	'underscore',
	'models/Kategori',
], function(Backbone, _, Kategori) {
	var KategoriCollection = Backbone.Collection.extend({
		model : Kategori,
		url : 'api/kategorier',
		
		initialize : function() {
			this.on('sync', this.removeHiddenKats, this);
			this.on('change:selected', this.triggerSelected, this);
		},
		removeHiddenKats : function() {
			this.set(this.reject(function(kat) { return kat.get('superkategoriid') == 125; }));
		},
		triggerSelected : function() {
			Backbone.trigger('kategorier:selected', this.selected());
		},
		selected : function() {
			return _.pluck(this.where({ selected : true }), 'id');
		},
	});
	return new KategoriCollection();
});
