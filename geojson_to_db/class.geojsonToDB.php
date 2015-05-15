<?php

include_once("class.jsonToDB.php");

class geojsonException extends Exception{};

// geojsonToDB
// Takes in parameter a pdo connection and a geo json file path or uri or json raw data 
// And exports data to database
// To use it :
// $exportdata = new jsonToDB($pdo_connection, Uri or file path, or raw data : "file/path", $isFile : true, $primaryKeys : array('primarykey_name'));
//
// $exportdata->createTable("mytable");
// $exportdata->insertEntries(integer $start=0, integer $count=-1);
//
// Or just : $exportdata->export("mytable", integer $start=0, integer $count=-1)
//
// As simple as that !
// Other functions :
// 
class geojsonToDB extends jsonToDB
{
/*	public function __construct($connection, $fileordata='', $isFile=true, $options=array())
	{
		$this->connection = $connection;

		$this->load($fileordata, $isFile, $options);
	}
	public function load($fileOrData='', $isFile=true, $options)
	{
		// $options are :
		// {
		// useBigJParser : boolean
		//     true : use BigJParser to handle the json file (it doesn't load the file entirely in the ram)
		//     false : don't use BigJParser
		//     this option is not taken in account if isFile is set to false
		//
		// primaryKeys : array
		//	   lists primary key names
		//
		// memlimit : exprimed in octets, the max amount of memory that we can use
		// }
		//
		//
		$default_options =array("useBigJParser"=>false,"memlimit"=>-1, "primaryKeys"=>array() );
		$options = array_merge($default_options,$options);

		$this->memlimit = $options["memlimit"];

		$this->useBigJParser  =$options["useBigJParser"];

		$this->extractCorrectData($fileOrData, $isFile);

		$this->setPrimaryKeys($options["primaryKeys"]);

		$this->indexes = array();

		$this->fetchColumns(); // fetch columns names and properties like not null, type, etc.
	}*/
	public function load($fileordata,$isFile=true,$options=array())
	{
	
		parent::load($fileordata,$isFile,$options);
	}


	protected function extractCorrectData($fileordata, $isFile=true)
	{
		parent::extractCorrectData($fileordata, $isFile);

		if( $this->jsonobj->isBigJSONobj() )
			$this->jsonobj->fetch();
//		$this->jsonobj->activateDebugMode();
	        if( !$this->jsonobj->hasChild("type") )
			{
	                throw new geojsonException("This object is not a feature or a feature collection");
        	}
	        else if( $this->jsonobj->getChild("type")->getValue(true) == "Feature") // It can be just a single feature in a geojson
        	{
        	}
	        else if( $this->jsonobj->getChild("type")->getValue(true) == "FeatureCollection") // Or a feature collection
        	{
	                if( !$this->jsonobj->hasChild("features") )
						throw new geojsonException("This feature collection does not contain the features array");
			
					$this->jsonobj = $this->jsonobj->getChild("features");
	        }
        	else
	        {
        	        throw new geojsonException("This object is not a feature or feature collection");
			}

	}
	protected function fetchColumns()
	{
		$this->columns = array();

		echo "hllo sir\n";
		if( $this->jsonobj->isBigJSONobj() )
			$this->jsonobj->fetch(1);

		echo "fetching feature\n";
		$feature = $this->jsonobj->nextChild();
		$feature->fetch();
		// Each row is a geometry feature in geojson
	
		if( $feature->hasChild("geometry"))
		{
			$geom=$feature->getChild("geometry");$geom->fetch();
			if($geom->hasChild("coordinates"))
				$this->columns["coordinates"] = $this->determineDBColumn("coordinates", $feature->getChild("geometry")->getJSON(true) );
		}
		if( $feature->hasChild("properties"))
		{
			$props = $feature->getChild("properties")->getJSON();
			foreach($props as $columnName => $value)
			{
				$this->columns[$columnName] = $this->determineDBColumn($columnName,$value);
			}
		}
		echo "Finish fetch cols\n";

		$this->jsonobj->setIndexChilds(0);
	}
	public function insertEntries($start=0,$count=-1)
	{
			//		if( count($this->data) < $start)return;
//		$this->jsonobj->loadState(0);
//		$this->jsonobj->fetch();
		if( $start > 0 && $this->jsonobj->isBigJSONobj() )
			$this->jsonobj->fetch($start, -1,false);


//		echo "FINISH\n";
//		echo $this->jsonobj->getSurroundingChars()." ".$this->jsonobj->getPosFile() ."\n";
//		exit(0);

		// Fetching every row in the json file and adding data to sql database
		do
		{
			if( $this->jsonobj->isBigJSONobj())
				$fetchRunning = $this->jsonobj->fetch();
			else
				$fetchRunning = false;
		for($i=$start; (($ch= $this->jsonobj->nextChild())!==null ) && (($count != -1 && $i < $start + $count)||$count==-1 ); $i++)
		{
			$item = $ch->getJSON(); /// not associative array because the geophp lib does not want it
			$cols_and_vals = (array)$item->properties;
			$cols_and_vals["coordinates"]=$item->geometry;
			$this->insertEntry($cols_and_vals);
				
			unset($ch);
		}}while($fetchRunning);

	}
	protected function determineDBColumn($columnName, $value)
	{
		$column = parent::determineDBColumn($columnName, $value);

		if( isset($value["type"]) && isset($value["coordinates"])) // IF spatial data
		{
			if( isset($value["type"]))
				$column->setType($value["type"]); // set the precise type (linestring or polygon, etc.)
			$column->not_null =true; // let it be not null
		}
		return $column;
	}

	// Fetches values in @arr, matching columns names with their correct values (sometimes needs to be converted to sql proper format)
	// @arr : a sub array of the root array in json file
	protected function correctValuesForSQL($arr)
	{
		// Filling columns and values array to use in the insert query
		$columns_and_values = array();

		$feature = $arr;
		if( isset($feature["geometry"])&&isset($feature["geometry"]["coordinates"]))
		{
			$column = $this->getColumn($columnName);
			if($column)
	            $columns_and_values["coordinates"] = correctValueForDatabase($column);

		}
		if( isset($feature["properties"]))
		{
			foreach($featureProperties as $columnName => $value)
			{
				if( isset($this->columns[$columnName]) )
				{
					$column = $this->getColumn($columnName);
					if($column)
						$columns_and_values[$name] = correctValueForDatabse($column, $value);
				}
			}
		}
		return $columns_and_values;
	}
}
?>
