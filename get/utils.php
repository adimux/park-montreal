<?php
function testSchedulesPan($dateToTest, $schedules)
{
	$inPeriod = false;
	$nextPeriods = array();
	foreach($schedules as $sc)
	{
		if( $sc["cas"] == "ALL" || $sc["cas"] == "SCHOOL DAYS")
		{
			try
			{
				if( $sc["schedule"] )
				{
					$sched = new DateSchedule( $sc["schedule"] );
					if( $sched->inPeriod($dateToTest) )
						$inPeriod=true;
					$nextPeriods[] = $sched->nextPeriod($dateToTest);
				}

			}catch(Exception $e)
					{
						throw new Exception("Problème avec le panneau ".$pan["CODE_RPA"]." : ". $e->getMessage() );
						// pas grave
					}
			//$dateToTest = new DateTime();
			//$dateToTest->setTimestamp($timestamp);
			//$dateToTest->setTimeZone(new DateTimeZone(date_default_timezone_get()) );

		}

	}
	$nextPeriod=null;
	//
	foreach($nextPeriods as $next)
	{
		if(!$nextPeriod)
			$nextPeriod=$next;

		if( $next["start"] < $nextPeriod["start"] )
			$nextPeriod = $next;
	}
	return array("inPeriod"=>$inPeriod, "nextPeriod"=>$nextPeriod);
}

function getRectFrom($get)
{
/*$north_west["lat"] = 45.554625;
$north_west["lng"] = -73.618846;
$south_east["lat"] = 45.550328;
$south_east["lng"] = -73.607324;*/


if( isset($_GET["lat_nwest"]) )
	$north_west["lat"] =($_GET["lat_nwest"]);
if( isset($_GET["lng_nwest"]))
	$north_west["lng"] = $_GET["lng_nwest"];

if( isset($_GET["lat_seast"]) )
	$south_east["lat"] = $_GET["lat_seast"];
if( isset($_GET["lng_seast"]))
	$south_east["lng"] = $_GET["lng_seast"];



$x1=$north_west["lat"];
$y1=$north_west["lng"];
$x2=$south_east["lat"];
$y2=$south_east["lng"];

if( !is_numeric($x1) || !is_numeric($x2) || !is_numeric($y1) || !is_numeric($y2) )
	throw new Exception("Expected coordinates to be numeric (lat_nwest, lng_nwest, lat_seast, lng_seast or juste lat_point, lng_point)");

$d = sqrt( pow($x1 - $x2, 2) + pow($y1 - $y2,2) ) ;
if( $d > 0.03) // If the rect is too far then don't 
	throw new Exception("Distance too far");

$nw = array($north_west["lng"], $north_west["lat"]);
$se = array($south_east["lng"], $south_east["lat"]);

return array("north_west"=>$nw, "south_east"=>$se );
}
?>
