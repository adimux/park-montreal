<?php
// GeoPHP library inc : it's useful for converting WKT to WKB
include_once("geoPHP/geoPHP.inc");
include_once("class.geojsonToDB.php");

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
function load_config($f)
{
	$d= json_decode(file_get_contents($f),true);
	return $d;
}

// Exports a geojson file to a database
try
{
$options = getopt("f:d::o::e:", array("host:","username:","dbname:","password:","table:","port:","primary-key:","jsontype:","conf:","geojson:","start:","count:"));
$config_file = $options["conf"];
	if( $config_file )
		$options = array_merge(load_config($config_file),$options);

if( !$options["start"] )$options["start"]=0;
if (!$options["count"] )$options["count"]=-1;




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

	echo $options["d"].":".$connec_options." ".$options["username"]. " ".$options["password"]."\n";
	
	$pdo_options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION); // Activate exceptions as error mode
	if( $ismysqlutf8 )
		$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND]    = "SET NAMES utf8"; // Set database connection as utf8

	echo "File : ";
	echo $options["f"]."\n";

	echo "Connecting to database...\n";
	
	$conn = new PDO($options["d"].":".$connec_options, $options["username"], $options["password"], $pdo_options);
	$conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

	if( $options["table"])
		$tablename = $options["table"];
	else
	{
		$tablename = basename($options["f"]);
		$tablename = substr($tablename, 0, strrpos($tablename,".") );
	}
	echo "Table : ".$tablename."\n";

	if( $options["geojson"] )
	{
		echo "It's ".($jsontype = "geojson")."\n";
		 }
	else
		$jsontype = "json";
	$file = $options["f"];

	echo "FILE $file\n";

	echo "MEMLIMIT : ".	$memlimit = $options["memlimit"]."\n";
	
	$useBigJParser = (filesize($file) > 10000000); // More than 10 mo we'll use BigJParser that do not loads the file in the ram
$useBigJParser=true;
	echo "Loading json parser and exporter...\n";

	if( $jsontype == "json" )
		$exportdata = new jsonToDB($conn, $file, true, array("primaryKeys"=>$options["primary-key"],"useBigJParser"=>$useBigJParser,"memlimit"=>$memlimit) );
	else if($jsontype == "geojson" )
	{
		echo "------------- GEOJSON\n";
		$exportdata = new geojsonToDB($conn, $file, true, array("primaryKeys"=>$options["primary-key"],"useBigJParser"=>$useBigJParser,"memlimit"=>$memlimit)	);
	}
	else
		throw new ArgumentException("Json file type $jsontype isn t recognized");
	
	echo "Beginning export.\n";
	echo "Creating the table...\n";
//	$exportdata->createTable('sign-descrip-panneau');
	//	xit(0);
	//
	$start = $options["start"];
	$count =$options["count"];
	$exportdata->export( $tablename ,$start,$count);


	echo "Table $tablename CREATED !\n";
	echo "And all entries are there\n";
	echo "Don't forget to use a spatial index for fast searching in coordinates :\n";
	echo "The SQL command : ALTER TABLE $tablename ADD SPATIAL INDEX(coordinatesColumn);\n";
	echo "But, before define the coordinates column as NOT NULL\n";
	
//	$conn->close();
}
catch(ArgumentException $e)
{
	echo "\nError : " . $e->getMessage()."\n";
	echo $e->getTrace();
//    echo $additional_info;
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
	echo $e->getTraceAsString();
        $additional_info = "";

        if($m =="SQLSTATE[HY000] [2002] No such file or directory")
                $additional_info .= "Try to give the host ip address rather than the domaine name\n";

	echo "\nError : ". $m . "\n$additional_info";
}
?>
