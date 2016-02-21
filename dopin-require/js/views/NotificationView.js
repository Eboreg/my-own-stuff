define([
	'backbone'
], function(Backbone) {
	var NotificationView = Backbone.View.extend({
		el : '#message-console',
		
		error : function(str) {
			var $errorBox = $('<div class="error-box"></div>');
			this.$el.append($errorBox);
			$errorBox.html('<p>'+str+'</p>');
			$errorBox.fadeIn("slow").delay(2000).fadeOut("slow");
		},
		info : function(str) {
			var $infoBox = $('<div class="info-box"></div>');
			this.$el.append($infoBox);
			$infoBox.html('<p>'+str+'</p>');
			$infoBox.fadeIn("slow").delay(2000).fadeOut("slow");
		},
	});
	return new NotificationView();
});
