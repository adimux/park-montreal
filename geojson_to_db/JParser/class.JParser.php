<?php
class JParserException extends Exception
{   

}

class JParser
{
	public static function open($options)
	{
		$default_options = array('useBigJSONobj'=>false,
			'file'=>'',
			'filepath'=>'',
			'data'=>'','memlimit'=>'-1');
		if( is_string($options) )$options = array('filepath'=>$options);

		$options = array_merge($default_options,$options);


		$useBigJSONobj = $options['useBigJSONobj'];

		if( !$useBigJSONobj)
		{
			if( $options["file"]!='' )
				return new JSONobj("", null,$options["file"], true);
			else if( $options["filepath"]!='')
				return new JSONobj("",null,$options['filepath'],true);
			else if( $options["data"]!='')
				return new JSONobj("", null, $options["data"],false);
			else
				throw new JParserException("You didn't give file or filepath or data");
		}
		else
		{
			if( $options["file"]!='')
				return new BigJSONobj("",null, $options["file"],0,$options["memlimit"]);
			else if( $options["filepath"]!='')
				return new BigJSONobj("",null, $options["filepath"],0,$options["memlimit"]);
			else if( $options["data"]!='')
				throw new JParserException("Can't parse data with BigJSONobj. Indicate a file/filepath");
		}
	}
}

class JSONobj
{
	public static $ARRAY = 'a';
	public static $OBJECT = 'o';
	public static $VALUE_NUM = 'n';
	public static $VALUE_STR = "s";
	public static $UNDEFINED = "u";

	protected $filepath="";
	protected $data=null;

	protected $parent=null;

	// if it is an array/obj, list of childs
	protected $childs =null;
	protected $index_childs=0; // if it's an array, $index_childs represents the current index (used by nextChild() )

	protected $name=""; // in the case that parent is an object
	protected $index=-1; // in the case that parent is an array

	protected $type="u";

	// value if it is not an array/obj
	protected $value=null;

	public function __construct($name,$parent,$fileOrData,$isFile=true,$isJSONstr=true)
	{
		$this->name = $name;
		$this->parent = $parent;

		$raw_data = null;

		if( $isFile && is_string($fileOrData) )
		{
			$this->filepath = $fileOrData;
			$raw_data = file_get_contents($fileOrData);
		}
		else if( $isFile )
		{
			while($c=fgetc($fileOrData))
			$raw_data .= $c;
		}
		else if( !$isFile )
		{
			$raw_data = $fileOrData;
		}

		if(is_string($raw_data))
		{
			//echo $raw_data;
			if( $isJSONstr )
			{
				$this->data = json_decode($raw_data,true);
				echo $this->data." arr\n";
				if($this->data === null)
					throw new Exception("Invalid json file");
			}

		}
		else
		{
			$this->data = $raw_data;

			if( !is_array($raw_data) && !is_object($raw_data) )
				$this->value = $raw_data;
		}

//		var_dump($this->data);
		$this->determineType();

		if( is_array($this->data) || is_object($this->data))
		{
			foreach( $this->data as $key => $value)
			{
				$this->childs[$key] = new JSONobj(""+$key,$this,$value,false,false);
			}
		}
	}
	public function isBigJSONobj()
	{return false;}
	public function size()
	{
		return count($this->childs);
	}
	public function dump()
	{
		var_dump($this->data);}
		public function getName()
	{
		return $this->name;
	}
	// parent is object
	public function setName($n)
	{
		$this->name = (string)$n;
	}
	// if parent is array
	public function setIndex($i)
	{
		$this->index = $i;
	}
	public function getIndex()
	{return $this->index;}
	public function getValue()
	{
			if( !$this->isValue() )
			{
				if( $this->isArray() )
					$type_name = "array";
				else if( $this->isObject() )
					$type_name = "object";
				else
				{
					$type_name = "undefined";
					throw new JParserException("JSONParser error : object '$this->name', Trying to get value of an undefined object.");
				}

				throw new JParserException("JSONParse error : object '$this->name', Trying to get value of an ".$type_name );
			}
			return $this->value;

	}
	private function isAssoc($arr)
	{
		    return array_keys($arr) !== range(0, count($arr) - 1);
	}
	private function determineType()
	{
		if( (is_string($this->data) &&preg_match("#collection#i",$this->data) ) ) 
			echo "determine type $this->data\n";
		if( ($this->name === '0' || $this->name === 0) && $this->parent->getName() == "features")
			echo "determine type OF $this->data\n";


		if( is_string($this->data) )
		{
			if( is_string($this->data) &&preg_match("#collection#i",$this->data) )
		
			echo "is string\n";
			$this->setType(JSONobj::$VALUE_STR);
		}
		else if( is_numeric($this->data) )
			$this->setType(JSONobj::$VALUE_NUM);
		
		else if( is_array($this->data) )
		{
			if(!$this->parent)
			{
				echo "is array\n";
			}
			if( !$this->isAssoc($this->data) )
				$this->setType(JSONobj::$ARRAY);
			else
			{
				if(!$this->parent)		
				echo "is assoc\n";
				$this->setType(JSONobj::$OBJECT);
			}
		}
	}
	public function setType($t)
	{
		if( !$this->parent)
		echo " set to ".$t."\n";
		if( $t == self::$VALUE_NUM || $t == self::$VALUE_STR || $t == self::$OBJECT || $t == self::$ARRAY )
		{
			$this->type = $t;
		}
	}
    public function isArray()
	{
		return $this->type == self::$ARRAY;
	}
	public function isValue()
	{
		return ($this->type == self::$VALUE_NUM || $this->type == self::$VALUE_STR);
	}
	public function isValueNum()
	{
		return $this->type == self::$VALUE_NUM;
	}
	public function isValueStr()
	{
		return $this->type == self::$VAUE_STR;
	}
	public function isObject()
	{
		return $this->type == self::$OBJECT;
	}

	public function setIndexChilds($i)
	{
		if(is_numeric($i))
		$this->index_childs = $i;
	}
	public function nextChild()
	{
		$child = null;
		
		//	echo "indx $this->index_childs \n";
		if( $this->isArray())
		{
			if( ! array_key_exists($this->index_childs , $this->childs)) 
			{
				$firstkey = array_keys($this->childs)[0];

				$this->index_childs = $firstkey > $this->index_childs? $firstkey:$this->index_childs;
			}
		}
		echo "first $firstkey\n";

		if (! $this->parent )
			echo " type ".$this->type."\n";
		if( $this->isArray() )
			$child = $this->getChild($this->index_childs);
		else if ( $this->isValue() )
			throw new JParserException("JSONParser error : object '$this->name', trying to get next child of a value json object");
		else if( !$this->isObject() ) // Then it's undefind
			throw new JParserException("JSONParser error : object '$this->name', trying to get next child of an undefined json object");
	
	
		$this->index_childs++;

	//	echo "indx child : ".$this->index_childs++."\n";

		return $child;

	}
	public function get($id)
	{return $this->getChild($id);}
	public function getChild($identifier)
	{
		if( $this->isArray() )
		{
			if( is_numeric($identifier) )
			{
				$ch = $this->childs[$identifier];
				return $ch;
			}
			else
				throw new JParserException("JSONParser error : object '$this->name', trying to get child by array index but the index is not numeric");
		}
		else if( $this->isObject() && is_string($identifier) )
		{
			foreach($this->childs as $name => $obj)
			{
				if($identifier == $name)
				{
					return $obj;
				}
			}
		}
		else if( $this->isObject() && !is_string($identifier) )
		{
			throw new JParserException("JSONParser error : object '$this->name', trying to get child by a non string identifier : $identifier");
		}
		else if( $this->isValue() )
		{
			throw new JParserException("JSONParser error : object '$this->name', trying to get childs of a value");
		}
		else
			throw new JParserException("JSONParser error : object '$this->name', trying to get childs of and undefined object.");
	}
	public function getChilds()
	{
		return $this->childs;
	}
	public function hasChild($identifier)
	{
		echo $identifier."\n";
		var_dump(array_keys($this->childs));
        $found =	(array_search($identifier,array_keys($this->childs))!==FALSE);
		var_dump($found);
		return $found;
	}
	public function has($i)
	{return $this->hasChild($i);}
	public function getJSON()
	{
		return $this->data;
	}
}

class FileReader
{

	protected $file;
	protected $oldPos;

	public function __construct($file)
	{$this->file = $file;
	$oldPos =-1;}
	public function getc()
	{return fgetc($this->file);}
	public function seek($pos)
	{fseek($this->file,$pos);}
	public function tell()
	{return ftell($this->file);}
	public function move($count)
	{$this->seek($this->tell()+$count);}
	public function read($count)
	{return fread($this->file, $count);}
	public function savePos()
	{
		$this->oldPos = ftell($this->file);
	}
	public function loadPos()
	{
		if($this->oldPos >= 0)
			fseek($this->file,$this->oldPos);
	}
}
class BufferedFileReader extends FileReader
{
	private $buffer=null;

	private $buffer_pos=0;

	private $position=0;
	private $end_of_file=-1;

	private $buffer_len=0;

	public function __construct($file,$buffer_len=1000)
	{
		$this->file = $file;
		$this->buffer_len = $buffer_len;
		$this->position = ftell($this->file);
	}
	public function loadBuffer()
	{
//		echo "load buff\n";

		$new_buffer='';
		$diff= $this->position - $this->buffer_pos;
		if( $diff <= $this->buffer_len && $diff > 0 && $this->buffer != '')
		{
//			echo "case 1 $this->position\n";
			$new_buffer.=substr($this->buffer, $diff);
			
			fseek($this->file,$this->position+ $this->buffer_len-$diff);

			$s = fread($this->file,$diff);
			$len = strlen($s);

			if( feof($this->file))
			{
//				echo "la fin est proche\n";
				$this->end_of_file = ($this->position+$this->buffer_len-$diff )+ $len;
			}

			$new_buffer = $s;

		}
		else if( $diff >= -$this->buffer_len && $diff < 0 && $this->buffer !='')
		{
//			echo "case 2\n";
			fseek($this->file,$this->position);

			$s=fread($this->file,-$diff);

			$s.=substr($this->buffer,0,$this->buffer_len + $diff);
		}
		else
		{
//			echo "case 3\n";
			fseek($this->file,$this->position);

			$new_buffer = fread($this->file,$this->buffer_len);

			$len = strlen($new_buffer);
			if( feof( $this->file))
			{
//				echo "la fin approche!!\n";
				$this->end_of_file = $this->position + $len;
			}
		}
		$this->buffer = $new_buffer;
		
		$this->buffer_pos = $this->position;
	}
	public function getc()
	{
		return $this->read(1);
	}
	public function read($count)
	{
		$end = $count + $this->position;


		if($this->end_of_file != -1 &&  $this->position + $count > $this->end_of_file )
			$count = $this->end_of_file - $this->position;

		if( $count == 0)
			return false;

		$output = "";

		if(( ($this->buffer_pos + $this->buffer_len) <= $this->position )||
			(($this->position + $this->buffer_len )<= $this->buffer_pos )||
		   	$this->buffer =='')
		{
	//		echo "one:$output\n";
			$this->loadBuffer();

			if( $this->buffer == '') // then eof
				return false;
		}
		

		if( $count >= ($this->position-$this->buffer_pos+$this->buffer_len))
		{
	//		echo "two:$output\n";
			$_count  = ($this->position-$this->buffer_pos+$this->buffer_len);
			$output .= substr($this->buffer, $this->position-$this->buffer_pos, $_count);

			$this->position += $_count;
			
			$output .= $this->read($count - $_count);
		}
		else
		{
			$output .= substr($this->buffer,$this->position-$this->buffer_pos,$count);
	//		echo "three:$output\n";
			$this->position += $count;
		}
		return $output;
	}
	public function setBufferLen($len)
	{
		$this->buffer_len = $len;
		$this->loadBuffer();
	}
	public function seek($pos)
	{
		$this->position=$pos;

		if(	abs($pos - $this->position) > $this->buffer_len/2)
			$this->loadBuffer();
	}
	public function move($count)
	{
		$this->seek($this->tell() + $count);
	}
	public function tell()
	{
		return $this->position;
	}

}

require_once('class.BigJSONobj.php');

?>
