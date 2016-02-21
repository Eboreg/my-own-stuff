define([
	'backbone',
	'underscore'
], function(Backbone, _) {
	var Kategori = Backbone.Model.extend({
		initialize : function() {
			this.set('super', this.get('super') == 1);
			this.on('change:selected', function() {
				if (this.get('selected')) {
					Backbone.trigger('kategori:selected', this.id);
				}
				else {
					Backbone.trigger('kategori:unselected', this.id);
				}
			});
		},
		toJSON : function(options) {
			var ret = _.clone(this.attributes);
			if (this.get('subs')) {
				ret.subs = ret.subs.toJSON();
			}
			return ret;
		}
	});
	return Kategori;
});
