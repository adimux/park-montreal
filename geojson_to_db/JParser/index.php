<?php
include("class.JParser.php");

$data = JParser::open("signalisation-description-panneau.json",true);

$i = 0;
$r  = $data->fetch(-1, 6800000);
while($r != false )
{
	while( $child = $data->nextChild() )
	{
		echo $child->getName()."\n";
	}
	echo "next\n";
	$i++;
	$r = $data->fetch(-1,6800000 );
}
$data->dump();
echo "offset : ".$data->getFileOffset()."\n";
/*
$f = new FileReader(fopen("signalisation-description-panneau.json","r"),2000000);
$before=time();
while( ($c = $f->getc())!==FALSE );
$b1 = time()-$before;
unset($f);

$file = fopen("signalisation-description-panneau.json","r");
fseek($file,0);
$before=time();
while( ($c = fgetc($file)) !== false );
$b2 = time() -$before;


echo "Bench 1 : $b1\n";
echo "Bench 2 : $b2\n";
	//echo $c;
echo "\n"*/
?>
