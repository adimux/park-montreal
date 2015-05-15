<?php
include("dbcon.php");

foreach( DB::query("SELECT CODE_RPA,DESCRIPTION_RPA FROM `".tbl_pan."` ")
	as $row)
{
	$image ='';
	$code = $row["CODE_RPA"];
			$path = '/home/adimux/cherti.name/adam.cherti.name/CREE TA VILLE/images_panneau/png/'.$code;
			echo $path."\n";
			$ext = ".JPG";
			$exts = array(".PNG",".png",".jpg", ".JPG", ".gif",".GIF");
			foreach($exts as $ext)
			{
				if( file_exists($path.$ext) )
				{
					$image = 'http://adam.cherti.name/CREE%20TA%20VILLE/images_panneau/png/'.$code.$ext;
					break;
				}
			}

			if( !$image)
			{
			$path = '/home/adimux/cherti.name/adam.cherti.name/CREE TA VILLE/images_panneau/'.$code;
			echo $path."\n";
			$ext = ".JPG";
			$exts = array(".PNG",".png",".jpg", ".JPG", ".gif",".GIF");
			foreach($exts as $ext)
			{
				if( file_exists($path.$ext) )
				{
					$image = 'http://adam.cherti.name/CREE%20TA%20VILLE/images_panneau/'.$code.$ext;
					break;
				}
			}
			}
			echo "Image : $image\n";


			DB::query("UPDATE  `".tbl_pan."` SET image=%s WHERE CODE_RPA=%s",$image, $code);
}
?>
