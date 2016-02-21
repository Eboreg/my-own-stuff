define(['google'], function(google) {
	return {
		urlroot : '/dopin-require/',
		zoom : 15,
		veckodagar : Array('Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'),
		markerImages : {
			user : {
				url : 'img/my_position.png',
				size : new google.maps.Size(20, 20),
				anchor : new google.maps.Point(10, 10)
			},
			cafe : {
				oppet : [{
					url : 'img/koppar/1traff-oppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/2traff-oppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/3traff-oppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/4traff-oppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/5traff-oppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}],
				okantoppet : [{
					url : 'img/koppar/1traff-okantoppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/2traff-okantoppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/3traff-okantoppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/4traff-okantoppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/5traff-okantoppet.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}],
				stangt : [{
					url : 'img/koppar/1traff-stangt.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/2traff-stangt.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/3traff-stangt.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/4traff-stangt.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}, {
					url : 'img/koppar/5traff-stangt.png',
					scaledSize : new google.maps.Size(20, 20),
					anchor : new google.maps.Point(10, 10)
				}]
			}
		},
	
		mapOptions : {
			center : new google.maps.LatLng(63, 14),
			zoom : 5,
			mapTypeId : google.maps.MapTypeId.ROADMAP
		},
	
		metersToString : function(m) {
			if (m >= 1000) {
				var km = m / 1000;
				if (km > 100)
					var patt = /^\d*/;
				else
					var patt = /\d*\.\d{1}/;
				km = km.toString();
				var match = km.match(patt);
				if (match !== null)
					km = match[0];
				return km.replace('.', ',') + ' km';
			} else
				return parseInt(m).toFixed() + ' m';
		},
	
		distanceFromPos : function(lat1, lng1, lat2, lng2) {
			var deg2rad = function(deg) {
				return deg * (Math.PI / 180);
			};
			var R = 6371;
			// Radius of the earth in km
			var dLat = deg2rad(lat2 - lat1);
			var dLng = deg2rad(lng2 - lng1);
			var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
			var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
			var d = R * c;
			// Distance in km
			//return d;
			return d * 1000;
			// Avstånd i m :-)
		},
	};
});
