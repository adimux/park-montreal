<?php
$PDO_CONNECT=true;
include('dbcon.php');



$tbl_poteaux_geoloc = tbl_poteaux_geoloc;
//$tbl_poteaux_geoloc = 'poteau-PMR';

echo "add column hidden\n";
if(count( $conn->query("SHOW COLUMNS FROM	`$tbl_poteaux_geoloc` LIKE 'hidden'")->fetchAll() ) == 0)
$conn->query("ALTER TABLE `$tbl_poteaux_geoloc` ADD COLUMN hidden tinyint(1) DEFAULT 0 ");


/*echo "SELECT POTEAU_ID_POT FROM `".tbl_poteaux."`  WHERE `".tbl_poteaux."`.CODE_RPA='BUS'\n";
$result = $conn->query("SELECT POTEAU_ID_POT FROM `".tbl_poteaux."`  WHERE `".tbl_poteaux."`.CODE_RPA='BUS'");

foreach($result as $row)
{
	echo "fuck\n";
	$id=$row["POTEAU_ID_POT"];
	echo $id."\n";
$conn->query("UPDATE `".$tbl_poteaux_geoloc."` SET hidden=0 WHERE POTEAU_ID_POT='$id'");

}
exit(0);*/

echo "supprimer assoc poteaux pan dont le code rpa est introuvable\n";
//$st = $conn->prepare("DELETE FROM `".tbl_poteaux."` WHERE POTEAU_ID_POT=:id ");
$st2 = $conn->prepare("DELETE FROM `".tbl_poteaux."` WHERE POTEAU_ID_POT=:id AND CODE_RPA=:code ");
echo "SELECT `".tbl_poteaux."`.POTEAU_ID_POT, `".tbl_poteaux."`.CODE_RPA FROM `".tbl_poteaux."` LEFT JOIN `".tbl_pan."` ON `".tbl_pan."`.CODE_RPA=`".tbl_poteaux."`.CODE_RPA WHERE `".tbl_pan."`.CODE_RPA IS NULL\n";
foreach( $conn->query("SELECT `".tbl_poteaux."`.POTEAU_ID_POT, `".tbl_poteaux."`.CODE_RPA FROM `".tbl_poteaux."` LEFT JOIN `".tbl_pan."` ON `".tbl_pan."`.CODE_RPA=`".tbl_poteaux."`.CODE_RPA WHERE `".tbl_pan."`.CODE_RPA IS NULL") as $row)
{
	$id = $row["POTEAU_ID_POT"];
	$code=$row["CODE_RPA"];
//	$st->execute(array(":id"=>$id));
	$st2->execute(array(":id"=>$id,":code"=>$code));

}

/*echo "cacher poteaux dont le code rpa est introuvable\n";
//$st = $conn->prepare("DELETE FROM `".tbl_poteaux."` WHERE POTEAU_ID_POT=:id ");
$st2 = $conn->prepare("UPDATE `".$tbl_poteaux_geoloc."` SET hidden=1 WHERE POTEAU_ID_POT=:id");
echo "SELECT * FROM `".tbl_poteaux."` LEFT JOIN `".tbl_pan."` ON `".tbl_pan."`.CODE_RPA=`".tbl_poteaux."`.CODE_RPA WHERE `".tbl_pan."`.CODE_RPA IS NULL\n";
foreach( $conn->query("SELECT * FROM `".tbl_poteaux."` LEFT JOIN `".tbl_pan."` ON `".tbl_pan."`.CODE_RPA=`".tbl_poteaux."`.CODE_RPA WHERE `".tbl_pan."`.CODE_RPA IS NULL") as $row)
{
	$id = $row["POTEAU_ID_POT"];
//	$st->execute(array(":id"=>$id));
	$st2->execute(array(":id"=>$id));

}*/

echo "cacher poteaux sans assoc avec la table poteaux-panneau\n";
$st=$conn->prepare("UPDATE `".$tbl_poteaux_geoloc."` SET hidden=1 WHERE POTEAU_ID_POT=:id");
echo "SELECT  `$tbl_poteaux_geoloc`.POTEAU_ID_POT FROM `$tbl_poteaux_geoloc` LEFT JOIN `".tbl_poteaux."` ON `$tbl_poteaux_geoloc`.POTEAU_ID_POT=`".tbl_poteaux."`.POTEAU_ID_POT WHERE `".tbl_poteaux."`.CODE_RPA IS NULL AND `$tbl_poteaux_geoloc`.hidden = 0\n";
exit(0);
foreach($conn->query("SELECT `$tbl_poteaux_geoloc`.POTEAU_ID_POT FROM `$tbl_poteaux_geoloc` LEFT JOIN `".tbl_poteaux."` ON `$tbl_poteaux_geoloc`.POTEAU_ID_POT=`".tbl_poteaux."`.POTEAU_ID_POT WHERE `".tbl_poteaux."`.CODE_RPA IS NULL AND `$tbl_poteaux_geoloc`.hidden = 0") as $row)
{
	$id = $row["POTEAU_ID_POT"];
	echo "found $id\n";
	$st->execute(array(":id"=>$id));
}
?>
