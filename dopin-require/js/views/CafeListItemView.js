/**
 * Model: Cafe
 * Events:
 * 	Backbone.'cafelistitemview:mouseenter' : När mus hovrar över raden
 * 	Backbone.'cafelistitemview:mouseleave' : Ja, gissa
 */
define([
	'backbone',
	'underscore',
	'jquery'
], function(Backbone, _, $) {
	var CafeListItemView = Backbone.View.extend({
		tagName : 'li',
		className : 'cafe-list-item',
		template : _.template($("#listItemTemplate").html()),
		events : {
			'mouseenter' : 'mouseenter',
			'mouseleave' : 'mouseleave',
			'click' : 'click'
		},
		
		initialize : function() {
			this.listenTo(this.model, 'change', this.render);
		},
		render : function() {
			// Om modellen ej längre har egenskapen 'distance', innebär det att den fallit ur cafélistan och ska ej renderas.
			if (!this.model.get('distance')) {
				this.remove();
			}
			else {
				this.$el.html(this.template(this.model.toJSON()));
				return this;
			}
		},
		mouseenter : function() {
			console.log("mouseenter ", this.model.id, this.model.get('namn'));
			Backbone.trigger('cafelistitemview:mouseenter', this.model.id);
		},
		mouseleave : function() {
			Backbone.trigger('cafelistitemview:mouseleave', this.model.id);
		},
		click : function() {
			//dopin.router.navigate('cafe/' + this.model.get('slug'), { trigger : true });
		},
		select : function() {
			this.$("h3").addClass('selected');
		},
		unselect : function() {
			this.$("h3").removeClass('selected');
		}
	});
	return CafeListItemView;
});
