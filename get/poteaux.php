<?php
$PDO_CONNECT=true;
require_once("dbcon.php");
require_once("utils.php");
require_once("geoPHP/geoPHP.inc");
require_once("class.sqlGeoTable.php");
require_once("class.DateSchedule.php");

$id = 5766;
$id =  $argv[1]?$argv[1]:$id;

try
{

$rect = getRectFrom($_GET);
$nw = $rect["north_west"];
$nw =array_reverse($nw);
$se =array_reverse( $rect["south_east"]);

if( isset($_GET["type_restrict"] ) )
	$type_restrict = $_GET["type_restrict"];
else
	$type_restrict = null;
if( isset($_GET["date"]) )
	$date = $_GET["date"];
else
	$date = null;

$table_poteaux = new sqlGeoTable($conn, tbl_poteaux_geoloc);

$poteaux = $table_poteaux->inRect($nw,$se,"DESCRIPTION_REP='Réel' AND hidden='0' ",true,true);
// on cherche mainteannt les panneaux associés à ce potea
for($i=0; $i< count($poteaux);$i++)
{
	$poteau = $poteaux[$i];
//	if( $type_restrict=="A" && !preg_match("^(A|",$poteau["type"]) )
//	{
//		unset($rows[$i];
//		continue;
//	}

	$id = $poteau["POTEAU_ID_POT"];
	$panneaux = $conn->query("SELECT * FROM `".tbl_poteaux."` INNER JOIN `".tbl_pan."` ON `".tbl_poteaux."`.CODE_RPA=`".tbl_pan."`.CODE_RPA WHERE POTEAU_ID_POT='$id' ORDER BY POSITION_POP DESC")->fetchAll(PDO::FETCH_ASSOC);

	$stat_autorise=null;
	$arret_autorise=null;
	$temps_max_stat = 0;
	$temps_max_arret = 0;
	$next_period_stat_interdit='';
	$next_period_arret_interdit ='';
	if( $date !==null )
	{
		$dateToTest = Datetime::createFromFormat("Y/m/d_H:i:s", $date);
		$stat_autorise = true;
		$arret_autorise = true;
		for($j = 0; $j < count($panneaux) ;$j++)
		{
			$pan = $panneaux[$j];
//echo $pan["CODE_RPA"]."\n";
/*			if( $type_restrict == "A" && !preg_match("#^(A|PX)#i",$pan["type"] ) )
			{
				unset($panneaux[$j]);
				continue;
			}
			else if(  $type_restrict == "S" && !preg_match("#^(S|PX)#i",$pan["type"] ) )
			{
				unset($panneaux[$j]);
				continue;
			}*/


			$schedules = $conn->query("SELECT * FROM `".tbl_pan_schedules."` WHERE CODE_RPA='".$pan["CODE_RPA"]."'")->fetchAll();
			$autorise = true;
			$nextPeriod="";

			try{
				$result = testSchedulesPan($dateToTest,$schedules);
				$autorise = ! $result["inPeriod"];
				$format= "Y/m/d H:i:s";
				if( count($result["nextPeriod"]) )
					$nextPeriod = array("start"=>$result["nextPeriod"]["start"]->format($format),
						"end"=>$result["nextPeriod"]["end"]->format($format) );
				if( $pan["CODE_RPA"] == "BUS" )
					$nextPeriod = array();

			}
			catch(Exception $e)
			{

			}

			$autorise = $autorise && (!(boolean)$pan["autorise"]);

			if( preg_match("#S#i", $pan["type"] ) ) // stationnement
			{
				$temps_max_stat= $pan["temps_max"];
				$next_period_stat_interdit = $nextPeriod;
				if($stat_autorise)
				{
					$stat_autorise = $autorise;
				}

			}
			if( preg_match("#A#i", $pan["type"] ) )
			{
				$temps_max_arret = $pan["temps_max"];
				$next_period_stat_interdit = $nextPeriod;


				if($arret_autorise)
					$arret_autorise=$autorise;

				 // Si l'arrêt n'est pas autorisé donc le stationnement ne l'est pas aussi
				if( $stat_autorise )
				{
					$stat_autorise=$autorise;
				}

			}
			if( preg_match("#PX#i",$pan["type"]) )// Donc pannonceau
			{
			}

		}
	}

	if( $stat_autorise !==null)
		$poteaux[$i]["stat_autorise"] = $stat_autorise;
	if( $arret_autorise !==null)
		$poteaux[$i]["arret_autorise"] = $arret_autorise;
	$poteaux[$i]["temps_max_stat"] = $temps_max_stat;
	$poteaux[$i]["temps_max_arret"] = $temps_max_arret;
	$poteaux[$i]["next_period_stat_interdit"] = $next_period_stat_interdit;
	$poteaux[$i]["next_period_arret_interdit"] = $next_period_arret_interdit;

	$poteaux[$i]["panneaux"]=$panneaux;
		//unset($poteaux[$i]);
}
echo $table_poteaux->toGeoJSON($poteaux);
/*

$invertxy_results = false;
// get all features within this rect 
$pt = $table_poteaux->inRect($nw, $se, true, $invertxy_results);
echo $table_poteaux->toGeoJSON($pt);
 */

}catch(Exception $e)
{
	echo json_encode( array("Error"=>$e->getMessage(),"backtrace"=>$e->getTrace()) );
}



?>
