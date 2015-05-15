<?php


$PDO_CONNECT = true;

require_once("dbcon.php");
require_once("geoPHP/geoPHP.inc");
require_once("class.sqlGeoTable.php");
require_once("utils.php");

header('Content-type:application/javascript; charset=utf-8');

$within = '';
try
{

// 45.554625, -73.618846
// to 45.550328, -73.607324

$EPSG_standard = isset($_GET["EPSG"])?(bool)$_GET["ESPG"]:false; // EPSG standard is lat/lng so we have to switch them in the input and in the results (output json)

/*if($EPSG_standard)
{
	$nw = new Point( $north_west["lat"], $north_west["lng"] );
	$se = new Point( $south_east["lat"], $south_east["lng"] );

}
else
{*/
//$nw =new Point( $north_west["lng"], $north_west["lat"] );
//$se = new Point( $south_east["lng"], $south_east["lat"] );
//
$rect = getRectFrom($_GET);
$nw = $rect["north_west"];
$se = $rect["south_east"];

$table_vcpogg = new sqlGeoTable($conn, "vcpogg");

$invertxy_results = false;
// get all features within this rect 
$troncons = $table_vcpogg->inRect($nw, $se, "", true, $invertxy_results);
echo $table_vcpogg->toGeoJSON($troncons);


}catch(Exception $e)
{
	echo json_encode( array("Error"=>$e->getMessage(),"backtrace"=>$e->getTrace()) );
}
?>
