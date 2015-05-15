<?php
$tmp = "tmp";
function clearTmp()
{
rmDirFiles($tmp);
}

function rmDirFiles($dir, $rmdir=false) { 
	if (is_dir($dir)) { 
		$objects = scandir($dir); 
		foreach ($objects as $object) { 
			if ($object != "." && $object != "..") { 
				if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
			} 
		} 
		reset($objects); 
		if($rmdir)
		rmdir($dir); 
	} 
}

scandir("input");

clearstatcache();
if( !is_dir($tmp) )
	mkdir($tmp);

$fichier = fopen("poteaux_geoloc_liens","r");
while(($line=fgets($fichier) ))
{
	$tab= explode("^",$line);
	$arrond = $tab[0];
	$lien = $tab[1];
	echo "$arrond : $lien\n";
	exec("wget $lien ");
}

?>
