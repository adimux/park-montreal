function init()
{
	var lat = 45.553349634491; var lng =-73.611958006357;
	var zoom = 17;

	var map = new L.map('map')

	var url='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
	var attrib='Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
	var lay = new L.TileLayer(url, {minZoom: 12, maxZoom: 20, attribution: attrib});		

	map.addLayer(lay);

	map.setView([lat,lng],zoom);


}

