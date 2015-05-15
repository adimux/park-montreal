
function LSvar(name, defaultVal)
{
	var item = localStorage.getItem(name);
	if(item == null )
		localStorage.setItem(name,JSON.stringify(defaultVal) );
	return function (val)
	{
		if( typeof val =="undefined")
		{
			return JSON.parse(localStorage.getItem(name));
		}
		else
		{
			localStorage.setItem(name, JSON.stringify( val) );
		}
	}
}

var map;
var plotlayers;
var layer_troncons; // Les tronçons 
var groupe_poteaux=[]; // sont groupés en fonction de l'id du tronçon
var troncons=[];
var maxzoom;
var maxzoom_poteaux;
var infoWindow=null;

var date_selected_txt = LSvar("date_selected", null);


console.log(date_selected_txt());
var date_selected = date_selected_txt() != null ? moment(date_selected_txt().replace('Z','')) : null;
if( !moment.isMoment( date_selected ) )
date_selected = moment();



var montrer_stationnements=new LSvar("montrer_stats",true); // Montrer les places de stationnement (par défaut true)
var montrer_arrets=new LSvar("montrer_arrets",false); // Montrer seulement les places où on peut arrêter la voiture (par défaut false)

var id_trc_poteaux_shown=null;
var self = this;
function detectBrowser() {
	var useragent = navigator.userAgent;
	var mapdiv = document.getElementById("map");

	if (useragent.indexOf('iPhone') != -1 || useragent.indexOf('Android') != -1 ) {
		mapdiv.style.width = '100%';
		mapdiv.style.height = '100%';
	} else {
		mapdiv.style.width = '600px';
		mapdiv.style.height = '800px';
	}
}
function momentToISOStringLocal(date)
{
	var corrige_date = moment( date );
	corrige_date.add( date.utcOffset(), "minutes" );
	return corrige_date.toISOString();
}
function momentFromISOStringLocal(date_txt)
{
	var date = moment(date_txt);
	date.add(-moment().utcOffset(),"minutes" );
return date;	
}
function setDate(date, now)
{
	if(typeof now =="undefined")
		now=false;
	var format = "M/D/YYYY H:m:ss";
	if( typeof intervalDate == "undefined")
		intervalDate=null;

	if(now==false)
	{
		date_selected = date;
		date_selected_txt(date_selected.toISOString());

		clearInterval(intervalDate);
		this.watching=false;
		document.getElementById("dtPicker").value=momentToISOStringLocal(date_selected).replace('Z','');
		updateFeatures();
	}
	else
	{
		var se=this;
		document.getElementById("dtPicker").value=momentToISOStringLocal(moment() ).replace('Z','');
		if(intervalDate != null)
			clearInterval(intervalDate);
		intervalDate = setInterval( function()
				{
					date_selected = moment();
					date_selected_txt(date_selected.toISOString() );

				document.getElementById("dtPicker").value=momentToISOStringLocal(date_selected).replace('Z','');

				}, 1000);
	}
}
function initReglages(div)
{
/*	console.log("hey kick");
	$(div).find('#timePicker').datetimepicker(
			{
				format: 'yyyy/MM/dd hh:mm:ss'
			}
			);
	$(div).find('#datPicker').datetimepicker(
			{
				format: 'yyyy/MM/dd hh:mm:ss'
			}
			);*/
	initButtons($(div).find("#choix_affichage"));

	setDate(date_selected, true); //watch date

	$(div).find("#dtPicker").on("click", function() {
		if(intervalDate != null)
		clearInterval(intervalDate);

		setDate( moment($(this).val()) );
	});
	$(div).find("#mnt").click(function(){setDate(null,true)});
	$(div).find("#in_quarter").click(function(){
		var d = moment();
		d.add('15','minutes');
		setDate(d)
	});
	$(div).find("#in_fourty").click(function(){
		var d = moment();
		d.add('40','minutes');
		setDate(d)
	});
	$(div).find("#in_1h20").click(function(){
		var d = moment();
		d.add('80','minutes');
		setDate(d)
	});

	/*{
		datepicker:false,
		format:'H:i'
	});*/
//	$(div).find('#datePicker').datetimepicker(

}

function initButtons(div)
{
	if( montrer_stationnements() )
		$(div).find("#stats").addClass("active");
	else if( montrer_arrets() )
		$(div).find("#arrets").addClass("active");
	else 
		$(div).find("#rien").addClass("active");

	$(div).find("button").click(function()
			{
				console.log("click");
				var button = this;
				$(div).find("button").each(function()
				{
					if(this != button)
						$(this).removeClass("active");
				});
				montrer_stationnements(false);
				montrer_arrets(false);

				if( $(button).attr("id") == "stats")
					montrer_stationnements ( !$(button).hasClass("active") );
				if( $(button).attr("id") == "arrets")
					montrer_arrets ( !$(button).hasClass("active") );

				if( !$(button).hasClass("active"))
					$(button).addClass("active");
				else
					$(button).removeClass("active");



				updateFeatures();
			});
}
function init()
{

	var lat = 45.553349634491; var lng =-73.611958006357;
	var zoom = 18;
	maxzoom =20;
maxzoom_poteaux = 17;
	
var styleArray = {};

var b = localStorage.getItem("bounds");
try{
var b = JSON.parse(b);
}catch(e)
{
//	alert(e)
	b = array();
}

console.log("LS bounds : "+b);

	var mapOptions = { zoom : zoom, styles:styleArray, disableDefaultUI:true, zoomControl:true, };

	map = new google.maps.Map(document.getElementById("map"), mapOptions);
	loadMapState(); // load last  position and zoom from LS
	//map.setCenter({lat:lat,lng:lng});

	/*map = new L.map('map');

	var url= "http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
	var url =	"http://otile1.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.jpg";

	var mapID = "mapbox.streets";
	var url ="http://{s}.tiles.maapbox.com/v3/"+mapID+"/997/256/{z}/{x}/{y}.png";
	var url = 'http://api.tiles.mapbox.com/v4/'+mapID+'/{z}/{x}/{y}@2x.png?access_token=sk.eyJ1IjoiYWRpbXV4IiwiYSI6IkJ3QzRib2cifQ.lOORWDpgkfkpn2o8y9FU0A';

	var attrib='mapbox';
	
	var osm = new L.TileLayer(url, {minZoom: 12, maxZoom: maxzoom,attribution:attrib});		

	map.addLayer(osm);
*/
	map.setCenter({lat:lat,lng:lng},zoom);

//var GeoMarker = new GeolocationMarker(map);
//
	initReglages( $("#reglages") );
	$("#button_reglages").click(function(){showWall("#reglages");  });

	addControls();
	google.maps.event.addListener(map,"bounds_changed",updateFeatures);



//	loadGeojson("http://adam.cherti.name/CREE%20TA%20VILLE/GEOBASE/sens_circ.json");

	//map.on('moveend',onMapMove);
	
	$("#close_info").click(closeWall);
	$("#info").click(function(e)
			{
				
				if(! $("#info .wall").is(':hover') )
				{
				closeWall();
				}
			});

//	var lc = L.control.locate().addTo(map);
}

function usingPhone()
{
return ( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) );
}
function addControls()
{
	
	// User Position control
	var controlDiv = document.createElement('div');
	controlDiv.index=1.
	var ctrl = new locateControl(map,controlDiv);
	if( !usingPhone() )
	map.controls[google.maps.ControlPosition.TOP_RIGHT].push(controlDiv);
	else
	{
map.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(controlDiv);

	}

/*	// Contrôle de l'affichage des features
	var html='<div class="btn-group" role="group" aria-label="..." id="choix_affichage"><button type="button" id="stats" class="btn btn-default"><img src="images/parking_forbidden.jpg"/></button><button type="button" id="arrets" class="btn btn-default"><img src="images/stop_forbidden.png"/></button><button type="button" id="rien" class="btn btn-default" >Rien</button></div>';
	var controlFeat = document.createElement('div');
//	controlFeat.class="controls";
	controlFeat.index=1;
	$(controlFeat).html(html);
	initButtons($(controlFeat));
	map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(controlFeat);
*/

	// Search box
	var search_div = document.getElementById('search_div');
	var input = document.getElementById('pac-input');;
	map.controls[google.maps.ControlPosition.TOP_CENTER].push(search_div);
	var searchBox = new google.maps.places.SearchBox(input);
	
	var markers = [];
	google.maps.event.addListener(searchBox, 'places_changed',
		function() {
		var places = searchBox.getPlaces();

		if (places.length == 0) {
			return;
		}
		for (var i = 0, marker; marker = markers[i]; i++) {
			marker.setMap(null);
		}

		// For each place, get the icon, place name, and location.
		markers = [];

		var bounds = new google.maps.LatLngBounds();
//	    for (var i = 0, place; place = places[i]; i++)
//	{
			place = places[i];
			var image = {
			    url: place.icon,
		        size: new google.maps.Size(71, 71),
		        origin: new google.maps.Point(0, 0),
		        anchor: new google.maps.Point(17, 34),
		        scaledSize: new google.maps.Size(25, 25)
		      };
			// Create a marker for each place.
			var marker = new google.maps.Marker({
				map: map,
				icon: image,
				title: place.name,
				position: place.geometry.location
			});
			markers.push(marker);

			bounds.extend(place.geometry.location);
		//map.fitBounds(bounds);
			map.setCenter(place.geometry.location);
			console.log(map.getZoom());
			console.log(maxzoom_poteaux);
//			map.setZoom((map.getZoom() > maxzoom_poteaux ? map.getZoom() : maxzoom_poteaux) );
			map.setZoom(maxzoom_poteaux);
		});
	 // Bias the SearchBox results towards places that are within the bounds of the
	 //   // current map's viewport.
	google.maps.event.addListener(map, 'bounds_changed', function() {
			saveMapState();
			updateFeatures();
			var bounds = map.getBounds();
			searchBox.setBounds(bounds);
		  });
}

function saveMapState()
{
	localStorage.setItem('zoom',map.getZoom());
	localStorage.setItem("lat",map.getCenter().lat());
	localStorage.setItem("lng",map.getCenter().lng());
//	alert(localStorage.getItem("lat")+","+ localStorage.getItem("lng") + " "+localStorage.getItem("zoom"));
}
function loadMapState()
{
	var lat = parseFloat( localStorage.getItem("lat") );
    var lng = parseFloat( localStorage.getItem("lng") );
    var zoom = parseFloat( localStorage.getItem("zoom")) ;
	if(lat!=null && lng!=null && zoom!=null && !isNaN(zoom) )
	{
	map.setCenter({lat:lat,lng:lng});
	map.setZoom(zoom);
	}
}
function showWall(selector)
{
	$("#info .wall").children().hide();

	$("#info").show();
	$("#info .wall "+selector).show();
}
function addToWall(htm)
{
	$("#info .wall .content").html(htm);
}

function closeWall()
{
$("#info").hide();
}

function loadGeojson(request)
{

	$.getJSON(request,function(data)
			{
				L.geoJson(data).addTo(map);});

}
var marker_position=null;

function onLocationFound(e)
{
//	if(marker_position !=null)
//		map.removeLayer(marker_position);
	
	if( marker_position == null)
	{
//		console.log(e.latlng);
		marker_position = L.marker(e.latlng).addTo(map);
	}
	else
	{
		marker_position.setLatLng(e.latlng);
		marker_position.update();
	}
	map.setView(e.latlng);
}
var finishUpdatingTronc = true;
var finishUpdatingPot = true;

function updateFeatures()
{
	if( finishUpdatingTronc == false || finishUpdatingPot == false)
		return;

	finishUpdatingPot=false;
	finishUpdatingTronc=false;

	if( map.getZoom() < maxzoom_poteaux )
	{
		removeTronc();
		removeGroupePots();
		finishUpdatingPot=true;
		finishUpdatingTronc=true;

		return;
	}

	if( !montrer_stationnements() && !montrer_arrets() )
	{
		removeTronc();
		removeGroupePots();
		finishUpdatingPot=true;
		finishUpdatingTronc=true;
		return;
	
	}

	var bounds = map.getBounds();
	var ne = bounds.getNorthEast();
	var sw = bounds.getSouthWest();

	var txt_bounds= 'lat_nwest='+ne.lat()+'&lng_nwest='+ne.lng()+'&lat_seast='+sw.lat()+'&lng_seast='+sw.lng();

	var request='http://adam.cherti.name/CREE TA VILLE/get/troncons.php?'+txt_bounds;
	
	// charger troncons
	$.getJSON(request,function(data)
			{
				if( typeof data.Error !="undefined" )
				{
					alert(data.Error);	
					return;
				}
				removeTronc();
				data =data.features;
				for(i in data)
				{
					var obj = data[i];
					map.data.addGeoJson(obj);
				}
				finishUpdatingTronc =true;
			});
	var date = date_selected || moment();
	var addzero = function(i) {
		    if (i < 10) {
				        i = "0" + i;
						    }
			    return i;
	}
	var datetxt = date.year()+"/"+(date.month()+1)+"/"+date.date()+"_"+date.hours()+":"+addzero(date.minutes())+":"+addzero(date.seconds());
// format  : "Y/m/d_H:i:s"
//	alert(timestamp);
	request = 'http://adam.cherti.name/CREE TA VILLE/get/poteaux.php?'+txt_bounds+'&date='+datetxt;

	// charger poteaux
	$.getJSON(request, function(data)
			{
				if( typeof data.Error !="undefined")
				{
					alert(data.Error);	
					return;
				}
			removeGroupePots();
				data =data.features;
				for(i in data)
				{
					var obj = data[i];
					map.data.addGeoJson(obj);
				}
				console.log("finito2");
				finishUpdatingPot =true;

				console.log(id_trc_poteaux_shown);
				if( id_trc_poteaux_shown != null)
				{
					if( !showGroupPots(id_trc_poteaux_shown) )
						id_trc_poteaux_shown =null;
				}


			});
	
	// style des features : poteaux / tronçons
	map.data.setStyle(function(feature)
			{
			if( feature.getProperty("POTEAU_ID_POT") ) // Donc c'est un poteau
			{
				var trc_id = feature.getProperty("TRC_ID");
				addPot(trc_id, feature);

				var url='';
				var resol="30x30";
				if( usingPhone() )
					resol = "20x20";


				if( montrer_stationnements() )
				{
//					alert(montrer_stationnements() );
					if( feature.getProperty("stat_autorise")==true)
					url ='images/parking_permitted_'+resol+'.jpg';
					else
					url='images/parking_forbidden_'+resol+'.jpg';
				}
				else if( montrer_arrets() )
				{

					if( feature.getProperty("arret_autorise")==true)
					url ='images/stop_permitted_'+resol+'.png';
					else
					url='images/stop_forbidden_'+resol+'.png';

				}
				var popup = document.createElement('div');
				var html = "<p><img src='"+url+" '/>";
				if( montrer_stationnements() )
				{
					if(feature.getProperty("stat_autorise") )
						html += "Stationnement autorisé pour le moment";
					else
						html += "Stationnement interdit pour le moment";
				}
				else if(montrer_arrets() )
				{
					if(feature.getProperty("arret_autorise") )
						html += "Arrêt autorisé pour le moment";
					else
						html+= "Arrêt interdit pour le moment";
				}

//				feature.setProperty("infoWindow", new google.maps.InfoWindow({content:$(popup).html()}));



				var image = {url:url, size:new google.maps.Size(30,30)};

				return {'visible':true,
				icon:image
				};
			}
			else if( feature.getProperty("ID_TRC") )  // C'est un tronçon
			{
				addTronc(feature);
					return{strokeColor:'orange',
						clickable:true,
					  strokeWeight:'15',
					strokeOpacity:0.2}

			}

			});
	map.data.addListener('mousedown',function(e)
			{
//				stopPropagation(e);
//e.preventDefault();
				if( e.feature.getProperty("POTEAU_ID_POT") )
				{
				var feature =e.feature;
				var url='';
				var resol="30x30";
				if( usingPhone() )
					resol = "20x20";


				if( montrer_stationnements() )
				{
//					alert(montrer_stationnements() );
					if( feature.getProperty("stat_autorise")==true)
					url ='images/parking_permitted_'+resol+'.jpg';
					else
					url='images/parking_forbidden_'+resol+'.jpg';
				}
				else if( montrer_arrets() )
				{

					if( feature.getProperty("arret_autorise")==true)
					url ='images/stop_permitted_'+resol+'.png';
					else
					url='images/stop_forbidden_'+resol+'.png';

				}
				var popup = document.createElement('div');

				var html = "<div class='infWin'> <p><img src='"+url+" '/><br/>";
				if( montrer_stationnements() )
				{
					if(feature.getProperty("stat_autorise") )
						html += "Stationnement autorisé pour le moment";
					else
						html += "Stationnement interdit pour le moment";

					html += "<br/>";

					var period = feature.getProperty("next_period_stat_interdit");
					var start = "";
					var end ="";
					//console.log("HJEY PERiOD ");
					//console.log(feature.getProperty("POTEAU_ID_POT"));
					//console.log(feature.getProperty("next_period_stat_interdit"));
					if(  period.start )
						start = moment( period.start, "YYYY/MM/DD HH:mm:ss" );
					if(  period.end )
						end = moment(period.end, "YYYY/MM/DD HH:mm:ss");
					if( moment.isMoment(start) && moment.isMoment(end))
					{
						if( feature.getProperty("stat_autorise") )
						{
							var duration =moment.duration(moment().diff(start));
							var dur = duration.humanize();
							var dur2 = moment.duration(start.diff(end)).humanize();
							console.log(dur2);
							html += "Prochaine Période d'interdiction dans <span style='color :red'>"+dur+'</style><br/>';
							html+= "( "+period.start+" ) <br/>"
								html += 'Et durera : '+ dur2;
						}
						else 
						{
							var dur = moment.duration(moment().diff(end)).humanize();
							html += "Sera permis dans <span style='color:green'>"+dur+'</span>';
						}
					}
					else
						html += "Toujours interdit";

				}
				else if(montrer_arrets() )
				{
					if(feature.getProperty("arret_autorise") )
						html += "Arrêt autorisé pour le moment";
					else
						html+= "Arrêt interdit pour le moment";
					html+="<br/>";
				}
				html += "<br/><a href='#'  id='more'>Voir les panneaux</a></p>";
				html += '</div>';
				$(popup).html(html);
				var addevent = function(thefeature)
				{

				$(document).on('click',"#more",
					function(event)
					{
							stopPropagation(event);

						showPoteauInfos(thefeature);
						});
				};
				addevent(feature);
var pos ={lat:feature.getGeometry().ga.lat(), lng:feature.getGeometry().ga.lng()}
				if( infoWindow != null )
					infoWindow.close();
				infoWindow= new google.maps.InfoWindow({content:$(popup).html(),
				position:pos});
				infoWindow.open(map);

/*				google.maps.event.addDomListener($(popup).find("#infWin"),'click',
					   function(e)
					   {
						   console.long("dom list");
						if(e.stopPropagation)
							e.stopPropagation();
					   }	   )

*/
				}

			});
}
function stopPropagation(myEvent){ 
	        if(!myEvent){ 
				                myEvent=window.event; 
								        } 
			        myEvent.cancelBubble=true; 
					        if(myEvent.stopPropagation){ 
								                myEvent.stopPropagation(); 
												        } 
}
function showPoteauInfos(feature)
{
	console.log("oh yeah");
				if( feature.getProperty("POTEAU_ID_POT") ) // Donc c'est un poteau
				{
					var poteau = feature;
//					console.log(poteau);

//					map.setCenter(poteau.getGeometry().get());
					var pans = poteau.getProperty("panneaux");
					var html ='ID : '+poteau.getProperty("POTEAU_ID_POT")+' hidden '+poteau.getProperty("hidden")+'<br/><br/>';
					html += '<center>';
					for(var i=0;i<pans.length;i++)
					{
					var description = pans[i].DESCRIPTION_RPA.replace("\\P", "Stationnement interdit<br/>");
					description = description.replace("\\A", "Arrêt interdit<br/>");
					if(description.charAt(0)=="P" && description.charAt(1) == " ")description="Limitation de stationnement<br/>"+description.substr(2);

						console.log(pans[i].image);
						var img = pans[i].image;
						html+= "<img class='panneau' src='"+img+"'/><br/>"+description+"<br/>"
				//		html+="CODE : "+pans[i].CODE_RPA+"<br/>";
//						if(pans[i].temps_max > 0)
//							html += "Temps max d'arrêt/stationnement : "+pans[i].temps_max;
						html += "<br/><div style='clear:both'></div>";
					}
					html+='<a href="signaler.php?id='+feature.getProperty("POTEAU_ID_POT")+'">Un panneau manque ?</a></center>';
					addToWall(html);
					showWall(".content");
				}
}
function showPoteaux(troncon, fitbounds)
{
	if( typeof fitbounds == "undefined")
		fitbounds=false;
/*	var b = troncon.getProperty("bounds");
	// Si on ne voit pas le tronçon complet, on change la pos
		if(! map.getBounds().contains(b) )
		{
			if( fitbounds)
			{
				map.fitBounds(b);
				return;
			}
		}*/

		var id = parseInt(troncon.getProperty("ID_TRC"));


		hideAllGroupPots();
		showGroupPots(id);

		id_trc_poteaux_shown = id;
	}

function addTronc(tronc)
{
troncons.push(tronc);
}
function addPot(id_trc, pot)
{
	var grp = getGroupePots(id_trc);

	if( grp !== null)
	{
		grp.push(pot);
	}
	else
	{
		addGroupePots(id_trc, [pot]);
	}
}
function getGroupePots(id_trc)
{
	var found = null;

	if( typeof groupe_poteaux[id_trc] !== "undefined")
	{
		found = groupe_poteaux[id_trc];
	}
	return found;
}
function addGroupePots(id_trc, poteaux)
{
	if( getGroupePots(id_trc) == null)
	{
		groupe_poteaux[id_trc] = poteaux;
	}
	else
		alert("already exists "+id_trc);
}


function removeFeatures()
{
	removeTronc();
	removeGroupePots();
}
// Supprime les tronçons affichés
function removeTronc()
{
	for(var i=0; i < troncons.length; i++)
	{
		map.data.remove(troncons[i]);
	}
	troncons=[];
}
// Supprime les poteaux affichés
function removeGroupePots()
{
	for(var id_trc in groupe_poteaux)
	{
		var grp = groupe_poteaux[id_trc];
		for(var i =0; i< grp.length;i++)
			map.data.remove(grp[i]);
	}
	groupe_poteaux=[];

}
function showGroupPots(id_trc)
{
	var grp =getGroupePots(id_trc);
	if( grp !== null )
	{
		for(var i =0; i < grp.length; i++)
		{

			map.data.overrideStyle(grp[i], {visible:true});
		}
		return true;
	}
	else return false;
}
function hideAllGroupPots()
{
	for(id_trc in  groupe_poteaux)
	{
		var grp = groupe_poteaux[id_trc];
		for(var i =0; i< grp.length;i++)
			map.data.overrideStyle(grp[i], {visible:false});
	}

}



function onMapMove()
{
	console.log("map move");
	updateFeatures();
}
