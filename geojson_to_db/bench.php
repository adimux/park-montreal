<?php
include('JParser/class.JParser.php');

class bench
{
	private $tstart;
	private $total;
	private $name;

	public function __construct($name)
	{
		$this->name =$name;
		$this->start();
	}
	public function dump()
	{
		echo "Bench $this->name : $this->total secs";
	}
	public function start()
	{
		$this->tstart = time();
	}
	public function stop()
	{
		$this->total += time() - $this->tstart;
	}
	public function getTotal()
	{return $this->total;}

}

$parser = JParser::open("JParser/signalisation-description-panneau.json",array("memlimit"=>5000000));
$bench_fetch = new bench("fetch");
$bench_json = new bench("fetch and getjson");
do
{
	$bench_fetch->start();
	$fetchRunning = $parser->fetch();
	$bench_fetch->stop();


	while(($ch=$parser->nextChild())!==null)
	{
		$bench_json->start();
		$obj = $ch->getJSON();
		$bench_json->stop();
	}
	
}while($fetchRunning);

$bench_json->dump();
$bench_fetch->dump();
?>
