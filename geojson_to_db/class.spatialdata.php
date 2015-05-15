<?php

abstract class Geom
{
	abstract function toWKB();
}

class Point extends Geom
{
	public $X;
	public $Y;
	
	__construct($X, $Y)
	{
		$this->X = $X;
		$this->Y = $Y;
	}
	// toWKB()
	// Converts geom to well known binary used in databases	
	public function toWKB()
	{
		
	}
	public function toWKT()
	{
	}
}
class LineString extends Geom
{
	__construct()
	{
		$args = func_get_args();
		for($i =0; $i <  count($args); $i++)
		{
		}
	}
	public function toWKB()
	{
	}
	public function toWKT()
	{
	}
}
