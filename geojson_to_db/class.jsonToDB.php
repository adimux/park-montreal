<?php
require_once("JParser/class.JParser.php");

class ParameterException extends Exception
{}

//ini_set("memory_limit","140M");
// class jsonToDB
// Constructor takes as parameter a pdo connection, a json file path or uri or json raw data, a boolean isFileOrData then the array of primary keys
// And exports data to database
// To use it :
// $exportdata = new jsonToDB($pdo_connection, "file/path", true, array('primarykey_name'));
// $exportdata->createTable("mytable");
// $exportdata->insertEntries(); // uses insert ignore
//
// As simple as that !
// Other functions :
// setPrimaryKeys(array @primarykeys)
// addIndex(string $indexName, string $columnNames)
// setColumn(string $columnName, array $properties) // changes a column propeties like type, not null, etc.


class jsonToDB
{
	protected $columns; // array of objects DBColumn
	protected $file; // URL/Path to the json file

	protected $jsonobj; // json obj (class Jsonobj)
	protected $raw_data; // raw json (if we're using normal JParser not BigJParser)

	protected $tablename;
	protected $connection; // PDO
	protected $primaryKeys; // Array containing names of primary key(s)
	protected $indexes; // Array containing columns' names that have to be indexes in the table

	protected $useBigJParser;

	// The constructor takes the file uri/path and 
	public function __construct($connection, $fileordata='', $isFile=true, $options=array())
	{
		$this->connection = $connection;


		$this->load($fileordata, $isFile, $options);
	}
	protected function deleteSpaces($str)
	{
		return str_replace(" ", "",$str);
	}
	protected function extractCorrectData($fileOrData,$isFile)
	{
		if( !$this->useBigJParser )
		{
                if( $isFile )
                {
                        $this->file = $fileOrData;
	                      $this->raw_data = file_get_contents($fileOrData);
                }
                else
				{
	                $this->file = null;
					$this->raw_data = $fileOrData;
				}
				$this->jsonobj = JParser::open( array("data"=>$this->raw_data));
		}
		else 
		{
			if( $isFile)
			{
				if( is_string($fileOrData) )
					$this->jsonobj = JParser::open(array("filepath"=>$fileOrData,"useBigJSONobj"=>true,"memlimit"=>$this->memlimit));
				else if ( is_resource($fileOrData))
					$this->jsonobj  = JParser::open(array("file"=>$fileOrData,"useBigJSONobj"=>true,"memlimit"=>$this->memlimit) );
			}
			else
				throw new ParameterException("Can't use raw data with a BigJParser");

		}
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
	}
	public function setPrimaryKeys($primaryKeys =array())
	{
	        if( !(is_array($primaryKeys)))
        	    $primaryKeys = array($primaryKeys);
	        $this->primaryKeys = $primaryKeys;
	}

	protected function fetchColumns()
	{
		$this->columns = array();

		if( $this->jsonobj->isBigJSONobj() )
			$this->jsonobj->fetch(1);

//		if( $this->jsonobj->size() < 1) return;
		//$this->jsonobj->dump();
		$ch=$this->jsonobj->nextChild();
		
		var_dump($ch);

		if($ch!== null)
			$item = $ch->getJSON(true);
		else
			return false;

		$this->jsonobj->setIndexChilds(0); 
		
		foreach($item as $columnName => $value)
			$this->columns[$columnName] = $this->determineDBColumn($columnName,$value);
	//	var_dump($this->columns);
	}
	public function addIndex($indexName, $columns)
	{
		$this->indexes[$indexName] = $columns;
	}
	public function addIndexesOnTable()
	{
        foreach($this->indexes as $indexName => $columns)
        {
	        $query = "CREATE INDEX $indexName ON $tablename (";
			$query.= implode(",",$columns); // Columns names will be comma separated
	        $query.= ')';	

            $this->connection->query($query); // add index
		}

	}
	protected function determineDBColumn($columnName, $value)
	{
		$column = new DBColumn($columnName);
		$column->name = $columnName;
		$column_type = null;
		$column_type = DBColumn::TYPE_VARCHAR; // par défaut

		if( is_array($value))
		{
			if( isset($value["type"])) //It can be a geom
			{
				$t=  strtoupper($value["type"]);
				if( DBColumn::textTypeToID($t))
				{
					$column_type = DBColumn::textTypeToID($t);
				}
			}
		}

		else if( preg_match("#[^-.0-9]#i",$value) )  // If we find anything that's not a number then the type is VARCHAR or DATE
		{
			if( preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$#i",$value) ) // if it is in the mysql date format
			{
				$column_type = DBColumn::TYPE_DATE;
				$column->additional_informations["date_format"] = "SQLDATE"; // We add a field precising that it is a correct date format that doesn't need to be converted in order to add it to the db
			}
			else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{2}:[0-9]{2}(:[0-9]{2})?$#i",$value) ) // mysql date time format
			{
				$column->additional_information["date_format"] = "SQLDATE"; // same thing here
				$column_type = DBColumn::TYPE_DATETIME;
			}
			else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+$#i",$value) ) // mysql date time format
			{
				$column->additional_information["date_format"] = "SQLDATE"; // same thing here
				$column_type = DBColumn::TYPE_MICROSECONDS;
				// SQL microseconds Date example : 2014-10-10 14:45:30.1212215
			}
			else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+Z$#",$value))

			{
				$column_type =DBColumn::TYPE_MICROSECONDS;
				$column->additional_information["date_format"] = "ISO8601";
				// ISO8061 date format example (for microseconds) : 2014-05-05T21:25:10.15454545
			}
			else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$#",$value))
			{
				$column_type =DBColumn::TYPE_DATE;
				$column->additional_information["date_format"] = "ISO8601";
			}
			// If it's a not date then it's a varchar
			else
				$column_type = DBColumn::TYPE_VARCHAR;
		}
		else if(preg_match("#^-?[0-9]+$#i",$value))
			$column_type = DBColumn::TYPE_INT;
		else if(preg_match("#^-?[0-9.]+$#i",$value))
			$column_type = DBColumn::TYPE_DOUBLE;

		if( $columnName == "DATE_CONCEPTION_POT") // ;)
		{
			$column_type = DBColumn::TYPE_DATETIME;
			$column->additional_information["date_format"] = "MONTREAL_CREEPY_DATE";
		}

		$size=null;

		if($column_type == DBColumn::TYPE_VARCHAR)
			$size = 255;

		$column->setType($column_type);
		$column->size=$size;
		$column->isPrimary=(array_search($columnName, $this->primaryKeys) != false);
		$column->notnull=($column->notnull || array_search($column->name,$this->primaryKeys));
		$column->isKey = (array_search($columnName,$this->indexes) != false);

		echo "Column '$columnName'\n";
		return $column;
	}

	// Create the table with the right columns types and sizes
	public function createTable($tablename='')
	{
		if($tablename != '')$this->setTable($tablename);
		else return;

		if( count($this->columns) <= 0)
			throw new Exception("No columns to add a table");

		/**********Create the table ********/
		$query = 'CREATE TABLE IF NOT EXISTS `'.$this->tablename.'` ( ';
		$theprimarykey = '';
		foreach($this->columns as $column_name => $prop)
		{
			$query.=''.$column_name . ' '; // add column name plus comma
			$query.=$prop->getSqlTextType().($prop->size!=false ? "(".$prop->size.")" :'' ).' '.($prop->notnull?" NOT NULL ":"") .' '; // add column size
			if( $prop->isPrimary == true  ) // add "primary key" if it is actually the primary key
				$theprimarykey = $column_name;
			//$query.=$prop["primary"] ? "PRIMARY KEY" :"";
			$query.=',';
		}
		$query = substr($query,0,strlen($query)-1);
		$query .= ($theprimarykey!=''? ",PRIMARY KEY($theprimarykey)" : '').')';

		echo "Creating the table : ".$query."\n";
		// Executing the create table query 
		$this->connection->query($query);

		// Add indexes ($this->indexes contains them)
		$this->addIndexesOnTable();
	}
	// Insert entries to the table
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
			$item = $ch->getJSON(true);

			$this->insertEntry($item);
				
			unset($ch);
		}}while($fetchRunning);
	}
	public function insertEntry($columns_and_values)
	{
			// getting correct quoted values that we can add to a sql database
			$columns_and_values = $this->correctValuesForDatabase($columns_and_values);
			$values = '';
			$query = 'INSERT IGNORE INTO `'.$this->tablename.'` (';

			foreach($columns_and_values as $column => $val)
            {
				// fetching columns and values 
            	$query.= "$column,";
				$values .= "$val,";
			}
			$values = substr($values,0,strlen($values)-1);
            $query = substr($query,0,strlen($query)-1) . ') VALUES('.($values).')';

			echo $query ."\n";


			$this->connection->query($query);
	}

	// Fetches values in @arr, matching columns names with their correct values (sometimes needs to be converted to sql proper format)
	// @arr : a sub array of the root array in json file
	protected function correctValuesForDatabase($arr)
	{
		// Filling columns and values array to use in the insert query
		$columns_and_values = array();
		foreach($arr as $columnName => $value)
		{
			if( isset($this->columns[$columnName]) )
			{
				$column = $this->columns[$columnName];
				$columns_and_values[$columnName] = $this->correctValueForDatabase($column,$value);
			}
		}
		return $columns_and_values;
	}
	// Params :
	// DBColumn $column 
	// string $value
	//
	// This function returns a quoted and corrected value that can be add to a database within the query
	protected function correctValueForDatabase($column,$value)
	{
		$correctedValue = $value;

		if( $column->getGeneralType() == DBColumn::TYPE_SPATIAL)
		{
				$processor = new GeoJSON(); // The converter we'll use to convert to wkb (binary format)
				//$jsongeometry = $feature["geometry"]; // It's an assoc array, we have to convert it to an object with type and coordinates attributes, type is a string, coordinates is an array
/*				$geometryobject = new stdclass();
				$geometryobject->type = $column->getSqlTextType();
				$geometryobject->type 
					$geometryobject->coordinates = $value;*/

				$geom = $processor->read($value);
				$wkt = new WKT();
				$correctedValue = "GeomFromText('".($wkt->write($geom))."')"; 
		}
		else
		{
			$correctedValue = ($ismysqlutf8 && $column->getGeneralType() == DBColumn::TYPE_TEXT) ? to_utf8($value) : $value;
			$correctedValue = $this->connection->quote($correctedValue); // PDO->quotes adds quotes as well as escaping the string
		}
		return $correctedValue;
	}

	// Exports json data to the table @table
	// 1. Creates the table if not exists
	// 2. Insert the entries
	public function export($table, $start=0, $count=-1)
	{
		$this->createTable($table);
		$this->insertEntries($start,$count);
	}

// Getters and Setters
	public function setTable($tablename)
	{
		$this->tablename = $tablename;
	}
	public function getTable()
	{return $this->tablename;}
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }


    public function getColumns()
    {
        return $this->columns;
    }
    public function getColumn($name)
    {
        return $this->columns[$name];
    }

    // setColumn($name, $properties) changes properties of a database column
    // (just in the this->columns array, not in the server) 
    // @name : name of column
    // @properties : properties to be changed (like is primary key, the type of column, etc.)
    public function setColumn($name, $properties)
    {
        if( !isset($this->columns[$name]))throw new ParameterException("Column $name does not exist");

        foreach($properties as $prop => $val)
        {
			if($prop == "type")
				$this->columns[$name]->setType($val);
            else if( isset($this->columns[$name]->$prop))
                $this->columns[$name]->$prop = $val;
        }
    }

}

class DBColumn
{
	public $type;

	public function __construct($name)
	{
		// Set type to unknown
		$this->type = DBColumn::TYPE_UNKNOWN;
		$this->name = $name;
	}
	// Returns the general type like number, date or text. It is just for the purpose of knowing which general type it belongs to (this can be good for knowking that it is a number, without testing if type is float or int or bigint or smallint, etc.)
	public function getGeneralType()
	{
		return $this->type & 0b00001111;  // $type & 0000 1111 to have only the general type  (the four bits right)
	}
	public function getType()
	{
		return $this->type;
	}
	// Set the type (always the specific type)
	public function setType($type)
	{
		if( is_string($type) )
		{
			if( isset(self::$sqlTextTypes[$typeName]))
				$this->type = self::$sqlTextTypes[$typeName];
		}
		else if( is_numeric($type) )
		{
			foreach(self::$sqlTextTypes as $typeName => $valuebin)
			{
				if($type == $valuebin) 
				{
					$this->type = $valuebin;
					break;
				} 
			}
		}
	}
	public function getSqlTextType()
	{
		foreach(self::$sqlTextTypes as $typeName => $valuebin)
		{
			if( $this->type == $valuebin)
				return $typeName;
		}
		return "";
	}
	public function getTextGeneralType()
	{
		foreach($this->textGeneralTypes as $typeName => $valuebin)
		{
			if( $this->getGeneralType() == $valuebin )
				return $typeName;
		}
		return "";
	}
	public function textTypeToID($text_type)
	{
		return self::$sqlTextTypes[$text_type];
	}

	public $name;
	public $size;
	public $notnull;
	public $isPrimary;
	public $isKey;
	public $additional_informations; // Additional informations (is an array) may contain some informations about the origin
	// format of this column value (origin in the json file)
	// Example, a date written like that in json : 20140502000000 have to be converted to sql proper date format)

	// General types
	//  Number 0000 0001
	//  Date   0000 0010
	// Spatial 0000 0011
	// Text    0000 0100
	// Unknown 0000 0101
	const TYPE_NUMBER = 0b00000001;
	const TYPE_GENERAL_DATE = 0b00000010;
	const TYPE_SPATIAL = 0b00000011;
	const TYPE_TEXT = 0b00000100;
	const TYPE_UNKNOWN = 0b00000000;

	// Specific types (four bits left specifies the specific type, four bits right are for general type)
	const TYPE_DATE =0b00010010; // 0001 0010
	const TYPE_DATETIME = 0b00100010; // 0010 0010
	const TYPE_MICROSECONDS = 0b00110010; // 0011 0010
	const TYPE_VARCHAR = 0b01000100; // 0100 0100
	const TYPE_INT = 0b01010001; // 0101 0001
	const TYPE_DOUBLE = 0b01100001; // 0110 0001
	const TYPE_LINESTRING = 0b01110011; // 0111 0011
	const TYPE_MULTIPOINT = 0b10000011; // 1000 0011
	const TYPE_MULTIPOLYGON = 0b10010011; // 1001 0011
	const TYPE_POLYGON = 0b10100011; // 1010 0011
	const TYPE_POINT = 0b11000011; // 1011 0011

	protected static $sqlTextTypes = array("UNKNOWN TYPE"=>DBColumn::TYPE_UNKNOWN, "DATE"=>DBColumn::TYPE_DATE, "DATETIME"=>DBColumn::TYPE_DATETIME, "MICROSECONDS"=>DBColumn::TYPE_MICROSECONDS,
	"VARCHAR"=>DBColumn::TYPE_VARCHAR, "INT"=>DBColumn::TYPE_INT, "DOUBLE"=>DBColumn::TYPE_DOUBLE, "LINESTRING"=>DBColumn::TYPE_LINESTRING,
	"MULTIPOINT"=>DBColumn::TYPE_MULTIPOINT , "MULTIPOLYGON"=>DBColumn::TYPE_MULTIPOLYGON, "POLYGON"=>DBColumn::TYPE_POLYGON,
	"LINESTRING"=>DBColumn::TYPE_LINESTRING, "POINT"=>DBColumn::TYPE_POINT  );

	protected static $textGeneralTypes = array("UNKNOWN_TYPE"=>DBColumn::TYPE_UNKNOWN,"IS DATE"=>DBColumn::TYPE_GENERAL_DATE, "IS SPATIAL"=>DBColumn::TYPE_SPATIAL, "IS TEXT"=>DBColumn::TYPE_TEXT);
}

?>
