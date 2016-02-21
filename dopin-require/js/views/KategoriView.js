/**
 * Vill ha model : Kategori
 * Om det är en subkategori, vill den även ha superKat : Kategori
 */
define([
	'backbone',
	'jquery'
], function(Backbone, $) {
	var KategoriView = Backbone.View.extend({
		tagName : 'li',
		className : 'kat-item',
		events : {
			'click h3' : 'toggleSubs',
		},

		initialize : function() {
			_.bindAll(this, 'clicked', 'toggleSubs');
			this.listenTo(this.model, 'change', this.render);
			if (this.model.get('super')) {
				this.$el.addClass('kat-item-super');
			}
			else {
				this.$el.click(this.clicked);
				this.$el.addClass('kat-item-sub');
			}
		},
		render : function() {
			if (this.model.get('super')) {
				this.$el.html('<h3>'+this.model.get('namn')+'</h3>');
			}
			else {
				this.$el.text(this.model.get('namn'));
				if (this.model.get('selected')) {
					this.$el.addClass('kat-item-selected');
				}
				else {
					this.$el.removeClass('kat-item-selected');
				}
			}
			return this;
		},
		/**
		 * Kommer bara att köras på superkategori-views
		 */
		toggleSubs : function() {
			this.$('.kat-list-sub').toggle();
		},
		/**
		 * Kommer bara att köra på subkategori-views
		 */
		clicked : function() {
			if (!this.model.get('super')) {
				this.model.set('selected', !this.model.get('selected'));
			}
		},
	});
	return KategoriView;
});
