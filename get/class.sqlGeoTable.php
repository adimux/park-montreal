<?php

class sqlGeoTable
{
	private $conn;
	private $table;

	private $columns_list=array();
	private $column_geom='';

	private $mysql_columns_list='';

	public function __construct($conn, $table, $column_geom='')
	{
		$this->table = $table;
		$this->conn=$conn;

		$this->column_geom = $column_geom;

		// Find the geom column because it is not indicated by the var $column_geom
		$this->columns_details = $this->conn->query("SHOW COLUMNS FROM `$table` ");
		foreach($this->columns_details as $col)
		{
			$isCoords = $this->detectIfCoords($col);

			if( $isCoords && !$this->column_geom)
			{
				$this->column_geom = array("name"=>$col["Field"], "type"=>$col["Type"]);
			}

			$this->columns_list[] = $col["Field"];
		}

		if( $this->column_geom == '')
			throw new Exception("Table $table does not have a geometry column");

		$this->mysql_columns_list = $this->listColumns();
	}
	private function detectIfCoords($column)
	{
		return preg_match("#(geometry|line|polygon|point)#i",$column["Type"]);
	}
	private  function listColumns()
	{
		$cl_list = $this->columns_list;

		// Building list of columns to use in a select query
		for($j=0;$j<count($cl_list);$j++)
		{
			$new_name = $cl_list[$j];
			
			$new_name = '`'.$new_name.'`';

			// if it's the geom column, we put AsWKB()
			if( $cl_list[$j] == $this->column_geom["name"] )
			{
				$new_name ='AsWKB('.$new_name.') as '.$new_name.'';
			}

			$cl_list[$j] = $new_name;
		}
		return implode(",",$cl_list);

	}
	public function selectQuery($limit=-1, $invertxy=false)
	{
		if( $limit  > 0)
		$limit = "LIMIT $limit";
		$q="SELECT ".$this->mysql_columns_list ." FROM `$this->table` $limit";
		$rows = $this->replaceWKBbyGeomInRows($this->conn->query($q)->fetchAll(PDO::FETCH_ASSOC), $invertxy);
		return $rows;
	}
	private function replaceWKBbyGeomInRows($rows,$invertxy=false)
	{
		$col_geom = $this->column_geom["name"];
		// reads binary wkb of each row and creates the Geomtry object (only for the geo column obviously)
		$col_geom_type = $this->column_geom["type"];
		for($i=0; $i < count($rows); $i++)
		{
			if( $col_geom !='' && isset($rows[$i][$col_geom ]))
			{
				$wkb = new WKB();
				$geom = $wkb->read($rows[$i][$col_geom]);
				if($invertxy )
					$geom->invertxy();

				$rows[$i][ $col_geom ] = $geom;
			}
		}

		return $rows;

	}
	// Finds all rows where the geometry is within the rect delimited by the two points
	// returns the list of rows. The geometry column is returned as a Geometry object(geoPHP) not
	// in binary format
	public function inRect($point1, $point2, $where='', $intersects_only=true, $invertxy=false)
	{
		$limit='';
		if($where)
			$where = "AND ($where)";
/*		$limit = 500;
		if( $limit )
			$limit = "LIMIT $limit";*/
		if( count($point1) < 2 || count($point2) < 2)
			throw new Exception("A point has to have two coordinates");
		
		$point1_txt ="POINT( ".$point1[0].", ".$point1[1]." )";
		$point2_txt ="POINT( ".$point2[0].", ".$point2[1]." )";


		$MBR_x = "MBRContains";
		if( $intersects_only)
			$MBR_x = "MBRIntersects";

		#echo "SELECT $this->mysql_columns_list FROM `$this->table` WHERE $MBR_x(GEOMETRYCOLLECTION($point1_txt, $point2_txt), coordinates ) $limit \n";
		$q = "SELECT $this->mysql_columns_list FROM `$this->table` WHERE $MBR_x(GEOMETRYCOLLECTION($point1_txt, $point2_txt), coordinates ) $where $limit ";
		//echo "query :$q\n";
		//exit(0);
		$rows = $this->conn->query("SELECT $this->mysql_columns_list FROM `$this->table` WHERE $MBR_x(GEOMETRYCOLLECTION($point1_txt, $point2_txt), coordinates ) $where $limit ")->fetchAll(PDO::FETCH_ASSOC);

		return $this->replaceWKBbyGeomInRows($rows,$invertxy);
	}
	public function toGeoJSON($rows, $return_array=false, $as_feature_collection=true)
	{
		if($as_feature_collection)
		{
			$array = array("type"=>"FeatureCollection");
			$features = array();

			$col_geom = $this->column_geom["name"];
			for($i=0;$i<count($rows);$i++)
			{
				$gjson = new GeoJSON();

				$f = array();

				$props = $rows[$i];
				if( $props)
				{
				unset($props[$col_geom]);

				$f["type"] ="Feature";
				$f["geometry"] = $gjson->write($rows[$i][$col_geom],true) ;			
				$f["properties"] = $props;

				$features[$i] =$f;
				}
			}

			$array["features"] = $features;

			return $return_array?$array: json_encode($array);
		}
		else
		{
		}


	}

}


?>
