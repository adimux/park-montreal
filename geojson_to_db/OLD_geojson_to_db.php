<?php
// GeoPHP library inc : it's useful for converting WKT to WKB
include_once("geoPHP/geoPHP.inc");

// Exception for errors in arguments passed to the script
class ArgumentException extends Exception
{
}
function array_to_object(array $array, $class = 'stdClass')
    {
            $object = new $class;
            foreach ($array as $key => $value)
            {
                    if (is_array($value))
                    {
                    // Convert the array to an object
                            $value = array_to_object($value, $class);
                    }
                    // Add the value to the object
                    $object->{$key} = $value;
            }
            return $object;
    }

function escape($str)
{
	return str_replace("'", "\'",$str);

}
function to_utf8($string)
{
	if(!mb_check_encoding($string, 'UTF-8')
    OR !($string === mb_convert_encoding(mb_convert_encoding($string, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32'))) {

    $string = mb_convert_encoding($content, 'UTF-8'); 
	}
	return $string;
}

// @geometry : the geometry object "extracted" from json file (it contains type and coordinates)
// This function converts geojson coordinates to Well-Known Text
// It can be used in mysql databases
function geojsonCoordinatesToWKT($geometry)
{
	$type = strtoupper($geometry["type"]);
	$depth= 1;

	$coordinates = $geometry["coordinates"];
	
	return $sql = 'GeomFromText(\''.$type . sqlDumpCoords($coordinates).'\')';	
}

// Recursive function dumping arrays following the format of WKT
function sqlDumpCoords($array)
{
	if( !is_array($array))
		return $array;
	else
	{
		$all_values = true; // Check if all elements of arrays are values (not arrays). If they are, we don't have to put the brackets

		for($i =0; $i < count($array); $i++)
		{
			if(is_array($array[$i]))
				$all_values =false;
		}	
	
		
		$toreturn = '';
		for($i =0; $i < count($array); $i++)
		{
			$toreturn .= sqlDumpCoords($array[$i]);
			if($all_values)
				$toreturn .= ' ';
			else
				$toreturn .= ',';
		}
		$toreturn = substr($toreturn,0,strlen($toreturn)-1);

		if(!$all_values)$toreturn = "($toreturn)";
		
		return $toreturn;
	}
}

// Exports a geojson file to a database
try
{
$options = getopt("f:d::o::e:", array("host:","username:","dbname:","password:","table:","port:","primary-key:"));
	
	if( !isset($options["host"] ))
	    	$options["host"] = "localhost"; 
	
	if( !isset($options["d"] )) // Default is mysql database
	    $options["d"] = "mysql"; 
	else 
 		$options["d"] = strtolower($options["d"]); 
	if( !isset($options["f"] ))
		throw new ArgumentException("You did not indicate a file with -f option");

	if(!$options["primary-key"])
		$options["primary-key"] = '';
	
	$connec_options = '';

	$ismysqlutf8 = true;
	if( strcasecmp($options["e"], "utf8"))
		$ismysqlutf8 = true;
	else if( strcasecmp($options["e"],"iso-8559-1"))
		$ismysqlutf8 = false;
	
	if( $options["host"])$connec_options.="host=".$options["host"].";";

	if( $options["dbname"] )$connec_options.="dbname=".$options["dbname"].";";
	else throw new ArgumentException("Please indicate dbname with --dbname \"database name\"");
	
	if( $options["port"])$connec_options.="port=".$options["port"].";";
	else $options["port"]=3306;
	
	$connec_options = substr($connec_options,0,strlen($connec_options)-1);

	echo $options["d"].":".$connec_options." ".$options["username"]. " ".$options["password"];
	
	$pdo_options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION); // Activate exceptions as error mode
	if( $ismysqlutf8 )
		$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND]    = "SET NAMES utf8"; // Set database connection as utf8

	$conn = new PDO($options["d"].":".$connec_options, $options["username"], $options["password"], $pdo_options);
	$conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

	if( $options["table"])
		$tablename = $options["table"];
	else
	{
		$tablename = basename($options["f"]);
		$tablename = substr($tablename, 0, strrpos($tablename,".") );
	}
	
	$json_data = json_decode(file_get_contents( $options["f"] ),true); // true : to convert to an assoc array


	// If option "o" given, we run through json_data until we find the deeper object we want to export
	if(  $options["o"] ) // option -o "obj1=>obj2=>obj3" indicates the precise json obj to export to the database
		$tree = explode("=>",$options["o"]);

	// $data_to_fetch is the data (array) that we have to export to mysql. It can be the original json_data or a more deep object as indicated by option "a"
	$data_to_fetch = $json_data;
	
	for($i=0; $i < count($tree);$i++)
	{
		if($tree[$i]!='')
		$data_to_fetch = $data_to_fetch($tree[$i]);
	}

	$columns = array(); // Containing columns names associated with the type and size. Will be filled when reading the first feature (geojson feature)

	/****** Now that we have the correct object : $data_to_fetch, we'll begin exporting to the db ******/

	$features = array(); // the features array

	if( !isset($data_to_fetch["type"]) )
	{
		throw new Exception("This object is not a feature or feature collection");
	}
	else if( $data_to_fetch["type"] == "Feature") // It can be just a single feature in a geojson
	{
		$features[] = $data_to_fetch;
		unset($features[0]["type"]);
	}
	else if( $data_to_fetch["type"] == "FeatureCollection") // Or a feature collection
	{
		if( !isset($data_to_fetch["features"]))
			throw new Exception("This feature collection does not contain the features array");
		$features = $data_to_fetch["features"];
	}
	else if( $data_to_fetch["type"] )
	{
		throw new Exception("This object is not a feature or feature collection");
	}
	for($i = 0; $i < count($features);$i++)
	{
		$feature = $features[$i];
		// First iteration, determine columns names and types and also primary index then create the mysql table
		if( $i == 0 ) // Fill the columns array (with names and types)
		{
			if( isset($feature["geometry"]))
			{
				if( isset($feature["geometry"]["type"]))
				{
					$columns["coordinates"] = array( "type"=>$feature["geometry"]["type"], "notnull"=>true, "size"=>false,"primary"=>($options["primary-key"]=='coordinates') );
					echo "Column 'coordinates'\n";
					var_dump($columns["coordinates"]);
					echo "\n";
				}
			}
			if( isset($feature["properties"]))
			{
				foreach($feature["properties"] as $feature_property_name => $value )
				{
					$columns[$feature_property_name] = array();
					// Determine the property type from the value
					$column_type = null;

					if( preg_match("#[^-.0-9]#i",$value) )  // If we find anything that's not a number then the type is VARCHAR or DATE
					{
						if( preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$#i",$value) ) // if it is in the mysql date format
						{
							$column_type = "DATE";
							$columns[$feature_property_name]["date_format"] = "SQLDATE"; // We add a field precising that it is a correct date format that doesn't need to be converted in order to add it to the db
						}
						else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{2}:[0-9]{2}(:[0-9]{2})?$#i",$value) ) // mysql date time format
						{
							$columns[$feature_property_name]["date_format"] = "SQLDATE"; // same thing here
							$column_type = "DATETIME";
						}
						else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+$#i",$value) ) // mysql date time format
                        			{   
			                            $columns[$feature_property_name]["date_format"] = "SQLDATE"; // same thing here
	        	        	            $column_type = "MICROSECONDS";
        	        	        	}
						else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]+Z$#",$value))
						{
							$column_type ="MICROSECONDS";
							$columns[$feature_property_name]["date_format"] = "ISO8601";
						}
						else if(preg_match("#^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$#",$value))
			                        {
	        	                	    $column_type ="DATETIME";
        	        		            $columns[$feature_property_name]["date_format"] = "ISO8601";
						}
        			                // If it's a not date then it's a varchar
						else
							$column_type = "VARCHAR";
					}
					else
						$column_type = "INT";
	
					if( $feature_property_name == "DATE_CONCEPTION_POT") // ;)
					{
						$column_type = "DATETIME";
						$columns[$feature_property_name]["date_format"] = "MONTREAL_CREEPY_DATE";
					}

					$size=false;

					if($column_type == "VARCHAR")
						$size = 255;

					$columns[$feature_property_name]["type"]=$column_type;
					$columns[$feature_property_name]["size"]=$size;
					$columns[$feature_property_name]["primary"]=($options["primary-key"]==$feature_property_name);
					$columns[$feature_property_name]["notnull"]=($columns[$feature_property_name]["notnull"]||$options["primary-key"]==$feature_property_name);

					echo "Column '$feature_property_name'\n";
					var_dump($columns[$feature_property_name]);
					echo "\n";
				}
			}
			/**********Create the table ********/
			$query = 'CREATE TABLE IF NOT EXISTS `'.$tablename.'` ( ';
			$theprimarykey = '';
			foreach($columns as $column_name => $prop)
			{

				$query.=''.$column_name . ' '; // add column name plus comma
				$query.=$prop["type"].($prop["size"]!=false ? "(".$prop['size'].")" :'' ).' '.($prop['notnull']?" NOT NULL ":"") .' '; // add column size
				if( $prop["primary"] == true  ) // add "primary key" if it is actually the primary key
					$theprimarykey = $column_name;
				//$query.=$prop["primary"] ? "PRIMARY KEY" :"";
				$query.=',';
			}
			$query = substr($query,0,strlen($query)-1);
			$query .= ($theprimarykey!=''? ",PRIMARY KEY($theprimarykey)" : '').')';
			
			echo "Creating the table : ".$query."\n";

			// Executing the query 
			$conn->query($query);
		}
		// First and other iterations
		// insert the feature into the table
		
		// Filling columns and values array to create the insert query
		$columns_and_values = array();
		// take the geometry object and convert it to wkb
		if( isset($feature["geometry"]) )
		{
			$processor = new GeoJSON(); // The converter we'll use to convert to wkb (binary format)
			$jsongeometry = $feature["geometry"]; // It's an assoc array, we have to convert it to an object with type and coordinates attributes, type is a string, coordinates is an array
			$geometryobject = new stdclass();

			$geometryobject->type = $jsongeometry["type"];
			$geometryobject->coordinates = $jsongeometry["coordinates"];
			
			$geom = $processor->read($geometryobject);
			$wkb = new WKB();
			$columns_and_values["coordinates"] = "GeomFromWKB(x'".$wkb->write($geom,true)."')"; // true : write as hex
		}
		// take other properties so they would be the other fields
		if( isset($feature["properties"]))
		{
			foreach($feature["properties"] as $name => $val)
			{
				$value = ($ismysqlutf8 && $columns[$name]["type"] == "VARCHAR") ? to_utf8($val) : $val;
//				$value = utf8_dcode($value);
				$columns_and_values[$name] = $conn->quote($value); // PDO->quotes adds quotes as well as escaping the string
			}
		}
		

		// Preparing the pdo statement
		$query = 'INSERT IGNORE INTO `'.$tablename.'` (';
		//$statement = $pdo->prepare('INSERT IGNORE INTO `'.$tablename.'` (');
		
		if( count($columns_and_values))
		{
			$values = '';
			
			foreach($columns_and_values as $column => $val)
			{
				$query.= "$column,";
//				if (preg_match("#^(LineString|MultiPolygon|Polygon|Geometry|GeometryCollection|Point|MultiLineString)$#",$columns[$column]["type"]) )
//					$values .= "GeomFromWKB($val), ";
//				else
					$values .= "$val,"; // in pdo statement, we put :nameofval
			}
			
			$values = substr($values,0,strlen($values)-1);
			$query = substr($query,0,strlen($query)-1) . ') VALUES('.($values).')';
			/*
			$statement = $conn->prepare($query);
			foreach($columns_and_values as $column => $val)
			{
			echo $column . " : ".$val."\n";
	//			if( preg_match("#^(LineString|MultiPolygon|Polygon|Geometry|GeometryCollection|Point|MultiLineString)$#",$columns[$column]["type"])  )
	//				$paramtype = PDO::PARAM_LOB;
				if(preg_match("#.*INT$#i", $columns[$column]->type) )
					$paramtype = PDO::PARAM_INT;
				else
					$paramtype = PDO::PARAM_STR;
//echo "$column is : ". (int)($paramtype == PDO::PARAM_STR)."\n";
//				if( ! preg_match("#^(LineString|MultiPolygon|Polygon|Geometry|GeometryCollection|Point|MultiLineString)$#",$columns[$column]["type"])  )
				$statement->bindParam(":".$column, $val, $paramtype);
			}
			*/
			echo $query."\n";
			file_put_contents("merde.txt",$query);
	        $conn->query($query);
			/*try
			{
			$statement->execute();
			}catch(Exception $e)
			{
				echo "Caught Exception (".$e->getMessage.")\n$e\n";
exit(0);
			}*/
		}
		
	}

	echo "Table $tablename CREATED !\n";
	echo "And all entries are there\n";
	echo "Don't forget to use a spatial index for fast searching in coordinates :\n";
	echo "The SQL command : ALTER TABLE $tablename ADD SPATIAL INDEX(coordinatesColumn);\n";
	echo "But, before define the coordinates column as NOT NULL\n";
	
//	$conn->close();
}
catch(ArgumentException $e)
{
    echo "\nError : " . $m."\n";
    echo $additional_info;
    echo "\n";
    echo "Reminder of usage :\n";
    echo "php geojson_to_db.php -f \"uri or file path\" --dbname \"database name\" --username \"user\" --password \"pass\" (--host \"hostname\" --port \"port\")\n";
    echo "The options between brackets are optionnal since default hostname is localhost and default port is 3306\n";
    echo "Other options :\n";
    echo "-d \"database type\" : By default it is MySQL but you can precise it\n";
    echo "--table \"table name\" : Precise table name. By default the table name is the file name\n";
    echo "--primary-key \"field name\" : Precise which field is a primary key. By default it is the coordinates field\n";
    echo "-o obj1=>obj2=>obj4 etc.: to indicate a deeper object to export rather than the main json object\n";

}

catch(Exception $e)
{
        $m = $e->getMessage();
        $additional_info = "";

        if($m =="SQLSTATE[HY000] [2002] No such file or directory")
                $additional_info .= "Try to give the host ip address rather than the domaine name\n";
}
?>
