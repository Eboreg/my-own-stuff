define([
	'backbone',
	'utils'
], function(Backbone, utils) {
	var CafeView = Backbone.View.extend({
		el : '#cafe-element',
		template : _.template($("#cafeElement").html()),
		
		initialize : function() {
			this.$el.html('');
			this.listenTo(this.model, 'sync', this.render);
		},
		
		render : function() {
			this.$el.html(this.template(_.extend(this.model.toJSON(), utils)));
//			this.$el.append(JSON.stringify(this.model.toJSON()));
			return this;
		},
	});
	return CafeView;
});
