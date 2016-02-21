define([
	'backbone',
	'underscore',
	'views/KategoriView',
], function(Backbone, _, KategoriView) {
	var KategorierView = Backbone.View.extend({
		el : '#kat-list',
		
		initialize : function() {
			this.listenTo(this.collection, 'sync', this.render);
		},		
		render : function() {
			var katview, $subsEl;
			var superkats = this.collection.filter(function(kat) { return kat.get('super') == 1; });
			_.each(superkats, function(superkat) {
				katview = new KategoriView({ model : superkat });
				this.$el.append(katview.render().el);				
				var subkats = this.collection.filter(function(kat) { 
					return !kat.get('super') && kat.get('superkategoriid') == superkat.id; 
				});
				$subsEl = $('<ul class="kat-list-sub"></ul>');
				katview.$el.append($subsEl);
				_.each(subkats, function(subkat) {
					katview = new KategoriView({ model : subkat });
					$subsEl.append(katview.render().el);
				}, this);
			}, this);
			return this;
		},
	});
	return KategorierView;
});
