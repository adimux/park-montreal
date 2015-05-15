<html>
<body>
<?php
include("dbcon.php");
if (isset($_POST["id"]))
{
	$id = $_POST["id"];
	$type = $_POST["type"];
	$heures = $_POST["heures"];
	$jours = $_POST["jours"];
	$mois = $_POST["mois"];
	$temps_max = $_POST["max"];
	$interv = "$heures $jours $mois";

	$description = "Ajouté par un utilisateur : Interdit ".($type=="S"?"de stationner":"d'arrêter")." pendant ".$interv;
	$code = "USER". rand(0, 10000000);

	echo "$id $code $description";
	echo "q1<br/>";
	DB::query("INSERT INTO `".tbl_poteaux."` (POTEAU_ID_POT, CODE_RPA,DESCRIPTION_RPA) VALUES(%s, %s, %s) ",$id, $code,$description);
	echo "q2<br/>";
	DB::query("INSERT INTO `".tbl_pan."` (DESCRIPTION_RPA, type, temps_max,CODE_RPA) VALUES (%s,%s,%s,%s)",$description, $type, $temps_max, $code);
	echo "q3<br/>";
	DB::query("INSERT INTO `".tbl_pan_schedules."` (CODE_RPA, cas,schedule) VALUES(%s,%s,%s) ",$code,"ALL",$interv);


}

?>
MERCI !
</body>
</html>
