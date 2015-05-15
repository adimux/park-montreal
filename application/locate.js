	L.Control.LocateUser = L.Control.extend(
	{
			options:{position:"topright",follow:true,
			circleStyle:
			{
				color: '#136AEC',
				fillColor: '#136AEC',
				fillOpacity: 0.15,
				weight: 2,
				opacity: 0.5
			},
			markerStyle:
			{
				color: '#136AEC',
				fillColor: '#2A93EE',
				fillOpacity: 0.7,
				weight: 2,
				opacity: 0.9,
				radius: 5
			}},

			initialize: function (options) {
				L.Map.addInitHook(function () {
					if (this.options.locateControl) {
						this.locateControl = L.control.locateUser();
						this.addControl(this.locateControl);
					}
				});

				for (var i in options) {
					if (typeof this.options[i] === 'object') {
						L.extend(this.options[i], options[i]);
					} else {
						this.options[i] = options[i];
					}
				}

			}	
			,onAdd : function(map)
			{
				var container = L.DomUtil.create('div','locate_control');
				var img = L.DomUtil.create('img','locate_me',container);
				img.src="images/locate_me.png";
				var selfCtrl =this;
				img.addEventListener('click', function()
					{
						$(selfCtrl.getContainer()).find(".locate_me").addClass("spinner");
						$(selfCtrl.getContainer()).addClass("pending");

						map.locate({setView:false});
					});

				this._layer = new L.LayerGroup();
				this._layer.addTo(map);
				this._event = undefined;

				map.on('locationfound',this._onLocationFound);

				return container;
			},
			marker_position:null,
			_activate: function() {
				if (this.options.setView) {
					this._locateOnNextLocationFound = true;
				}

				if(!this._active) {
					this._map.locate(this.options.locateOptions);
				}
				this._active = true;

				if (this.options.follow) {
					this._startFollowing(this._map);
				}
			},
			_deactivate: function() {
				this._map.stopLocate();

				this._map.off('dragstart', this._stopFollowing);
				if (this.options.follow && this._following) {
					this._stopFollowing(this._map);
				}
			},

			drawMarker : function(map)
			{
				var e= this._event;
				if( this.marker_position == null)
				{
					console.log(e.latlng);
					this.marker_position = L.marker(e.latlng).addTo(map);
				}
				else
				{
					this.marker_position.setLatLng(e.latlng);
					this;marker_position.update();
				}
				map.setView(e.latlng);
			}
			,
			_onLocationFound : function(e)
			{
				$(this.getContainer()).find(".locate_me").removeClass("spinner");
				$(this.getContainer()).removeClass("pending");

				this._event = e;
				console.log(this);
				this.drawMarker(this._map);/*
				if( this.marker_position == null)
				{
					console.log(e.latlng);
					this.marker_position = L.marker(e.latlng).addTo(map);
				}
				else
				{
					this.marker_position.setLatLng(e.latlng);
					this;marker_position.update();
				}
				map.setView(e.latlng);*/
			}
});

L.control.locateUser=function(opt)
{
	return new L.Control.LocateUser(opt);
}
