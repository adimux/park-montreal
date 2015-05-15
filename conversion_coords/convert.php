<?php
/**
 * This script converts a geojson file or a kml file (detects with
 * the extension.. i know it sucks) with mtm /utm coordinates to
 * lat/lng wgs84 coordinates system
 *
 * How to use this script :
 *
 * From UTM to WGS84
 * php convert.php filepath UTM_[Zone Number]_[Hemisphere] (sc|)
 *
 * From MTM to WGS84
 * php convert.php filepath MTM_[Zone Number](_[Hemisphere]) (sc|)
 *
 * From WGS84 to WGS84
 * php convert.php filepath (wgs84|) (sc|)
 *
 * Hemisphere : 1 for north, -1 for south. For MTM, hemisphere is by default 1, so it
 *
 * @author Adimux <adam@cherti.name>
 * @version 0.1
 * @copyright (C) 2015 Adimux <adam@cherti.name>
 * @license MIT
 */

// This script converts a geojson file or a kml file (detects with
// the extension.. i know it sucks) with mtm /utm coordinates to
// lat/lng wgs84 coordinates system
//
// How to use this script :
// From UTM to WGS84
// php convert.php filepath UTM_[Zone Number]_[Hemisphere] (sc|)
// From MTM to WGS84
// php convert.php filepath MTM_[Zone Number](_[Hemisphere]) (sc|)
// From WGS84 to WGS84
// php convert.php filepath (wgs84|) (sc|)
//
// Hemisphere : 1 is north, -1 is south. For MTM, hemisphere is by default 1, so it
//	is optional to indicate hemi for MTM
// sc or switch-coordinates to switch the two coords

function myecho($msg,$return_line=true)
{
	echo $msg;

	$rline = php_sapi_name() == "cli" ? "\n" :"<br/";
		
	echo ($return_line) ? $rline : "";
}


// Function converting from UTM or MTM coordinates to WGS84 Latitude/Longitude
// $E : easting
// $N : northing
// $Zone : the xtm zone (xtm = utm /mtm)
// $hemi : The hemisphere (+1 for north hemi, -1 for south hemi)
// $MTM : boolean indicating MTM if true, UTM if false
// Returns an array with keys "lat" and "lng"
function convert_xTM_to_LatLng($E, $N, $Zone, $hemi, $MTM)// convert UTM X,Y coordinates in km to WGS84 Lat and Lng 
{
	$f = 1/298.257223563; // Flattening
	
	if($hemi == 1) // northern hemisphere
		$N0 = 0; // in km
	else if($hemi == -1) // southern hemisphere
		$N0 = 10000; // in km

	$k0 = 0.9996; // =scaleTM
	if($MTM)
		$k0 = 0.9999;
	$E0 = 500; // in km
	if($MTM)
		$E0 = 304.8;
	
	$a = 6378.137; // Equatorial radius in km

	// Compute some preliminary values
	$n = $f/(2-$f); // n = eccent (excentricité)
	$A = ($a/(1+$n));
	$temp = 1;
	for($i =2; $i <= 6; $i+=2) // Pas forcément jusqu'à 6 mais c'est juste plus de précision
	{
		$temp += pow( $n , $i) / pow(4, $i-1);
	}
	$A = $A * $temp;

	$alpha= array();
	$alpha[0] = (1/2)*$n  - (2/3)*$n*$n + (5/16)*$n*$n*$n;
	$alpha[1] = $n*$n*(13/48) - $n*$n*$n*(3/5);
	$alpha[2] = $n*$n*$n*(61/240);

	$beta = array();
	$beta[0] = $n*(1/2) - $n*$n*(2/3) + $n*$n*$n*(37/96);
	$beta[1] = $n*$n*(1/48) + $n*$n*$n*(1/15);
	$beta[2] = $n*$n*$n*(17/480);

	$s = array();
	$s[0] = 2*$n - $n*$n*(2/3) -2*$n*$n*$n;
	$s[1] = (7/3) * $n*$n -$n*$n*$n*(8/5);
	$s[2] = (56/15) * $n*$n*$n;


	// UTM (E, N, Zone, hemi) to latitude, longitude (hemi +1 for northern, -1 for southern) (E=X, N=Y

	// Intermediate values
	$epsi = ($N - $N0)/($k0*$A);
	$nu = ($E - $E0)/($k0*$A);

	$epsi_ = $epsi;
	$nu_ = $nu;
	$sigma_ = 1;
	$tau_ = 0;

	for($j =1; $j <= 3; $j++)
	{
		$epsi_ -= $beta[$j-1] *sin(2 * $j * $epsi) * cosh(2 * $j * $nu);
		$nu_ -= $beta[$j-1] * cos(2 * $j *$epsi) * sinh(2 * $j * $nu);
		$sigma_ -= 2*$j*$beta[$j-1]*cos(2*$j*$epsi)*cosh(2*$j*$nu);
		$tau_ += 2*$j*$beta[$j-1] * sin(2*$j*$epsi) *sinh(2*$j*$nu);
	}
	$X = asin( sin($epsi_) / cosh($nu_)   ); 

	// Final values !
	$latitude = $X; // LATITUDE !

	for($j =1; $j <= 3; $j++)
	{
		//alert(s[""+j]);
		$latitude +=$s[$j-1] * sin(2*$j*$X);
	}
	// OBTENUE

	$lambda0 = $Zone * deg2rad(6) - deg2rad(183);
	//Zone * 6° - 183° : origine de la longitude
	// ref meridian of longitude
	if($MTM)
	{
		$mtmSmers =  // MTM zone to reference meridian
			array(0., 53., 56.,
			58.5, 61.5, 64.5, 67.5, 70.5, 73.5,
			76.5, 79.5, 82.5,
			81., 84., 87., 90., 93., 96., 99.,
			102., 105., 108., 111., 114., 117., 120., 123., 126.,
			129., 132., 135., 138., 141.);
		$lambda0 = - deg2rad($mtmSmers[$Zone] );
	}
	$lambda_longitude = $lambda0 + atan( sinh( $nu_) / cos( $epsi_)  );

	$k = (($k0*$A) /$a) * sqrt(   ( 1 +pow( ((1-$n)/(1+$n))*tan($latitude), 2)  )* ( (pow(cos($epsi_), 2) + pow(sinh($nu_), 2) ) / (   $sigma_*$sigma_   + $tau_*$tau_) ) );
	//k : point scale factor

	$gamma = $hemi *atan(  ( $tau_ + $sigma_*tan($epsi_)*tanh($nu_) ) /  ( $sigma_ - $tau_*tan($epsi_)*tanh($nu_)   )     );
	// gamma: meridian convergence angle

	return array("lat" => rad2deg($latitude), "lng" => rad2deg($lambda_longitude) );

}

function radtodeg($rad)
{
	return $rad * (180 / M_PI);
}
function degtorad($deg)
{
	return $deg * (M_PI / 180);
}

$input_file_path =''; // input file
$coords_system = ''; // MTM, UTM or WGS84
//$zone = -1; // zone number (for mtm and utm only)
$additional_data = array(); // additional data concerning the coords system (like zone or hemisphere for Xtm)
$switch_coords = false;

if( isset($argv[1] ) && isset($argv[2]) )
{
	$input_file_path= $argv[1];
	$coords_system = strtolower($argv[2]);
	
	$arr =explode("_",$coords_system);
	
	$coords_system =$arr[0];

	if( $coords_system =="mtm" || $coords_system == "utm")
	{
		for($i = 1; $i < count($arr);$i++)
		{
			if( preg_match("#^[-.0-9]+$#",$arr[$i] ) )// If it is a number
				$d = (float)$arr[$i];
			else
				$d = $arr[$i];

			if($i == 1)
				$additional_data["zone"] = $d;
			else if($i == 2)
				$additional_data["hemi"] = $d;
			else 
				$additional_data["".$i] = $d;
		}
	}

	$matches = array();

	if( isset($argv[3]))
		$switch_coords = (strtolower($argv[3])== "switch-coordinates" || strtolower($argv[3])=="sc");
	else
		$switch_coords = false;
}
else if( isset($_GET["input_file_path"]) && isset($_GET["coords_system"]) )
{
	$input_file_path= $_GET["input_file_path"];
	$coords_system = $_GET["coords_system"];

	if( isset($_GET["switch_coords"]))
		$switch_coords = true;

	foreach($_GET as $key => $value)
	{
		if( $key != "switch_coords" && $key != "coords_system" && $key != "input_file_path")
			$additional_data[$key] = $value;
	}
}
class ConverterCallback {
	private $filetype;
	private $additional_data;
	private $coords_system;
	private $switch_coords;

	// $filetype : "kml" or "json", to output data differently
	// $coords_system : specifies the coordinates system (e.g UTM,MTM or WGS84)
	// $additional_data : additional data related to the coordinates like zone if MTM/UTM or hemisphere
	// $switch_coords : if we have to switch between lat and lng
	function __construct($filetype,$coords_system, $additional_data, $switch_coords=false) {
		$this->additional_data = $additional_data;
		$this->coords_system =$coords_system;
		$this->filetype =$filetype;
		$this->switch_coords = $switch_coords;
	}

	public function callback($matches) {
		$X = $matches[1];
		$Y = $matches[2];
		// Converting utm and mtm

		$latlng =array();
		if( preg_match("#^(utm|mtm)$#",$this->coords_system ) )
			$latlng=convert_xTM_to_LatLng($X/1000, $Y/1000, $this->additional_data["zone"], $this->additional_data["hemi"], ($this->coords_system=="mtm") );
		else if( "wgs84"==$this->coords_system)
			$latlng = array("lat"=>$X, "lng"=>$Y);

		$lat = $latlng["lat"]; $lng = $latlng["lng"];

		// Switching coordinates if indicated
		if($this->switch_coords==true)
		{
			$lng = $latlng["lat"]; $lat = $latlng["lng"];
		}

		// different output for every file type
		if($this->filetype=="json")
			return '['.$lat.','.$lng.']';
		else if($this->filetype == "kml")
			return "$lat,$lng";
	}
}

try
{
	// Test variables validity
if( preg_match("#^(utm|mtm|wgs84)$#i",$coords_system) == 0)
	throw new Exception("Coordinate system '$coords_system' not handled. UTM, MTM and WGS84 only");
else if( ($coords_system == "utm") &&(!isset($additional_data["zone"]) || !isset($additional_data["hemi"] )))
	throw new Exception("Specify zone and hemisphere for utm (e.g UTM_8_1 for UTM zone 8, hemi 1)");
else if(($coords_system == "mtm") && !isset($additional_data["zone"]) )
	throw new Exception("Specify MTM zone (e.g MTM_8 for zone 8)");

if( $coords_system == "mtm")
	$additional_data["hemi"] = 1;

// Opening files

if( !file_exists($input_file_path))
	throw new Exception("The file does not exist : $input_file_path");

$input = fopen($input_file_path, "r");

if( !$input )
	throw new Exception("Failed to open input file : $input_file_path");

$type = '';
$output_file = fopen("output/".basename($input_file_path), "w");

if(!$output_file)
	throw new Exception( "Failed to open output file : output/.basename($input_file_path)");

// Specifying file type (json or kml...)
if( preg_match("#.*\.kml$#i",$input_file_path ) )
	$type = 'kml';
else 
	$type = 'json'; // Default type

$i = 0;

// Reading input file and converting coordinates from whatever system to wgs84 gps coordinates (latitude and longitude)
$range_no_coords = 0;
while($line = fgets($input))
{
	$matches = array();
	$line = trim($line);

	$found = 0; // coords found in the current line

	$converterCallback = new ConverterCallback($type, $coords_system, $additional_data, $switch_coords);

	// Regex replacing coordinates
	if($type == 'json')
		$tooutput = preg_replace_callback('#\[ ?([-0-9.]+) ?, ?([-0-9.]+) ?\]#i', array($converterCallback, 'callback'), $line, -1, $found);
	else if($type == 'kml')
	{
		 preg_match("#<coordinates>(.+)</coordinates>#i",$line,$matches);
		$coords = trim(" ".$matches[1]." ");
		
		$tooutput = preg_replace_callback('# ([-0-9.]+) ?, ?([-0-9.]+)( ?, ?[-.0-9]+ ?)? #i', array($converterCallback, 'callback'), $coords, -1, $found);
	}
	if($found == 0) // If not found coordinates to convert in this line
	{
		if(!fwrite($output_file, $line."\n"))
			throw new Exception("Could not write to output file $output_file");	

		$range_no_coords ++;
		
//		myecho( "Line $i does not contain coordinates."); // Notice it
	}
	else // Coordinates to convert Found
	{
		if($range_no_coords)
		{
			myecho("Line ".($i - 1 - $range_no_coords)." to ".($i-1)." does not contain coordinates");
			$range_no_coords = 0;
		}
			
		myecho ("Line $i output : ".$tooutput);
		if( !fwrite($output_file, $tooutput."\n") )
			throw new Exception("Could not write to output file $output_file");
	}


	$i++;
}
myecho("");
myecho("Output file : output/".basename($input_file_path));
fclose($output_file);
fclose($input);
}
catch(Exception $e)
{
	myecho ("Error : ".$e->getMessage());
	exit(0);
}
?>
