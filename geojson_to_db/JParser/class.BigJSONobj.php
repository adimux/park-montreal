<?php

function _uniord($c) {
	    if (ord($c{0}) >=0 && ord($c{0}) <= 127)
			        return ord($c{0});
		    if (ord($c{0}) >= 192 && ord($c{0}) <= 223)
				        return (ord($c{0})-192)*64 + (ord($c{1})-128);
			    if (ord($c{0}) >= 224 && ord($c{0}) <= 239)
					        return (ord($c{0})-224)*4096 + (ord($c{1})-128)*64 + (ord($c{2})-128);
				    if (ord($c{0}) >= 240 && ord($c{0}) <= 247)
						        return (ord($c{0})-240)*262144 + (ord($c{1})-128)*4096 + (ord($c{2})-128)*64 + (ord($c{3})-128);
					    if (ord($c{0}) >= 248 && ord($c{0}) <= 251)
							        return (ord($c{0})-248)*16777216 + (ord($c{1})-128)*262144 + (ord($c{2})-128)*4096 + (ord($c{3})-128)*64 + (ord($c{4})-128);
						    if (ord($c{0}) >= 252 && ord($c{0}) <= 253)
								        return (ord($c{0})-252)*1073741824 + (ord($c{1})-128)*16777216 + (ord($c{2})-128)*262144 + (ord($c{3})-128)*4096 + (ord($c{4})-128)*64 + (ord($c{5})-128);
							    if (ord($c{0}) >= 254 && ord($c{0}) <= 255)    //  error
									        return FALSE;
								    return 0;
}   //  function _uniord()
class BigJSONobj extends JSONobj
{
	public static $ARRAY = 'a';
	public static $OBJECT = 'o';
	public static $VALUE_NUM = 'n';
	public static $VALUE_STR = "s";
	public static $UNDEFINED = "u";

	protected $debug_mode =false;
	protected $expressMode = true;

	protected $hasFetched = false; //bool : true means that we parsed the file

	protected $file;
	protected $fileReader;

	protected $memlimit; 

	protected $oldPos; // old pos in file saved by savePos() so we can return to oldPos when calling loadPos()

	protected $start; // start index of object in the file
	protected $end; // end inclusive

	public static $Mo = 1000000; // 1 mo
	public static $Ko = 1000;

	protected $parent; // parent object if exists
	protected $childs; // childs if exists
	//	protected $index_childs; // the actual index in the array child (used by the function nextChild() )
	protected $add_child_index_ch;

	public function activateDebugMode()
	{
		$this->debug_mode=true;
	}
	function __construct($name,$parent,$fileorpath, $startOfObject=0, $memlimit=30000000 )
	{
		$this->add_child_index_ch = 0;

		$this->start = $startOfObject;
		$this->end = -1;

		if( is_string($fileorpath) )
		{
			$this->file = fopen($fileorpath, "r");
			$this->filepath = $fileorpath;
		}
		else
		{
			$this->file = $fileorpath;
			$this->filepath = '';
		}
		$this->fileReader = new FileReader($this->file);


		$this->parent = $parent;
		$this->childs = array();
		$this->name =$name;
		$this->value = null;

		if($memlimit > 0)
			$this->memlimit = $memlimit;
		else
			$this->memlimit = -1;

		$this->index_childs = 0;

		$this->type = JSONobj::$UNDEFINED;

	}
	public function getPosFile()
	{
	return $this->fileReader->tell();
	}
	// Activating express mode means that getChild will return a fetched child
	public function setExpressMode($b)
	{
		$this->expressMode = (boolean)$b;
	}
	public function getExpressMode()
	{return $this->expressMode;}
	public function dump()
	{
                $childs =$this->childs;
                $type = $this->type;

                $arr_type_text = array(JSONobj::$ARRAY=>"array", JSONobj::$OBJECT=>"object",JSONobj::$VALUE_NUM=>"numeric", JSONobj::$VALUE_STR=>"string", JSONobj::$UNDEFINED=>"undef");
                $type_text = $arr_type_text[$type];

                echo "$type_text ['".$this->name ."']";
                if($this->isArray() || $this->isObject() )
                {
                        echo " =>".(count($childs)<=0?"":"\n");
                        foreach($childs as $key => $child)
                        {
                                echo "[".$key."]\n";
						if($child->getType() != self::$UNDEFINED){
						echo " : ";
						$child->dump();
				}
                        }
                }
                else if($this->isValue())
                        echo " : ".$this->getValue();
                echo "\n";
	}
	protected function setMemlimit($m)
	{$this->memlimit=$m;}
	private function addChild($name, $jsonObj)
	{
		$jsonObj->setMemlimit($this->memlimit);
		if($this->isArray())
		{
			$jsonObj->setIndex(""+$this->add_child_index_ch);
			$this->childs[$this->add_child_index_ch] = $jsonObj;
			$this->add_child_index_ch++;
		}
		else if($this->isObject())
		{
//			echo "$name 'cause is object\n";
//			exit(0);
			$this->childs[$name] = $jsonObj;
		}
		if( $this->isArray() )
		echo "ch:".$this->add_child_index_ch."  ".memory_get_usage()."\n";
	}
	public function getName()
	{
	return $this->name;
	}
	public function getType()
	{
		return $this->type;
	}
	public function getEnd()
	{
		return $this->end;
	}
	public function getStart()
	{
		return $this->start;
	}

	// Returns the value if it is actually a value, not an object nor an array
	public function getValue($fetch_if_not_already=false)
	{
		if(!$this->hasFetched && $fetch_if_not_already)
			$this->fetch();

		if( !$this->isValue() )
		{
			if( $this->isArray() )
				$type_name = "array";
			else if( $this->isObject() )
				$type_name = "object";
			else
			{
				$type_name = "undefined";
				throw new JParserException("JSONParser error : object '$this->name', Trying to get value of an undefined object. Try to call fetch() first");
			}
			
			throw new JParserException("JSONParse error : object '$this->name', Trying to get value of an ".$type_name );
		}
		return $this->value;
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
		return $this->type = self::$VALUE_NUM;
	}
	public function isValueStr()
	{
		return $this->type == self::$VAUE_STR;
	}
	public function isObject()
	{
		return $this->type == self::$OBJECT;
	}

	public function isBigJSONobj()
	{
		return true;
	}
	public function size()
	{
		if(!$this->hasFetched)
			$this->fetch();
		return parent::size();
	}
	public function getChild($identifier)
	{
		echo "identifier:".$identifier."\n";
//		var_dump($this->childs);
		if( !$this->hasFetched && !array_key_exists($identifier,$this->childs) )
		{
//			echo "case fuckin g 1\n";
			$this->fetch();
			return $this->getChild($identifier);
		}
		else if( $this->hasFetched && !array_key_exists($identifier,$this->childs) )
			return null;

//		echo "i'm in thsi \n";

		$ch = parent::getChild($identifier);
		if( $this->expressMode)
			$ch->fetch;

		return $ch;
/*		if( $this->isArray() )
		{
			if( is_numeric($identifier) )
			{
				
				$ch = $this->childs[$identifier];
				if($this->expressMode && $ch)
					$ch->fetch();
				return $ch;
			}
			else
				throw new JParserException("JSONParser error : object '$this->name', trying to get child by array index but the index is not numeric");
		}
		else if( $this->isObject() && is_string($identifier) )
		{
			foreach($this->childs as $name => $jsonObj)
			{
				if($identifier == $name)
				{
					echo $this->expressMode?"EXPRESS\n":"";
					if($this->expressMode)
						$jsonObj->fetch();
					return $jsonObj;
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
			throw new JParserException("JSONParser error : object '$this->name', trying to get childs of and undefined object. Try to call fetch() first");*/
	}
	public function getChilds()
	{
		if($this->expressMode)
		{
			foreach($this->childs as $key => $child)
				$this->childs[$key]->fetch();
		}
		return $this->childs;
	}
	public function getJSON($assoc=false)

	{
		return $this->json_decode($assoc);}
	public function json_decode($assoc=false)
	{
		$raw = $this->getRawJson();
		
		$name = $this->name;

		if( $name != "")
		{
			$obj = json_decode( "{".$raw."}",$assoc);
			if($assoc == true)
				return $obj[$name];
			else
				return $obj->$name;
		}
		else
		{
			return json_decode("[".$raw."]",$assoc)[0];
		}
	}
	public function getRawJson() // returns raw json from file of this obj
	{
		$this->fileReader->savePos();


		$this->fileReader->seek($this->start);

		if($this->end >= 0)
		{
			//echo $this->start ." ".$this->end;
			//exit(0);
			$raw = $this->fileReader->read($this->end+1 - $this->start);
		}
		else
		{
			$this->fetch(0);
			return $this->getRawJson();
/*			$raw ='';
			do
			{
				$c = $this->fileReader->getc();
				$raw.=$c;
			}
			while($c!== FALSE);*/
		}


		$this->fileReader->loadPos();

		return $raw;
	}

	    public function getFileOffset()
			    {return $this->fileReader->tell();}

	// This function fetches inside the object (in the file) and determines its types (wether it is an array, object or a value)
	// If it is an object, it determines the child objects start positions and creates the childs in the array "childs"
	public function fetch($max_childs=-1,$max_chars=-1,$add_childs=true)
	{
		echo $max_childs."  ismax \n;";
		// max_childs == 0 means infinite
		// max_childs <= -1 means default =>
		if( $this->memlimit > 0 && $max_childs < 0)
			$max_childs = (int)($this->memlimit/1400); // Imperial value (experimentation). 34000000 childs take 33 MBytes of mem
//		if($max_chars > 0)
//			$this->fileReader->setBufferLen($max_chars) ;


		$c = $this->loadFetchState();
//var_dump($c);
		if($c!=null && $c->isDone())
			return false;

		$debug = $this->debug_mode;
		if( !$this->isFetchPaused() )
		{

			$this->fileReader->seek($this->start);

			// Begin with reading spaces or line returns until we 
			// find an opening bracket, a number or a quote
			try
			{
				$startDelimiter = $this->readUntilCharExpected("#^(|[\n\t\r ])$#", "#^[-\[{\"0-9]$#"); // Read spaces or none chars until we find the bracket "[" telling the beginning of an array or "{" telling the begininning of the object or a " telling the beginning of an object/array name or a string value or finally a number so it's a number value
			}
			catch(JParserExpectedException $e)
			{
				$f = $e->getFound();
				// it's a bad json format because we didn't found what was expected
				throw new JParserException("JSONParser error : File ".$e->getFilename()." Position ".$e->getOffset().", expecting spaces or no character before beginning delimiter {, [, \", or number. Found '".$e->getFound()."' \nMsg : ".$e->getAdditionalMessage()."\nSurrounding chars : ...".$e->getSurroundingChars()."...\n");
			}

			if($startDelimiter == "\"" || $startDelimiter == "'") // Then the object can
				// be a value (so it is just a string), an object or an array (so it's the name
				// of the object), we don't know yet
			{
				// move forward by 1 to parse the quoted string
				$this->fileReader->move(-1);

				$string = '';
				try{

					$string = $this->readQuotedString();

				}catch(JParserExpectedException $e){
					throw new JParserException("JSONParser error : ".$e->getMessage()."\n" );
				}

				try{
					// Read spaces or none chars until  we find a comma or a ':' or a closing bracket (] or })
					$c =$this->readUntilCharExpected("/([\t\r\n ]|)/s", "/(,|:|])/");
					// Read until we find a "," telling that we're moving
					// to the next object in an array or an array closing bracket ]
					// Or a ":" telling that the object/arr/value
					// value is beginning here so what's preceeding was the name
				}catch(JParserExpectedException $e){
					throw new JParserException("JSONParser error : Expecting ':' or ',' instead"
						."of ".$e->getFound() ."\nSurrouding chars : ".$e->getSurroundingChars(-1)."\n");
				}

				if( $c == "," || $c == "]")
				{
					// Then it's a string value in a parent array
					if( $this->parent != null && $this->parent->isObject() )
					{
						throw new JSONParserException("JSONParser error : Expecting ':', found $c instead.\nAt :\n".$this->getSurroundingChars()."\n");
					}
					$this->value = $string;
					$this->type = self::$VALUE_STR;

					$this->end = $this->fileReader()->tell()-2;
					return;
				}
				else if($c == ":")
				{
					// Then the string preceeding was only the name of a value/obj/array
					$this->name = $string;
					try{
						// Find the beginning of an array/obj/string/number
						$startDelimiter =$this->readUntilCharExpected("#(|[\n\t\r ])#", "#[[{\"'0-9-]#");

					}catch(Exception $e)
					{throw new JParserException("JSONParser error : trying to search the value of '$this->name', expecting { or [ or quote, found $startDelimiter\n"); }
				}
			}
			else if( $this->parent != null && $this->parent->isObject() )
				throw new JParserExpectedException("JSONParser error : expecting an opening quote because the parent is an object. Found $startDelimiter.\nAt :\n"+$this->getSurroundingCode(-1)."\n");
		}
		// If we're here, then there was a string before and a ":", the string is the name of the object (it can be an array or an object or a value
		// Or case 2 : we're in an array
		// Note : if fetch has benn paused, what we said is not applicable

		/// NUMBER
		if(preg_match("#^[-0-9]$#",$startDelimiter) && !$this->isFetchPaused() )
		{
			$this->fmove(-1); // Move forward by one to match the beginning of the number
			try
			{
				$number = $this->readNumber();
			}catch(JParserExpectedException $e)
			{
				throw new JParserException("JSONParser error : ".$e->getMessage());
			}
			$this->setType(JSONobj::$VALUE_NUM);
			$this->value = $number;

			$this->end = $this->fileReader->tell()-1;
		}
		/// STRING
		else if($startDelimiter == "\"" || $startDelimiter=="'" && !$this->isFetchPaused() )
		{
			$this->fmove(-1);
			try{
				$string = $this->readQuotedString();
			}catch(JParserExpectedException $e){
				throw new JParserException("JSONParser error : ".$e->getMessage());
			}
			$this->setType(JSONobj::$VALUE_STR);
			$this->value = $string;
			$this->end = $this->fileReader->tell()-1;
		}
		/// ARRAY / OBJ
		else if($startDelimiter == "{" || $startDelimiter == "["  || $this->isFetchPaused() ) // Then it's a json object or an array
		{
			$nb_childs = 0;
			$pos_debut = -1;

//echo (int)$this->isFetchPaused."yes\n";


			if( !$this->isFetchPaused() )
			{
				// If not fetch paused then it's the first fetch, so we have to dot that
				if($startDelimiter == "{")
				{
					$endDelimiter = "}";
					$this->setType(JSONobj::$OBJECT);
				}
				else if( $startDelimiter == "[")
				{
					$endDelimiter = "]";
					$this->setType(JSONobj::$ARRAY);
				}
			}

			$s = & $this->loadFetchState();

			if($pos_debut==-1)
				$pos_debut=$this->fileReader->tell();
			
			if( $s==null )
			{
				$s= new FetchState($this->fileReader->tell());

				$s->closing_brackets = array($endDelimiter); // it is a stack reminding the c
				$s->count_closing_brackets=1;
				$s->first_fetch	= true;
				$s->index_childs = &$this->index_childs;
				$s->add_child_index_ch = &$this->add_child_index_ch;

				$s->c = '';
				$s->pc = '';

				$this->saveFetchState($s);
			}
			else
			{
				//              echo "here\n";
				//           $this->printFetchState($s);
				echo "empty shit !\n";
				$this->emptyChilds(); // we loaded the fetch state. Then we have to empty the
			}                                          
			
			$pos_debut = $this->fileReader->tell();

			// We'll parse and try to find the locations (position in file) of the items without parsing their contents but only their names (if we are in an object. In the case of an array, they have not names, they are just ordered from 0 to n)

			/*
			$childName ="";
			if($this->isObject() ) // If it is an object, then the items have names so we ll parse it
			{
				$pos = $this->fileReader->tell();
				try{
					$childName = $this->readQuotedString();
				}catch(JSONExpectedException $e){
					throw new JParserException("JSONParser error : ".$e->getMessage());
				}
				$this->fileReader->seek($pos);
			}
			if($add_childs)
				$this->addChild($childName,new BigJSONobj($childName,$this, $this->file, $this->fileReader->tell()));
			else
				echo "do not add childs";

			$nb_childs++;*/

			$nb_childs = 0;
			if( $max_childs > 1)
				{
	echo "max childs to fetch : ".$max_childs."\n";

//exit(0);
		}
			do
			{
				$nb_chars = $this->fileReader->tell()-$pos_debut;

				#echo $nb_childs."\n";
				#
				//echo $max_childs."\n";
				// IMPORTANT : always save state here (afte fgetc and pc affectation in the while cond down there)
				if( ($max_childs >= 1 && $nb_childs >= $max_childs) ||
					($max_chars >= 1 && $nb_chars >= $max_chars) 
						)
				{
					$this->saveFetchState($s);
					return true;
				}

//				if($debug)
				//				echo "carac : $c. Closing brack : ".$closing_brackets[$count_closing_brackets-1]." $count_closing_brackets\n";

	//			if($s->depth == 1)
//				{
//					echo "depth:". $s->depth."\n";
//				}
				if( !$s->in_quotes)
				{
					if( ($s->first_fetch	|| $s->c == ",") && $s->depth ==1 ) // if we are in the root object (this) in the file then the comma announces the next child
					{
//						echo "tell".$this->fileReader->tell()."\n";
//						echo "Garda! : ".$this->fileReader->read(5)."\n";
						//$this->fileReader->move(-5);
						if($s->first_fetch)
							$s->first_fetch =false;

						$childName ="";
						if($this->isObject() )// If it is an object, then the items have names so we ll parse it
						{
							$pos = $this->fileReader->tell();
//							echo "look:".$this->fileReader->getc()."\n";
							try{
								if($debug )
								{
									echo "I am doing that because $s->c\n";
									echo"Searching for quote in : ". $this->getSurroundingChars(-1)."\n";
								}
								//echo "Garde : ".$this->fileReader->read(5)."\n";
								//$this->fileReader->move(-5);
								$childName = $this->readQuotedString();
								if($debug)
									echo "Found : ".$childName."... GOOD\n";
							}catch(JParserExpectedException $e){
								throw new JParserException("JSONParser error : ".$e->getMessage());
							}
							$this->fileReader->seek($pos);
						}
						if($debug)
							echo "childName : $childName\n";
						//					echo "Add child\n";
						if($add_childs)
						{
							$this->addChild($childName, new BigJSONobj($childName,$this, $this->file, $this->fileReader->tell())); // add next child
						}
						else
						{
							$this->add_child_index_ch++;
						}
						$nb_childs++;
					}
					else if( $s->c == "{"||$s->c=="[")
					{
						$s->depth++;
						if($debug)
							echo "found $s->c".$twoc." depth : $s->depth\n";
						$s->closing_brackets[] = $s->c == "{"?"}" :"]";
						$s->count_closing_brackets++;
					}
					else if( $s->count_closing_brackets> 0 && $s->c == $s->closing_brackets[$s->count_closing_brackets-1])
					{
						//$closing_brackets = array_slice($closing_brackets,0,$count_closing_brackets-1);
						$new_cb = array();
						for($i =0; $i < $s->count_closing_brackets-1; $i++)
							$new_cb[] = $s->closing_brackets[$i];
						$s->closing_brackets = $new_cb;

						$s->count_closing_brackets--;
						$s->depth--;

						if($debug)
						echo "found".$s->c." at ".$this->fileReader->tell()." depth : $s->depth\n";
						//fmove(-2);
					}
					else if( ($s->c == "'" || $s->c == "\"") ) 
					{
						$this->fileReader->savePos();
						$nbr_slashes =0;
						
						$C = $s->pc;
						$this->fileReader->move(-1);

						// find the number of backslashses before the quote to know if the quote is escaped (if the nbr is pair the quote is not escaped)
						while($this->fileReader->tell()>=0)
						{
							if($debug)
								echo $C."\n";
							if ( $C == "\\")
								$nbr_slashes++;
							else
								break;
							$this->fileReader->move(-2);
							$C = $this->fileReader->getc();

						}
						if($debug)
						echo $nbr_slashes." ".$this->fileReader->tell()."\n";
						$this->fileReader->loadPos();

						if($nbr_slashes%2 == 0)
						{
							$this->fileReader->loadPos();

							$s->in_quotes = true;
							if($debug)
								echo "found $s->c IN QUOTES";
							$s->quote = $s->c;
						}
                    }
				}
				else if( $s->in_quotes )
				{
					if( $s->c == $s->quote )
					{
						$this->fileReader->savePos();
						$nbr_slashes = 0;

						$this->fileReader->move(-1);
						$C = $s->pc;

						while($this->fileReader->tell()>=0)
						{
							if($debug)
							echo $C."\n";
							if ( $C == "\\")
								$nbr_slashes++;
							else
								break;

							$this->fileReader->move(-2);
							$C = $this->fileReader->getc();

						}
						if($debug)
						echo $nbr_slashes." ".$this->fileReader->tell()."\n";
						$this->fileReader->loadPos();


						if( $nbr_slashes%2 == 0)
						{
							// we found quotes, then we don't care of the content even if there are "}"
							$s->in_quotes = false;
							$s->quote = $c;		
							if($debug)
								echo "found $s->c".$twoc." OUT QUOTES\n";
						}
					}
				}
				$s->pc = $s->c;
//				echo "depth : $s->depth\n";

			}
			while(($s->c = $this->fileReader->getc() ) !== FALSE && $s->depth > 0);

			$this->saveFetchState($s);

			$this->end = $this->fileReader->tell() -2;
//			if($debug)
				echo "FINAL DEPTH : ".$s->depth."\n";

			$this->hasFetched=true;

//			if($debug)
			echo $this->getSurroundingChars();

			if( $s->depth > 0)
			{
				if( $s->c === FALSE ) $s->c = "end of file";
var_dump($s->closing_brackets);
				throw new JParserException("JSONParserError : Missing closing bracket '".$s->closing_brackets[count($s->closing_brackets)-1]."', at ".$this->fileReader->tell()."\nSurrounding Chars : ".$this->getSurroundingChars(-1)."\n");
			}
		}
	}
	private function isFetchPaused()
	{
		return (count($this->fetch_states) > 1);
	}
	private function loadFetchState()
	{
		$c=count($this->fetch_states);
		//echo "FUCKING HELL $c $this->index \n";
		$state =null;
		if($c > 0)
		{
			$this->fileReader->seek($this->fetch_states[$c-1]->fileOffset);

			$state = $this->fetch_states[$c-1];
		}
/*		else if( $this->parent ==null)
		{
			
			$s=json_decode(file_get_contents("fetchstate"));
			if( $s )
			{
				var_dump($s);
				$state  =new FetchState();
				$state->merge($s);
			}
		}*/

		if($state)
		{
			$this->index_childs = $state->index_childs;
			$this->add_child_index_ch = $state->add_child_index_ch;
			$this->type = $state->type;
			if($this->debug_mode)
			{
				echo "LOADING FETCH STATE\n";
				$this->printFetchState($state);
			}

		}

		return $state;
	}
	private function saveFetchState($s)
	{
		$s->type = $this->type;
		$s->index_childs = $this->index_childs;
		$s->add_child_index_ch = $this->add_child_index_ch;

		$s->fileOffset = $this->fileReader->tell();
		$this->fetch_states[] = $s;
static $nb = 0;
		if($nb >= 3)
		{
//			echo "SAVE:";		var_dump($s);exit(0);
		}
		$nb++;
		echo "COUNT". count($this->fetch_states)."\n";
		if( $this->debug_mode)
			$this->printFetchState($s);
		file_put_contents("fetchstate",json_encode($s));
	}
	private function printFetchState($s)
	{
		echo "Fetch state :";
		var_dump($s);
		echo $this->getSurroundingChars();
	}
	private function emptyChilds()
	{
		unset($this->childs);
		$this->childs= array();
	}
	private function savePos()
	{
		$this->fileReader->savePos();
	}

	private function loadPos()
	{
		$this->fileReader->loadPos();
	}
	private function fmove($count)
	{	
		if( !is_numeric($count) )
			return;

		$position = $this->fileReader->tell();
		$position += $count;

		if($position < 0)
			$position = 0;

		$this->fileReader->seek($position);
	}
	private function readNumber($before=" ")
	{
		$negative = false;

		try{
			// Read until we find the minus - or the first digit
			$firstNumber =$this->readUntilCharExpected("#($before|)#", "#(-|[0-9])#");
			if($firstNumber == "-")
				$negative = true;
			$positionBeginNumber = $this->fileReader->tell() - ($negative==false?1:0);
		}catch(JParserExpectedException $e){
			$e->setExpected("number");
			throw $e;
		}

		try{
		// Read digits until we find a comma or a space or a line return
		$this->readUntilCharExpected("/^[0-9.]$/", "/^([\n\t\r]| |,|)$/");
		}catch(JParserExpectedException $e){
			throw $e;
		}

		$positionEndNumber = $this->fileReader->tell()-1;

		$this->fileReader->seek($positionBeginNumber);

		return ($negative?-1:1)*intval(fread($this->file, $positionEndNumber -$positionBeginNumber));
	}
	private function readQuotedString($beforeQuote="#^([\n\t\r ]|)$#")
	{
		$string = -1;

		try 
		{
			$foundOpeningQuote = false;
			$quote =$this->readUntilCharExpected($beforeQuote, "#^(\"|')$#"); // Find the opening quote
			if( $quote == "\"" || $quote =="'")
				$foundOpeningQuote =true;
			$positionBeginStr = $this->fileReader->tell();
			$this->readUntilCharExpected("/^.?$/s", "#^$quote$#", '#^([^\\\]|)$#s'); // Read until a closing non-escaped quote's found (marking the end of the string) )
		}
		catch(JParserExpectedException $e)
		{
			$e->addAdditionalMessage("\nError occured while trying to parse a quoted string, expecting a ".((!$foundOpeningQuote)?"opening":"closing")." quote");
			throw $e;
		}

		// Extract the string
		$positionEndStr = $this->fileReader->tell();

		$this->fileReader->seek($positionBeginStr); 

		$string = fread($this->file, $positionEndStr - $positionBeginStr-1);
		$this->fileReader->getc(); // To move after the quote
	
		// And return it

		return $string;
	}

	// Values of return :
	// -1 : the chars expected to be before the expected character aren't the correct ones
	//  0 : reached end of file while the char expected hasn't been found
        private function readUntilCharExpected($charsBeforePattern, $expectedCharPattern, $patternCharJustBefore='', $position_start=-1) // Read chars matching a regex until we find the char expected while the previous chars matches a pattern and the last previous one matches a given pattern too
        {
		if($position_start >= 0)
			$this->fileReader->seek($position_start);

		$c = $this->fileReader->getc();
		//echo "read:".$c."\n";
		$pc = ''; // Precedent char
		do
		{
			if($this->debug_mode)
			echo $c."\n";
			if( $pc != '' && !preg_match($charsBeforePattern, $pc) )
			{
				// That means that the chars before the expected one aren't the ones that we expect to be before
				throw new JParserExpectedException("here", $this->filepath, $charsBeforePattern, $pc,$this->fileReader->tell(), -1, -1, $this->getSurroundingChars(-1));
				return false;
			}


			if ( preg_match($expectedCharPattern, $c) && (( $patternCharJustBefore != '' && preg_match($patternCharJustBefore, $pc) )||!$patternCharJustBefore) )
			{
				$found = true;
				break;
			}

			$pc = $c;
			$c = $this->fileReader->getc();
		}while($c != false);
		// We found the expected char or we're at the end of the file
		if($c == false && !$found)
		{
			// That means end of file
			throw new JParserExpectedException("there", $this->filepath, $expectedCharPattern, "end of file", $this->fileReader->tell(), -1, -1, $this->getSurroundingChars(-1) );
			return false;
		}

		// We found the character that matches the expected regexp
		return $c;
        }
	// Returns surrounding chars from the current position in the file given a length
	// Example :
	// File :
	// .......fjjknjdsnjdssdkqsfnqfjdjdbbjlsllsql__file_cursor__kqsdksqnkdnksqnfnkqkndnndgndgkngknnk.....
	//         <-------------------------------------length----------------------------------------->
	//						  |
	// 						func output
	public function getSurroundingChars($move=0,$length=40,$highlighter="*")
	{
		$this->savePos();

		$this->fmove($move);
		$length = (int)$length;
		
		$oldPos = $this->fileReader->tell();
		
		$c_actu = $this->fileReader->getc();
	

		$begin = ($oldPos - (int)($length/2) );
		$begin = $begin < 0 ? 0:$begin;
		
		$this->fileReader->seek($begin);
		
		$surrC = fread($this->file, $oldPos-$begin-1);
		$surrC .= $highlighter.$this->fileReader->getc().$highlighter;
		$surrC .= fread($this->file, $length/2);

		$this->loadPos();

		return $surrC;
	}

}
class JParserExpectedException extends Exception
{
	private $filename;
	private $expected;
	private $foundInstead;
	private $offset;
	private $lineInFile;
	private $column;
	private $optmessage;
	private $surroundingChars;
	public function __construct($optional_message,$filename, $expected, $foundInstead, $offset=-1, $line=-1, $column=-1, $surroundingChars='')
	{
		$this->optmessage =$optional_message;
		$this->filename = $filename;
		$this->expected = $expected;
		$this->foundInstead = $foundInstead;
		$this->offset = $offset;
		$this->lineInFile = $line;
		$this->column = $column;
		$this->surroundingChars = $surroundingChars;
		
		$message = '';
		if($this->filename)
			$message .= "File '$this->filename' : ";
		$message .= "expecting '$this->expected', found '$this->foundInstead' ";
		if( $this->offset !=-1)
		$message .= " at offset $this->offset";
		if($this->lineInFile != -1)
			$message .= ", line $this->lineInFile, ";
		if($this->column != -1)
			$message .= ", character #$this->column";
		if($this->surroundingChars!='')
			$message .= "\nSurrounding chars :\n...$this->surroundingChars...";
		if($this->optmessage!='')$message .= "\nOther info : ".$this->optmessage;

		$this->message = $message;
	}
	public function getExpected()
	{
		return $this->expected;
	}
	public function setExpected($ex)
	{
		$this->expected = $ex;
	}
	public function setFound($f)
	{$this->foundInstead =$f;}
	public function getFound()
	{
		return $this->foundInstead;
	}
	public function getAdditionalMessage()
	{
		return $this->optmessage;
	}
	public function setAdditionalMessage($m)
	{
		$this->message = substr($this->message,0,strlen($this->message)-strlen($this->optmessage)).$m;
		$this->optmessage =$m;
	}
	public function addAdditionalMessage($m)
	{$this->setAdditionalMessage($this->getAdditionalMessage().$m);}
	public function getSurroundingChars()
	{
		return $this->surroundingChars;
	}
	public function getFilename()
	{return $this->filename;}
	public function getOffset()
	{return $this->offset;}
}
class FetchState
{
	public function __construct($pos=0)
	{
		$this->fileOffset = $pos;
		$this->depth = 1;
		$this->in_quotes = false;
		$this->quote = '';
		$this->closing_brackets =array();
		$this->count_closing_brackets=0;
		$this->c ='';
		$this->pc ='';
		$this->first_fetch =false;
		$this->type = JSONobj::$UNDEFINED; // object / array / value num / value str
	}
	public function merge($obj)
	{
		foreach($this as $key => $val)
		{
			if(isset($obj->$key))
				$this->$key = $obj->$key;
		}
	}
	public $add_child_index_ch = 0;
	public $index_childs = 0;

	public $firstTime;
	public $c;
	public $pc;
	public $depth;
	public $in_quotes;
	public $quote;
	public $fileOffset;
	public $closing_brackets;
	public $count_closing_brackets;
	public $type;

	public function isDone() // is it the final fetch
	{
		return $this->c === false || $this->depth == 0;
	}
}

?>

