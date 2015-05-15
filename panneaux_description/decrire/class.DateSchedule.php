<?php

/**
 * class.DateSchedule.php
 * Serves to represent a period of time that repeats 
 * It's a cron-like schedule, example of input :
 * 'W1-5 D10:00-14:00' means Weekly Monday to Friday, From 10am to 2pm
 *
 * @author Adimux <adam@cherti.name>
 * @version 0.1
 * @copyright (C) 2015 Adimux <adam@cherti.name>
 * @license MIT
 */



/*
class Range
{
	private $min;
	private $max;
	private $start;
	private $end;

	private $inf;

	public function __construct($start,$end, $min, $max,$endExclusive=true)
	{
		if( $min == "inf" || !isset($min) )
		{
			$this->inf=true;return;
		}
		$this->endExclusive =$endExclusive;
		$this->min = $min;
		$this->max = $max;
		$this->start = $start;
		$this->end = $end;
	}
	public function getMax()
	{return $this->max;}
	public function inRange($n)
	{
		if($this->end_exclusive==true)
			$test2 = $n < $this->end;
		else
			$test2 = $n <= $this->end;

		return ($n >= $this->start && $n <= $this->end);
	}
	public function nextInRange($n)
	{
//		if($n > $this->end )
//			return $this->min;
//		else if( $n < $this->min)
//			return $this->min;
//		else
		//			return $n+1;
		if ( ($n >= $this->start && (($n < $this->end && $this->endExclusive) || 
									($n <= $this->end && !$this->endExclusive)) )
			|| $this->inf)
			return 1;
		else if ( $n > $this->end ) 
			return $this->start-$n +($this->max-$this->min);
		else if( $n < $this->start)
			return $this->start - $n;
	}

}
class  MonthDayRange extends Range
{
//	private $
	private $end_date;
		public function __construct()
		{}
	public function inRange($monthday) // format M/D : 01/15 = feb 15th
	{
			
	}
	public function nextInRange()
	{
	}
}

class NRange extends Range
{
	private $ranges;
	public function __construct()
	{ 
		if( func_num_args() > 0)
		{
			for($i =0;$i<func_num_args();$i++)
			{
				$this->ranges[] = func_get_arg($i);

			}
		}
	}
	public function inRange()
	{
		$in = true;
		if(func_num_args() == (count($this->ranges)) )
		{
			for($i=0; $i < func_num_args(); $i++)
			{
				$number = func_get_arg($i);
				if( !($in=$this->ranges[$i]->inRange($number) ))
					break;
			}
		}
		return $in;
	}
	public function nextInRange()
	{
		$nexts = array_fill(0, count($this->ranges), 0);
		if( func_num_args() == (count($this->ranges)) )
		{
			for($i = func_num_args();$i>=0; $i++)
			{
				$n = func_get_arg($i);
				$toadd = $this->ranges[$i]->nextInRange($n);
				if( ($n + $toadd) > $this->ranges[$i]->getmax)
				{
					if( $i == 0)
						return null;
					else
					{
						$next[$i] = ($n + $toadd) - $this->ranges[$i]->getMax();
						$next[$i-1] += 1;
					}
				}	

				$nexts[$i] = $next;
			}
		}
		else
			throw new Exception("Number of parameters not suffiscient ");
			
		return $next;
	}
}*/
class DateSchedule
{
	private $pd_intervals=array(); // 2D tab, contain levels that contains pd intervals (UNIONS are between the components of each level by default)
			// sorted by level

	private $min_level;
	private $max_level;
	private $debug_mode=false;
	public function __construct($str)
	{
		if(($str=trim($str))){
			$pd_int = explode(" ", $str);

			$first =true;
			foreach($pd_int as $pd)
			{
				if($this->debug_mode) echo $pd."**\n";
				
				if( is_string($pd) )
					$toadd = new PeriodicDateInterval($pd);
				else
					$toadd = $pd;
				$level = $toadd->getLevel();

				
				if($this->debug_mode) echo $level."\n";

				if($first)
				{
					$min_level = $level;
					$max_level = $level;
					$first =!$first;
				}
				else if( $min_level > $level)
					$min_level = $level;
				else if($max_level < $level)
					$max_level = $level;

				if( !isset($this->pd_intervals[$level] ) )
					$this->pd_intervals[$level] = array();

				$this->pd_intervals[$level][] = $toadd;

			}
			$this->max_level = $max_level;
			$this->min_level = $min_level;
		}
		else
			throw new Exception("DateSchedule __constructor requires at least one parameter (string) or multiple objects of PeriodicDateInterval types");

		if($this->debug_mode) echo "min $min_level max $max_level\n";
	}
	public function inPeriod($date)
	{
		for($lv = $this->min_level; $lv <= $this->max_level; $lv++)
		{
			if( isset($this->pd_intervals[$lv]))
			{
				$pdis = $this->pd_intervals[$lv];

				$in = false;

				foreach($pdis as $pdi)
				{
					if( $in = $pdi->inPeriod($date) )
					{
						if($this->debug_mode) echo "yes\n";
						$in = true;
					}
					else
						if($this->debug_mode) echo "no\n";
				}
				if( !$in )
					return false;
			}
		}
		return $in;
	}
	public function nextPeriod($date, $level=-1000)
	{
		// look for the periods of current level then the period of the next level $level+1 inside the current level 
		// and returns the nearest period (nearest in time) found 

		if( $level == -1000)
		{
			if($this->debug_mode) echo "oh shit\n";
			
			$level= $this->min_level;
		 }

		// Level blank, pass to the next
		if($level < $this->max_level && !isset($this->pd_intervals[$level]) )
			return $this->nextPeriod($date, $level+1);

		
		$pdis = $this->pd_intervals[$level];
		$date_intervals = null; // same size as $pdis after the for loop. will stock results of pids lower level 
	
		if($this->debug_mode) echo "LEVEL $level\n";
		
		for($i = 0; $i < count($pdis); $i++)
		{
//			if($this->debug_mode) echo "Level $level\n";
			$pd = $pdis[$i];

			$period = $pd->nextPeriod($date);
			$start=$period["start"];
			$end =$period["end"];
			if($this->debug_mode) echo "NEXT\n";
			//var_dump($period);

			if( $level + 1 > $this->max_level )
				$next = $period;
			else
				$next = $this->nextPeriod( $start, $level+1 );

			//var_dump($next);

			if(! ($next == null || $next > $end))
				$date_intervals[$i] = $next;
			
		}
		$min_index = 0;
		if( isset($date_intervals[0]))
			$min = $date_intervals[0]["start"];

		// Finding the date interval with the nearest start date from the list of the possibilities
		for($i = 0; $i < count($date_intervals); $i++)
		{
			if( $date_intervals[$i]["start"] < $min)
				$min_index = $i;
		}
		if( isset($date_intervals[$min_index]))
			return $date_intervals[$min_index];
		else
			return null;
	}
}


class PeriodicDateInterval
{

	public static $days = array(1=>"Monday",2=>"Tuesday",3=>"Wednesday",4=>"Thursday",5=>"Friday",6=>"Saturday",7=>"Sunday");

	private $level; // 0 for yearly =====> 6 for hourly
	// yearly 0
	// monthly 1
	// weekly 2
	// daily 3
	// hourly 4
	private $type_period; // one of these : W for weekly, Y for yearly, D for daily, H for hourly

	private $debug_mode=false;

	private $start_date;
	private $end_date;
	private $endExclusive;
	public function getLevel()
	{return $this->level;}
	public function __construct($period,$endExclusive=false)
	{
		if($this->debug_mode) echo $period."\n";
		preg_match("#^([MYDWH]|Mw)(.*)-(.*)$#i", $period, $matches);
		if( count($matches) < 3)
			throw new Exception("Period format invalid : '$period'. It has to start with M/Y/D/H, then continues with the start and end dates separated by a -");

		$type = $matches[1];
		if($this->debug_mode)if($this->debug_mode) echo $type."\n";
		$this->type_period = strtoupper( $type );

		$start= $matches[2];
		$end = $matches[3];
		$this->endExclusive = $endExclusive;

		if(stristr($type, "y")) // yearly
		{
			$this->level = 0;
			$day=-1;
			$month=-1;

			if( preg_match("#(^[0-9]+)/([0-9]+)$#i", $start,$matches) ) // format M/d or M
			{
				$month = intval($matches[1]);
				$day = intval($matches[2]);
			}
			else if( preg_match("#^[0-9]+$#i", $start, $matches))
			{
				$month = intval($matches[0]);
				$day = 1;
			}
			else
				throw new Exception("Yearly period format invalid for starting date : '$period', it has to be Month/day or Month, all numeric");
			$this->start_date = array($month,$day);
						
			
			//$this->start_date = new DateTime();
//			$this->start_date->setDate($this->start_date->format("Y"), $month,$day);
			if( preg_match("#^([0-9]+)/([0-9]+)$#i", $end,$matches) ) // format M/d
			{
				$month = intval($matches[1]);
				$day = intval($matches[2])+($this->endExclusive?0:1);
			}
			else if( preg_match("#^[0-9]+$#i", $end, $matches)) // format M seulement
			{
				$month = intval($matches[0]) + ($this->endExclusive?0:1);
				$day = 1;
			}
			else
				throw new Exception("Yearly period format invalid for the end date : '$period', it has to be Month/Day or Month, all numeric ");


			$this->end_date = array($month,$day);
//			$this->end_date = new DateTime();
//			$this->end_date->setDate($this->start_date->format("Y"), $month,$day);

		}
		if(stristr($type,"M")) //monthly
		{
			if( !preg_match("#^[0-9]+$#",$start) || !preg_match("#^[0-9]+$#",$end))
				throw new Exception("Monthly period format invalid : '$period'. It has to be N-N like 1-4 meaning jan to april ");
			$this->level = 1;
			
			$day_start = intval($start);
			$day_end = intval($end);
			if($this->debug_mode)if($this->debug_mode) echo "start $day_start\nend $day_end\n";
			$this->start_date = $day_start;

			$this->end_date = $day_end + ($this->endExclusive? 0:1);
			

//			$d =new DateTime();
//			$d->setDate($d->format("Y"),$d->format("m"),$day_start);
//			$this->start_date = $d;

//			$d->setDate($d->format("Y"),$d->format("m"),$day_end);
//			$this->end_date=$d;
		}
		else if(stristr($type, "W")) //weekly (1-7)
		{
			$this->level = 2;
			
			//$days = array(1=>"Monday",2=>"Tuesday",3=>"Wednesday",4=>"Thursday",5=>"Friday",6=>"Saturday",7=>"Sunday");
			$this->start_date = intval($start);//new Datetime("next ".$days(intval($start)));
			$this->end_date= intval($end);
		}
		else if(stristr($type, "d")) // daily
		{
			$this->level = 3;
			if( !preg_match("#^[0-9]+(:[0-9]+)?$#i",$start,$matches1) || ! preg_match("#^[0-9]+(:[0-9]+)?$#i",$end,$matches2))
				throw new Exception("Daily period format invalid : '$period'. It must be like 19:30-22:30 or 19:15-22");
			$add_twop1 = isset($matches1[1])?"":":00";
			$add_twop2 = isset($matches2[1])?"":":00";

			$this->start_date = $start.$add_twop1;
			$this->end_date = $end.$add_twop2;
		}
		else if(stristr($type,"h")) // hourly
		{
			$this->level = 4;
			$this->start_date = $start;
			$this->end_date = $end;
		}
	}
	public function inPeriod($date)
	{
if($this->debug_mode)if($this->debug_mode) echo		$date->format("Y-m-d H:i:s")."\n";
		switch($this->type_period)
		{
		case "Y":
			$d = new DateTime();
			$startDate = clone $d;
			$startDate->setDate($date->format("Y"),$this->start_date[0], $this->start_date[1]);
			$startDate->setTime(00,00,00);
			$endDate  = clone $d;
			$endDate->setDate($date->format("Y"),$this->end_date[0], $this->end_date[1]);
			$endDate->setTime(00,00,00);

			break;
			
		case "M":
				$startDate = (clone $date);
				$startDate->setTime(00,00,00);
		
				$month_days = $startDate->format("t");
				$month_start_day = $this->correctDayOfMonth($startDate,$this->start_date);

				$startDate->setDate($date->format("Y"),$date->format("m"),$month_start_day);
				$endDate = (new DateTime());
				$endDate->setTime(00,00,00);

				$month_end_day = $this->correctDayOfMonth($startDate,$this->end_date);


				$endDate->setDate($date->format("Y"),$date->format("m"),$month_end_day);
			break;
			
		case "W":
			if($this->debug_mode)if($this->debug_mode) echo $this->start_date."\n".$this->end_date."\n";
				if( $date->format("N") >= $this->start_date )
				{
					if(!$this->endExclusive)
						return $date->format("N") <= $this->end_date;
					else
						return $date->format("N") <= $this->end_date;
				}
			break;
			
		case "D": // daily donc heure de départ et heure de fin
				$startDate = clone $date;
				$startDate->modify($this->start_date);
				$endDate =new DateTime();
				$endDate->modify($this->end_date);
				if( !$this->endExclusive )
				$endDate->modify("+ 1 minute");

				
			break;
			
		case "H":
				$startDate = clone $date;
			    $startDate->modify($date->format("G").":".$this->start_date);
			    $endDate = clone $startDate;
				$endDate->modify($date->format("G").":".$this->end_date);
				if( !$this->endExclusive )
					$endDate->modify("+ 1 minute");
			break;
		
		}
		
if($this->debug_mode)if($this->debug_mode) echo $startDate->format('Y-m-d H:i:s') . "\n".$endDate->format('Y-m-d H:i:s')."\n";
if($this->debug_mode)if($this->debug_mode) echo		$date->format("Y-m-d H:i:s")." \n";

		return $startDate <= $date && $endDate >= $date;

	}
	public function correctDayOfMonth($date, $day)
	{
		$nb_days = $date->format("t");
		if( $day > $nb_days)
			$day = $nb_days;
		return $day;
	}
	public function nextPeriod($date)
	{
		switch($this->type_period)
		{
		case "Y":
			$month_start = $date->format("m");
			$month_end = $this->end_date[0];

			$year_start = $date->format("Y");
			$year_end = $year_start;

			$day_start = $date->format("d");
			$day_end = $this->end_date[1];

			$start = clone $date;

//			if($this->debug_mode) echo  $month_start ." ". $this->start_date[0]."\n";
			if(  $month_start < $this->start_date[0] || ($month_start == $this->start_date[0] && $day_start < $this->start_date[1]	) )
			{
				$day_start = $this->start_date[1];
				$month_start = $this->start_date[0];
				$start->setTime(0,0,0);
			}
			else if( ($month_start >= $this->end_date[0] ) ||( $month_start == $this->end_date[0] && $day_start > $this->end_date[1] ) )
			{
				$year_start++;
				$year_end++;

				$month_start = $this->start_date[0];
				$day_start = $this->start_date[1];
				$start->setTime(0,0,0);
			}

			$start->setDate($year_start, $month_start, $day_start);

			if( $this->end_date[0] == 13 && ($this->start_date[0] <= 1 && $this->start_date[1] <= 1) )
				$end = null; // That means infinite
			else
			{
			$end = new DateTime();
			$end->setDate($year_end,$month_end,$day_end);
			$end->setTime(0,0,0);
			}
			break;
		case "M":
			$day_start = $date->format("d");

			$day_end = $this->end_date;

			$month= $date->format("m");
			$year =$date->format("Y");

			if($this->debug_mode) echo $this->end_date."\n";

			$start = clone $date;

			$endD = clone $date;
			$endD->setDate($year,$month,$day_end);
			$endD->setTime(0,0,0);

			if($date > $endD )
			{
				$d = clone $date;
				$i = $month;
				for($i=$month+1; $i <= 12; $i++)
				{
					$d->setDate($year,$month,$day_start);
					$nb_days_month = $d->format("t");
					if($nb_days_month >= $this->start_date)
					{
						$day_start = $this->start_date;
						$month =$i;
						break;
					}
				}
				$start->setTime(0,0,0);
			}
			else if( $day_start < $this->start_date )
			{
				$day_start = $this->start_date;
				$start->setTime(0,0,0);
			}

			$start->setDate($year, $month,$day_start);

			if( $day_end > $start->format("t") )
				$day_end = $start->format("t");

			if($this->end_date >= 32 && $this->start_date<=1)
				$end = null; // that means infinite
			else
			{
			$end = (clone $date );
			$end->setDate($year,$month,$day_end);
			$end->setTime(0,0,0);
			}
			break;
		case "W":
			$week_day = $date->format("N");

			$day_start =$date->format("d");
			$start = (clone $date);
	//		$start->setTime(0,0,0);

			if( ! $this->inPeriod($date) )
			{
				$start->setTime(0,0,0);
				$plus_days = $this->start_date - $week_day;
				if( $plus_days < 0)
					$plus_days += 7;
				$start->modify("+$plus_days days");
			}
			$plus_days = $this->end_date - $start->format("N");
			$plus_days += $this->endExclusive?0:1;

			if($this->end_date >= 7 && $this->start_date <= 1)
				$end = null;//inf
			else
			{
			$end = (clone $start);
			$end->setTime(0,0,0);
			$end->modify("+$plus_days days");
			}

			break;
		case "D":
			$start = (clone $date);
			$end = (clone $date);

			$startDate= (clone $date);
			$startDate->modify($this->start_date);
			
			$end->modify($this->end_date);

			if( $date > $end)
			{
				$start->modify($this->start_date);
				$start->modify("+1 days");
				$end->modify("+1 days");
			}
			else if($date < $startDate )
			{
				$start->modify($this->start_date);
				
			}
			$test = new DateTime($this->start_date);
			$test2 = new DateTime($this->end_date);
			if($test->format("H")==0 && $test->format("i")==0 && $test->format("s")==0 && $test2->format("w")!=$test1->format("w") )
				$end =null;// inf
			break;
		case "H":
			$start = (clone $date);
			$end = (clone $date);

//			$add_hour=(int)( $date > $end);
			
			$end->setTime($start->format("H") , $this->end_date);
			$add_hour = 0;
			if($date > $end)
			{
				$start->setTime($start->format("H") , $this->start_date  );
				$add_hour = 1;
			}
			else if( intval($date->format("i")) < $this->start_date )
			{
				$start->setTime($start->format("H"), $this->start_date );
			}
			
			$start->modify("+$add_hour hours");
			$end->modify("+$add_hour hours");

			if($this->start_date == 0 && $this->end_date == 60 )
				$end = null;//inf
			
			break;
	
		}
			return array("start"=>$start,"end"=>$end);

	}
}
/*
$d = new DateTime();
$d->modify("2015-01-16 10:00");
if($this->debug_mode) echo "now ".$d->format("Y-m-d H:i:s l")."\n";

//$interv = new DateSchedule("D18-21 H25-35");
//var_dump($interv->inPeriod($d));
//var_dump($interv->nextPeriod( $d  ));


$sc = new DateSchedule("Y02-04 Y8-12 W1-5 D18-21");
//var_dump( $sc->nextPeriod($d)   );


$dates = $sc->nextPeriod($d);
if($this->debug_mode) echo "FOUND !!!!!!!\n";

foreach( $dates  as $name => $date)
{
	
	if($this->debug_mode) echo $date->format("Y-m-d H:i:s l")."\n";
}*/

?>
