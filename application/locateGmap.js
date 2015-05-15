function locateControl(map,controlDiv,options)
{
	this.map = map;
	this.marker;
	this.accuracyCircle;
	this.watchId; 
	
	this.container = $(controlDiv);
	this.container.index=1;
	this.container = $(this.container);
	this.container.addClass('locate_control');
	
	this.img = jQuery('<img class="locate_me" alt="locate me"/>').appendTo(this.container);
	this.img.attr('src',"images/locate_me.png");

	var self =this;
	this.img.click( function(){self.onClick()} );
	this.onClick = function()
	{

		if( ! this.isWatching() )
		{
			this.beginAnimation();
			this.beginWatching();
		}
		else
			this.stopWatching();
	}
	this.beginAnimation=function()
	{
		$(this.container).find(".locate_me").addClass("spinner");
		$(this.container).addClass("pending");
	}
	this.stopAnimation=function()
	{
		$(this.container).find(".locate_me").removeClass("spinner");
		$(this.container).removeClass("pending");
	}

//	this.map.controls[google.maps.ControlPosition.TOP_RIGHT].push(this.container.get() );
this.handleNoGeolocation = function()
{
	self.stopAnimation();
	alert("Il y a un petit problème, on ne peut pas vous géolocaliser dans la map...");
}
this.isWatching = function()
{
	return typeof this.watchId != "undefined";
}
this.stopWatching = function()
{
	$(this.container).removeClass("watching");
	$(this.container).find("img").attr("src","images/locate_me.png");

	if( typeof this.watchId != "undefined")
	navigator.geolocation.clearWatch(this.watchId);
	this.watchId = undefined;
	this.hideMarker();
}
this.beginWatching = function ()
{
	if(navigator.geolocation) {
		var func = function(){var firstTime=true; return function(position)
			{
				var pos = new google.maps.LatLng(position.coords.latitude,
				position.coords.longitude);
				var acc = position.coords.accuracy;

				if(firstTime)
					self.stopAnimation();

				self.drawMarker(pos,acc);

				if( firstTime )
				{
					self.map.setCenter(pos);
					if( self.map.getZoom() < 16)
					self.map.setZoom(16);
				}

				if( firstTime )
					firstTime=false;
			}};
	
		$(this.container).addClass("watching");
		$(this.container).find("img").attr("src","images/locate_me_watching.png");
		
		this.watchId = navigator.geolocation.watchPosition(func(), null, {timeout:0,enableHighAccuracy:true,maximumAge:Infinity});
		navigator.geolocation.getCurrentPosition(function(position) {
			var pos = new google.maps.LatLng(position.coords.latitude,
				position.coords.longitude);

//			self.stopAnimation();
//			self.drawMarker();

			this.map.setCenter(pos);
		}, function() {
			self.handleNoGeolocation(true);
		});

	} else {
		self.stopAnimation();
		self.handleNoGeolocation(false);
	}

}
this.hideMarker = function()
{
this.marker.setMap(null);
	this.accuracyCircle.setMap(null);
	this.marker = undefined;
	this.accuracyCircle = undefined;
}
this.drawMarker = function(position,accuracy)
{
	console.log("draw marker");
	var circleOptions={
		clickable:false,
		strokeColor:"#136AEC",
		strokeOpacity:0.9,
		strokeWeight:2,
		fillColor:"#2A93EE",
		fillOpacity:0.7,
		map :this.map,
		visible:true,
		center:position,
		radius:0.5,
		optimized:false
	}
	var accCircleOptions={
		strokeColor:"#136AEC",
		strokeOpacity:0.5,
		strokeWeight:2,
		fillColor:"#136AEC",
		fillOpacity:0.15,
		map :this.map,
		visible:true,
		clickable:false,
		center:position,
		radius:accuracy
	}
	if(typeof this.marker == "undefined")
		this.marker =new google.maps.Circle(circleOptions);
	if( typeof this.accuracyCircle=="undefined")
		this.accuracyCircle = new google.maps.Circle(accCircleOptions);

	this.marker.setVisible(true);
	this.accuracyCircle.setVisible(true);
	this.updateMarkerRadius();

	google.maps.event.addListener(this.map,"zoom_changed",this.updateMarkerRadius);

	
	/*		var infowindow = new google.maps.InfoWindow({
			map: this.map,
			position: position,
			content: 'Vous êtes ici !'
		});*/

}
this.updateMarkerRadius=function()
{
	if(typeof self.marker == "undefined")
		return;
	radius_marker = 1;

	if( self.map.getZoom() < 18)
		radius_marker = radius_marker^((self.map.getZoom()-18)*4);

	self.marker.setRadius(radius_marker);

}
}
