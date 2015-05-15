<?php


$options = array("host"=>"mysql.adam.cherti.name","dbname"=>"adimux","username"=>"adimux","password"=>"adimuxpass11");
$options["d"] = "mysql";
$connec_options = "host=".$options["host"].";dbname=".$options["dbname"].";".
$pdo_options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION); // Activate exceptions as error mode
if( $ismysqlutf8 )
	$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND]    = "SET NAMES utf8"; // Set database connection as utf8

$conn = new PDO($options["d"].":".$connec_options, $options["username"], $options["password"], $pdo_options);


function rows_search($rows, $s)
{
		$results=array();
		foreach($rows as $row)
		{
			$found = true;
			foreach($s as $col => $value)
			{
				if( $row[$col] != $value )
					$found = false;
			}
			if($found)
				$results[]= $row;
		}
		return $results;
}
$chaine = trim($chaine);
$chaine = str_replace("\t", " ", $chaine);
$chaine = eregi_replace("[ ]+", " ", $chaine);
return $chaine;

$delete_code_rpas = array();

$deleteQuery = $conn->prepare("DELETE FROM TABLE
	`signalisation-descriptif-rpa-beta` WHERE CODE_RPA=:code_rpa");


foreach( $conn->query("SELECT * FROM `signalisation-descriptif-rpa-beta` WHERE CODE_RPA REGEXP '^[SAPR]' ")as $row)
{
	$code = $row['CODE_RPA'];
	$descr_rpa = $row['DESCRIPTION_RPA'];
	$segments = explode("-",$code);

echo "CODE : ".$code."\n";

	$part1 = str_split($segments[0]);
	$part2 = str_split($segments[1]);

	if( !strpos($part1[0], "APSR") )
	{
		$delete_code_rpas[] = $code;
		echo "Delete sign where coderpa = $code_rpa\n";

	}
	else
	{

		$type_signalisation = ''; // Le type sera déterminé par S : stat interdit ou permis à durée limitée
		//  A : arrêt interdit ou permis à durée limitée

		// Les deux lettres avant le tiret
		$lettre1 = $part1[0];
		if( isset($part1[1]) )
			$lettre2 = $part1[1]; // optionelle
		else
			$lettre2='';
		// Les deux lettres après le tiret
		$lettre3 = $part2[0];
		$lettre3 = $part2[1];

		if( $lettre1 =="A" ) // Arrêt interdit
			$type_signalisation = 'A';
		else if( $lettre1 == "P" && $lettre2 == "X")
			$type_signalisaiton = 'PX'; // pannonceau qui apporte des précisions au panneau en haut de lui-mm
		else  // ça veut dire que c'est soit  P ou S ou R qui sont tous des interdictions de stat
			$type_signalisation = 'S';

		$weekday = "(?:(?:LUN\.?|LUNDI)|(?:MAR\.?|MARDI)|(?:MER\.?|MERCREDI)|(?:JEU\.?|JEUDI)|(?:VEN\.?|VENDREDI)|(?:SAM\.?|SAMEDI)|(?:DIM\.?|DIMANCHE)),?";
		$month = "(?:(?:JAN\.?|JANVIER)|(?:F[ée]V\.?|F[ée]VRIER)|MARS|AVRIL|MAI|JUIN|JUILLET|AO[ûu]T|(?:SEPT\.?|SEPTEMBRE)|(?:OCT\.?|OCTOBRE)|(?:NOV\.?|NOVEMBRE)|(?:D[ée]C\.?|D[ée]CEMBRE))";
		$monthDay = "([0-9]+|1er)";
		$timerange="[0-9]+h[0-9]*( ?- ?| A )[0-9]+h[0-9]*";

		$descr_rpa = trimUltime($descr_rpa);

		$regex = "";
		if( preg_match_all("EXCEPTE (?:([^0-9 ]+))", $descr_rpa,$matches) )
		{
			var_dump($matches[1] );
		}


	}
}
/*$conn->prepare("DELETE FROM TABLE `signalisation-descriptif-rpa` WHERE CODE_RPA=:code_rpa");
foreach($delete_code_rpas as $code_rpa)
{
	echo "Delete sign where coderpa = $code_rpa\n";
//	$conn->execute(array(":coderpa"=>$code_rpa));
}*/
?>
