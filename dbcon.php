<?php
require_once("class.meekrodb.php");
require_once('dbconstants.php');
if( !isset($PDO_CONNECT) || ! $PDO_CONNECT )
{
	require_once('class.meekrodb.php');


DB::$user=db_user;
DB::$password=db_password;
DB::$host = db_host;
DB::$dbName =db_name;
DB::$encoding=db_encoding;
}
else
{
	$connec_options='';
	if( defined('db_host') )$connec_options.="host=".db_host.";";

	if( defined('db_name') )$connec_options.="dbname=".db_name.";";
	else throw new Exception("DB connection : No dbname");
	
	if( defined('db_port'))$connec_options.="port=".db_port.";";
	
	$connec_options = substr($connec_options,0,strlen($connec_options)-1);

	$db_type = defined('db_type')?db_type:"mysql";
	if( defined('db_user'))
		$username = db_user;
	if(defined('db_password'))
	$password = db_password;


	$pdo_options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION); // Activate exceptions as error mode
	if(strtolower(db_encoding) == "utf8")
	$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND]    = "SET NAMES utf8"; // Set database connection as utf8
	
	$conn = new PDO($db_type.":".$connec_options, $username, $password, $pdo_options);
	$conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}
?>
