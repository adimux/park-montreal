<!DOCTYPE HTML>
<html style="background-color:white">
<body>
<?php
$PDO_CONNECT=true;
require_once("dbcon.php");
require_once("utils.php");
require_once("class.sqlGeoTable.php");
require_once("class.DateSchedule.php");


if( isset($_GET["poteau"]) )
{
	$id = $_GET["poteau"];
	echo "ID : $id<br/>";
	$panneaux = $conn->query("SELECT * FROM `".tbl_poteaux."` INNER JOIN `".tbl_pan."` ON `".tbl_poteaux."`.CODE_RPA=`".tbl_pan."`.CODE_RPA WHERE POTEAU_ID_POT='$id' ORDER BY POSITION_POP DESC")->fetchAll(PDO::FETCH_ASSOC);

	$stat_autorise=null;
	$arret_autorise=null;
	$date = "2015/6/5_19:46:00";
	if( $date !==null )
	{
		$dateToTest = Datetime::createFromFormat("Y/m/d_H:i:s", $date);
		$stat_autorise = true;
		$temps_max_stat = 0;
		$arret_autorise = true;
		$temps_max_arret = 0;
		for($j = 0; $j < count($panneaux) ;$j++)
		{
			$pan = $panneaux[$j];
			echo $pan["CODE_RPA"]."<br/>";
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

			$result = testSchedulesPan($dateToTest,$schedules);
			$autorise = ! $result["inPeriod"];
			echo ($autorise ? "Autorisé !":"Non autorisé") . "\n";
			/*foreach($schedules as $sc)
			{
				if( $sc["cas"] == "ALL" ||( $sc["cas"] == "SCHOOL DAYS" && $dateToTest->format("N") <= 5 ))
				{
					try
					{
						if( $sc["schedule"] )
						{
							$sched = new DateSchedule( $sc["schedule"] );
							if( $sched->inPeriod($dateToTest) )
								$autorise = false;
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

			}*/

			$autorise = $autorise && (!(boolean)$pan["autorise"]);
			echo ($autorise ? "Autorisé ":"NOn" ). "<br/>";

			if( preg_match("#S#i", $pan["type"] ) && $stat_autorise )
			{
				$temps_max_stat= $pan["temps_max"];
				if($stat_autorise)
				{
					echo "write it<br/>";
					$stat_autorise = $autorise;
				}
			}
			if( preg_match("#A#i", $pan["type"] ) && $arret_autorise)
			{
				$temps_max_arret = $pan["temps_max"];

				if($arret_autorise)
					$arret_autorise=$autorise;

				 // Si l'arrêt n'est pas autorisé donc le stationnement ne l'est pas aussi
				if( $stat_autorise )
				{
					$stat_autorise=$autorise;
				}
			}

		}
		echo "Finalement stat : ".intval($stat_autorise)."<br/>";
	echo "Finalement arret : $arret_autorise<br/>";

	}
}

?>
</body>
</html>
