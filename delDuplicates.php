<?php
$PDO_CONNECT=true;
include("dbcon.php");

echo "supprimer duplicates poteaux";

$tbl_poteaux_geoloc = tbl_poteaux_geoloc;
$q ="SELECT `".$tbl_poteaux_geoloc."`.POTEAU_ID_POT FROM `$tbl_poteaux_geoloc`";
$st=$conn->prepare("DELETE FROM ".$tbl_poteaux_geoloc." WHERE POTEAU_ID_POT=:id");
foreach( $conn->query($q) as $row)
{
	$id = $row["POTEAU_ID_POT"];
	$first=true;
	foreach($conn->query("SELECT `".$tbl_poteaux_geoloc."`.POTEAU_ID_POT FROM ".$tbl_poteaux_geoloc." WHERE POTEAU_ID_POT='$id'") as $r)
	{
		echo "".$r["POTEAU_ID_POT"]." is duplicate\n";
		if($first)
			$first=false;
		else
		{
		$st->execute(array(":id"=>$r["POTEAU_ID_POT"]) );
		}
	}
}

echo "done!\n";
?>
