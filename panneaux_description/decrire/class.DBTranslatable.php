<?php


class DBTranslatable
{
	private $row;
	private $translations;

	private $id_column;

	private $orig_tbl;
	private $tr_tbl;

	private $columns_tr; // array of columns that has translations associated with their name in the translation table

	public function __construct($orig_tbl, $tr_tbl, $id_column, $id_column_tr='',$columns_tr=array())
	{
		$this->orig_tbl = $orig_tbl;
		$this->tr_tbl =$tr_tbl;

		$this->id_column=$id_column;
		$this->id_column_tr = $id_column_tr==''?$id_column:$id_column_tr;

		$this->columns_tr=$columns_tr;
	}
	public function addColumnTr($column_name_orig_tbl, $column_name_tr_tbl)
	{
		$this->columns_tr[$column_name_orig_tbl]=$column_name_tr_tbl;
	}
	public function affect($row)
	{
		$this->row = $row;
		$this->searchTranslations();
	}
	public function getRow()
	{
	return $this->row;
	}
	public function getTranslatedRows()
	{
		return array_merge($this->row, $this->translations );
	}
	private function searchTranslations()
	{
		$this->translations=array();
		$id = $this->row[ $this->id_column ];

		$result = DB::query("SELECT * FROM `".$this->tr_tbl."` WHERE $this->id_column_tr='$id' ");
		echo "look at m qurt  SELECT * FROM `".$this->tr_tbl."` WHERE $this->id_column_tr='$id'\n ";
		foreach($result as $r)
		{
			$lang = $r['lang'];
			foreach($this->columns_tr as $col_orig => $col_tr)
			{
				if( !isset($this->translations[$col_orig]))
					$this->translations[$col_orig] = array();
				$this->translations[$col_orig][$lang] = $r[$col_tr];
			}
		}
	}
	public function getTranslations($column_name)
	{
		if( !isset($this->translations[$column_name]) )
			return array("orig"=>$this->row[$column_name]);
		else
		{
			$arr= $this->translations[$column_name];
			$arr["orig"] =$this->row[$column_name];
			return $arr;
		 }
	}
	public function getTranslation($column_name, $lang)
	{
		return ($this->getTranslations($column_name)[$lang]);
	}

	
}


?>
