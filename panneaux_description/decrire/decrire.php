<?php
require_once('class.Panneau.php');
require_once('dbcon.php');

/**
 * Lit les entrées de la table de signalisation des panneaux qui n'ont pas encore
 * été "traduits" en langage compréhensible par l'application et essaie de les traduire,
 * sinon demande de l'aide à la personne qui éxécute le script
 * 
 * @author Adimux <adam@cherti.name>
 * @version 0.1
 * @copyright (C) 2015 Adimux <adam@cherti.name>
 * @license MIT
 */



/*$options = array("host"=>"mysql.adam.cherti.name","dbname"=>"adimux","username"=>"adimux","password"=>"adimuxpass11");
$options["d"] = "mysql";
$connec_options = "host=".$options["host"].";dbname=".$options["dbname"].";".
$pdo_options = array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION); // Activate exceptions as error mode
if( $ismysqlutf8 )
	$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND]    = "SET NAMES utf8"; // Set database connection as utf8
 
$conn = new PDO($options["d"].":".$connec_options, $options["username"], $options["password"], $pdo_options);
 */
//DB::query("SET NAMES utf8");


function reg_extract($reg,$str,&$matches=null)
{
	if( preg_match($reg,$str,$m, PREG_OFFSET_CAPTURE))
	{
		$to_extract = $m[0][0];
		$pos = $m[0][1];
		$str = substr($str,0,$pos).substr($str,$pos+strlen($to_extract));

		$newm = array();
		for($i =0; $i < count($m); $i++)
		{
			$newm[] = $m[$i][0];
		}


		if($matches!==null)
			$matches = $newm;

		return $str;
	}
	else
	{
		$matches=array();
		return $str;
	}
}

function id_vehicule($nom, $liste)
{
		foreach($liste as $vehicule)
		{
			if( strcasecmp($nom ,$vehicule['nom']))
				return $vehicule['id'];
		}
}
$delete_code_rpas = array();

function getResults($q)
{
	$r=array();
	foreach($q as $row)
		$r[] =$row;
	return $r;
}
function rows_search($rows, $equalities, $callback=null)
{
	$results=array();

	foreach($rows as $row)
	{
		if( $callback!==null)
		{
			if( $callback($row,$equalities) )
				$results[] =$row;
		}
		else
		{
		foreach($equalities as $column => $value)
		{
			echo $column . "=>".$value." ".$row[$column]."\n";
			if( $row[$column] == $value)
				$results[] = $row;
		}
		}
	}
	return $results;
}
function trimUltime($chaine){
	$chaine = trim($chaine);
	$chaine = str_replace("\t", " ", $chaine);
	$chaine = eregi_replace("[ ]+", " ", $chaine);
	return $chaine;
}
//$deleteQuery = $conn->prepare("DELETE FROM `signalisation-descriptif-rpa-beta` WHERE CODE_RPA = :code_rpa");
echo "keeeet\n";
$liste_vehicules = getResults(DB::query("SELECT * FROM `".tbl_vehicules."`"));

echo "we delete the inutile !\n";

//$conn->query("DELETE FROM TABLE `signalisation-descriptif-rpa-beta` WHERE CODE_RPA REGEXP '^[^SAPR]' ");
foreach( DB::query("SELECT CODE_RPA,DESCRIPTION_RPA FROM `".tbl_pan."` WHERE CODE_RPA REGEXP '^[SAPR].*' AND type= '' ")
	as $row)
{
	$code = $row['CODE_RPA'];
	$descr_rpa = trimUltime($row['DESCRIPTION_RPA']); // C'est celui qu'on va tenter de "traduire" en schedule utilisable par la clases DateSchedule
	$segments = explode("-",$code);

echo "CODE : ".$code."\n";

	$part1 = str_split($segments[0]);
	$part2 = str_split($segments[1]);
	$part3 = $segments[2];

	$type_signalisation = ''; // Le type sera déterminé par S : stat interdit ou permis à durée limitée
	// A :arrêt interdit // PX : pannonceau de précison // PP : stationnement tarifié
		$schedules = array(); // La description du schedule qui est utilisable par la class DateSchedule
		// Ce sera un array de cette forme :
		// ( "all"      # "pour les cas normaux", le schedule est :
		//  =>  ["W1-5 D14-18" , interv2, ...]   # liste d'intervalles qui font du sens pour la classe DateSchedule (entre ces intervalles y a une union OU)
		//  , "clignotant" # pour le cas de stationnement en clignotant
		//  => [interv1 , interv2, ...]
		//  , "autrecas"=>[], ... )

	$duree_max = '';// durée max de stationnement si applicable	
	$image = ''; // chemin vers l'image du panneau
	$reserve_a = ''; // si la place est réservée à un véhicule / les résidents d'un immeuble, le précisier ici

		//  A : arrêt interdit ou permis à durée limitée
		

		// Les deux lettres avant le tiret
		$lettre1 = $part1[0];
		if( isset($part1[1]) )
			$lettre2 = $part1[1]; // optionelle
		else
			$lettre2='';
		
		// Les deux lettres après le tiret
		$lettre3 = $part2[0];
		$lettre4 = $part2[1];

		
		if( $lettre1 =="A" ) // Arrêt interdit
			$type_signalisation = 'A';
		else if($lettre1 == "P" && $lettre2 == "X") // pannonceau de précision
			$type_signalisation = 'PX';
		else  // ça veut dire que c'est soit  P ou S ou R qui sont tous des interdictions de stat
			$type_signalisation = 'S';

		// Les éléments du schedule
		$jours = '';
		$heures = '';
		$mois = '';
		$amende = 0;
		$tarifie=0;
		$jours_ecole = false;
		$estAutorisation =false; // Par défaut c'est un panneau d'interdiction pas d'autorisation 
	
		$vehicules_autorises = array(); // Les véhicules qui ne sont pas soumis à cette restriction (EXCEPTE CAMIONS par exemple)

	
	/*	if( $lettre2 == "E") // jours d'école
	{
			$jours_ecole = true;
			echo "J ECOLE\n";
		
	}*/
		if($lettre2 == "L") // livraison seulement
			$vehicules_autorises[] = rows_search($liste_vehicules, null,
				function($row)
				{
					return preg_match("#v(é|e)hicule de livraison#i",$row["nom"] );
				} )[0];
		
/*		if( $lettre2 == "V") // lun au ven
			$jours = "W1-5";
		else if( $lettre2 == "D") // lun au dim
			$jours = "W1-7";
		else if( $lettre2 == "S") // lun au sam
			$jours = "W1-6";
*/
//		if( $lettre3=="T") // en toute heure de la journée
//			$heures='D0-23';

		$weekday =	"(?:(?:LUN\.?|LUNDI)|(?:MAR\.?|MARDI)|(?:MER\.?|MERCREDI)|(?:JEU\.?|JEUDI)|(?:VEN\.?|VENDREDI)|(?:SAM\.?|SAMEDI)|(?:DIM\.?|DIMANCHE)),?";
		$month = "(?:(?:JAN\.?|JANVIER)|(?:F[ée]V\.?|F[ée]VRIER)|(?:MRS|MARS)|(?:AVRIL|AVR\.?)|MAI|JUIN|JUILLET|AO[ûu]T|(?:SEPT\.?|SEPTEMBRE)|(?:OCT\.?|OCTOBRE)|(?:NOV\.?|NOVEMBRE)|(?:D[ée]C\.?|D[ée]CEMBRE))";
		$monthDay = "(?:[0-9]+|1er)";
		$timerange= "[0-9]+(?:[Hh][0-9]*)?(?: ?- ?| [AÀà] )[0-9]+([Hh][0-9]*)?";

		$descr_rpa = preg_replace("#(\(|\))#","", $descr_rpa);
		echo $descr_rpa."\n";

		if( preg_match("#ANNULE#",$descr_rpa ) || !preg_match("#^[SAPR]#",$code) )
		{
			echo "delete it!\n";
			echo "delete $code ? ";
//			if( preg_match("#^ye?s?#i",fgets(STDIN)))
//			{
				DB::query("DELETE FROM `".tbl_pan."` WHERE CODE_RPA=%s", $code);
//			}
			continue;
		}


		$r=$descr_rpa;
		if( preg_match("#SLR-ST#i",$code)&&!preg_match("#^\\\\p#iu",$descr_rpa) )
		{
			$lettre1 = "P";
			$descr_rpa = "PANONCEAU ".$descr_rpa;
			$lettre2 = "X"; // pannonceau
		}


		// JOURS D ECOLE
		$m=array();
		
		$descr_rpa = reg_extract("#JOURS D.ECOLE#u",$descr_rpa,$m);
		if( isset($m[0])) // On a trouvé jours d'école
		{
			$jours_ecole = $m[0] != '';
			echo "J ECOLES\n";
		}

		// EXTRAIRE RESERVE à ..
		$descr_rpa = reg_extract("#R[ÉE]S[ÉE]RV[EÉ]( (S3R|[^0-9]+))?#ui",$descr_rpa,$m);
		if( count($m))
		{
			$reserve_a = trim( $m[1] );
			if($reserve_a=='')
				$reserve_a = 'RESERVE';
			$descr_rpa = preg_replace("#RESERVE $reserve_a#","",$descr_rpa);
			echo "RESERVE A : '".$reserve_a."'\n";
		}

		// EXTRAIRE première lettre qui indique le type 

		$descr_rpa = reg_extract("#AUTOCOL\. #",$descr_rpa,$m);
		$autocol = ($m[0] != '');

		$REG ="";
		$estPannonceau =false;
		if(preg_match("#PANN?ONCEAU#i",$descr_rpa) || ($lettre1 == "P" && $lettre2 =="X")) // pannonceau
		{
			$estPannonceau=true;
			$couleur = "(?:NOIR|BLEU)";
			$REG="^PANONN?CEAU(?: $couleur)? (?:- )?";
			
		}
		else if( $lettre1 == "A" ) // interdit - excepté
			$REG="^(\\\\|/)A ";
		else if($lettre1 == "R" || $lettre1=="S" || $autocol ) // interdit - excepté
			$REG="^(\\\\|/)P ";
		else if( $lettre1 == "P")
			$REG="^P ";

		if($lettre1 == "P") // stat permis à durée limitée
		{
			$REG.="(([0-9]+) ?(min\.?|H|h)([0-9]+)? [^Aà]|STAT. A 45 cm DE LA BORDURE - )?";
		}

		if( preg_match("#TARIFI?E?#i",$descr_rpa))
		{
			echo "Cette merde est tarifiée\n";
			$REG = "^(HORAIRE DE STATIONNEMENT TARIFI?E|STATIONNEMENT TARIFI?E?) ?";
			$tarifie=1;
		}


		if( preg_match("/^REMORQUAGE/i",$descr_rpa))
		{
			$type_signalisation="PZ-XT";
			echo "REMORQUAGE !!\n";
		}

		if( preg_match("#^RH-T-[0-9]+$#i",$code )
	|| preg_match("#(VIRAGE|Balise|ZONE DE LIVR|M.TRES|MESSAGER|NON EXISTANT|EXCLUSIF|NON-IDENTIFIABLE|SOYEZ VISIBLE|OBSTACLE|TRANSIT|INTERDICTION|SENS|VOIE|AV\.|GAUCHE|DROITE|AERIEN|LARGEUR|SEL|CIRCULATION|MANOEUVRE|DEVIATION|INTERDIT|PIETONS|OBLIG.|FLUO|MAXIMUM [0-9]+ |JAUNE|SECOURS|PRE-SIGNAL|PASSAGE|ECOLIERS|TERRAIN|CEDEZ|FEU|ACCES|RECULEZ|FERMEE|CONF[IU]GURATION|JONCTION|CAHOTEUSE|ATTENTION|LENTEMENT|D.NIVELATION|SIGNAL|DANGER|vitesse|autoroute|sp.cifique|poste de police|sans issue|radio|civique|RISQUES|Veuillez|Please|Glace|tout droit|SORTIE|^ENTR.E SEULEMENT|RAPPEL|variables|km/h|Plage| rue |Propri.t. priv.e|^aire d'attente|m.tro|POSTE D'ATTENTE|ARRÊT|CYCLISTES|SURVEILLANCE|DIRECTION|ATTENDRE|PRESSER|OBLIGATION|FIN|DEBUT|distance|TOPONYMIE)#ui",$descr_rpa) )
		 {
	 		echo "delete it $code plz !\n";
			DB::query("DELETE FROM  `signalisation-descriptif-rpa-beta` WHERE CODE_RPA=%s", $code);
			continue;
		 }
	
		


		echo $REG."\n";
		$old_descr_rpa =$descr_rpa;

		$descr_rpa=reg_extract("#$REG#siu",$descr_rpa,$m);

		if( $lettre1 == "P")
		{
			echo "POS "."\n";
			echo $old_descr_rpa."\n";
			echo $descr_rpa."\n";
			$l = substr($old_descr_rpa, strpos($old_descr_rpa, $descr_rpa)-1,1);
			if( !preg_match("#^[aà]$#iu",$l))
			{
				$descr_rpa=$l.$descr_rpa;
			}
		}


		if( count($m) || $continue)
		{
			if($estPannonceau)
			{
				if( preg_match("#EXCEPT(E|é) [0-9]+#iu",$descr_rpa) )
				{
					echo "C'est une autorisation\n";
					$estAutorisation = true;
				
					$descr_rpa =reg_extract("#EXCEPT(E|é) #i",$descr_rpa);
					
				}
				if( preg_match("#ZONE DE REMORQUAGE#i",$descr_rpa))
				{
					echo "C'est une zone de remorquage\n";
					$type_signalisation = 'PX-REMORQUAGE';
				}
				$v=array();
				if( preg_match("#AMENDE ([0-9]+)\\$#",$descr_rpa,$v))
				{
					$type_signalisation = 'PX-AMENDE';
					$amende = intval($v[1]);
					echo "C'est une amende de $amende\n";
				}
		}
		if($lettre1=="P")
			{
				$max = $m[1];
				if( preg_match("#bordure#i",$max))
				{
					echo "c une auth\n";
					$estAutorisation=true;
				}
				else
				{
					$max = $m[2];
				$unite = $m[3];
				$minutes = $m[4];

				$duree_max = 0;

				echo "U:".$m[2]."\n";
				if(preg_match("#min#i",$unite) )
					$duree_max += intval($max);
				else
					$duree_max += intval($max)*60;

				if( $minutes != '')
					$duree_max += intval($minutes);

				echo "DUREE MAX $duree_max minutes\n";
				}
			}
echo "HEY exc ".$descr_rpa."\n";
			$REG= " ?(?:(EXCEPT[EÉ]) (S3R|[^0-9]+))";
			//********** Extraire le truc d'excepté
			$descr_rpa = reg_extract("#$REG#ui",$descr_rpa,$m);
			if(count($m))
			{
				$nom ='';
				if( $m[1]=="EXCEPTE" ) //Excepté
					$nom = trim($m[2]);
				
				if( $nom == "PERIODE INTERDITE" )
				{
					$type_signalisation = "PX-PH";
					echo "c'est un PX-PH";
				}
				else{

					$nom = trim($nom);
					if( preg_match("#livraison#i",$nom))
						$nom="livraison";
				$vehicule = rows_search($liste_vehicules, array("nom"=>$nom),
					function($row,$search)
					{
						$nom =$search["nom"];
						return preg_match("#$nom#i",$row["nom"]);
					});
				// On ne l'a pas trouvé dans la liste des véhicules donc on demande à l'utilisateur quoi faire
				if( count($vehicule)==0 )
				{
					echo "Quel est l'id du véhicule $nom ? ";
					fscanf(STDIN,"%d\n",$id_veh);
					if( $id_veh!='')
					{
					$liste_vehicules[] = array("nom"=>$nom, "id"=>intval($id_veh));
					$vehicule = $liste_vehicules[count($liste_vehicules)-1];
					}
					
				}
				else // On l'a trouvé
					$vehicule=$vehicule[0];

				$vehicules_autorises[] = $vehicule;

				echo "SEULEMENT '$nom' ".$vehicule["id"]."\n";
				}
			}
			else
			{
				$REG = "#AUX ([^0-9]+)#iu";
				$descr_rpa = reg_extract( $REG, $descr_rpa,$m);
				if( count($m) )
				{
					$nom_veh = trim($m[1]);
					$vehicules_autorises = $liste_vehicules;
					for($i=0;$i<count($vehicules_autorises);$i++)
					{
						if(strtolower( $vehicules_autorises[$i]["nom"]) == strtolower($nom_veh))
						{
							echo "unset véhicule $nom_veh : $i";
							unset($vehicules_autorises[$i]);
						}
					}
				}
//				var_dump($vehicules_autorises);
			}

			$REG_seulement="#([^0-9]+) (SEULEMENT)#i";

			echo "ch seul : $descr_rpa\n";
			$descr_rpa=  reg_extract($REG_seulement, $descr_rpa,$m);
			if(count($m))
			{

				$autorise = 1;
				echo "Ce truc est une fking auth\n";
				$nom = trim($m[1]);
				$vehicule = rows_search($liste_vehicules, array("nom"=>$nom),
					function($row,$search)
					{
						$nom =$search["nom"];
						return preg_match("#$nom#i",$row["nom"]);
					}); 
				// On ne l'a pas trouvé dans la liste des véhicules donc on demande à l'ut
				if( count($vehicule)==0 )
				{
					echo "Quel est l'id du véhicule $nom ? ";
					fscanf(STDIN,"%d\n",$id_veh);
					if( $id_veh != -1 && $id_veh != '' && preg_match("#^[0-9]+$#i",$id_veh) && intval($id_veh) > 0 )
					{
						$liste_vehicules[] = array("nom"=>$nom, "id"=>$id_veh);
						$vehicule = $liste_vehicules[count($liste_vehicules)-1];
					}

				}
				else // On l'a trouvé
					$vehicule=$vehicule[0];

				$vehicules_autorises[] = $vehicule;

				echo "SEULEMENT '$nom' id:".$vehicule["id"]."\n";
			}



			$success_intervalles = true;

			while(true)
			{
				//******* Maintenant extraire date/heure, etc..
				//
				/// HEURES
				echo "HERE hours $descr_rpa\n";
				$t=array();
				//$timerange = "[0-9]+[Hh] [ÀAa]";

				//preg_match("#$timerange#ui",$descr_rpa,$t);
				$REG = "#^ *(- )?($timerange(?:(?: (?:ET|-) |, ?| )($timerange))?)#ui";
				$descr_rpa = reg_extract($REG, $descr_rpa,$m);
				if( count($m) )
				{
					preg_match_all("#$timerange#iu", $m[0], $matches);
					foreach( $matches[0] as $h )
					{
						echo $h."\n";
						$a=explode("-",$h);
						if( count($a) ==1)
						{
							$n=array();
							preg_match("#^(.*)à(.*)#iu",$h,$n);
							$a = array( $n[1], $n[2]);
						}

						$s = preg_replace("#^([0-9]+)$#i", "$1h", trim($a[0]));
						$f= preg_replace("#^([0-9]+)$#i", "$1h", trim($a[1]));



						$s=preg_replace("#h#i",":",$s);
						$f=preg_replace("#h#i",":",$f);
						echo "'$s'\n";
						echo "'$f'\n";
						$s= preg_replace("#:$#i",":00",$s);
						$f= preg_replace("#:$#i",":00",$f);

						$ds = (new DateTime())->modify($s);
						$df = (new DateTime())->modify($f);
						if($ds > $df)
						{
							//						echo "erreur heure non suivies\n";
							//						$success_intervalles = false;
							$heures .= "D$s-23:59 D00:00-$f";
						}
						else

							$heures .= "D$s-$f ";

					}
					//				$heures = "D".preg_replace("#h#i", ":", implode(" D",$matches[0]) );
					echo"H:". $heures."\n";
				}
				if(preg_match("#$timerange#i",$descr_rpa))
				{
					//				echo "Quelles sont les heures ? ";
					//				$heures = fgets(STDIN);
					echo "erreur dans les heures\n";
					$success_intervalles = false;
				}


				// jours de le sem
				if(!$jours_ecole)
				{
					$REG = "#^( *| *- *)?(?:(?:((?:$weekday)(?:( ET | )$weekday(?:(?: ET | )$weekday(?:(?: ET | )$weekday)?)?)))|(?:($weekday) ([AÀ]U?) ($weekday)))( |$)#iu";
					echo $descr_rpa."\n";

					$m =array();
					$oneDay = false;

					$arrDays = array("LUN","MAR","MER", "JEU","VEN","SAM","DIM");

					echo "HERE sem ".$descr_rpa."\n";
					if( preg_match_all("# ?($weekday) #i", $descr_rpa." ", $matches, PREG_OFFSET_CAPTURE)>=1 )
					{
						$results =array();
						foreach($matches[1] as $occurence )
						{
							$position = $occurence[1];
							$mars = substr($descr_rpa,$position,4);
							echo "OCC : ".$occurence[0]."\n";
							if( $mars != "MARS")
								$results[] = $occurence;
						}
						if( count($results) == 1) // No more than one day we found
						{
							$descr_rpa = reg_extract("#".$results[0][0]."#ui",$descr_rpa);
							$day = trim($results[0][0]);

							foreach( range(0, count($arrDays)-1) as $i )
							{
								if( preg_match("#^".$arrDays[$i]."#i", $day) )
								{
									$day = $i+1;
									break;
								}
							}



							$jours = "W$day-$day";
							echo "oneday ".$jours."\n";

							$oneDay = true;
						}
					}
					if( !$oneDay )
					{
						$m=array();
						$descr_rpa = reg_extract($REG, $descr_rpa,$m);
						echo "xtract $descr_rpa\n";
						if( count($m) )
						{

							if( $m[2]!='' ) // Présence du "ET" ou juste " "
							{
								preg_match_all("#$weekday#i",$m[1],$matches);

								$days_from_to = $matches[0];
								$day_from=$days_from_to[0];
								$day_to=$days_from_to[1];
								$day_3=$days_from_to[2];
								$day_4 =$days_from_to[3];

								foreach( range(0, count($arrDays)-1) as $i )
								{

									if( preg_match("#^".$arrDays[$i]."#i", $day_from) )
										$day_from = $i+1;
									if( preg_match("#^".$arrDays[$i]."#i",$day_to) )
										$day_to=$i+1;
									if( preg_match("#^".$arrDays[$i]."#i",$day_3) )
										$day_3=$i+1;
									if( preg_match("#^".$arrDays[$i]."#i",$day_4) )
										$day_4=$i+1;


								}

								$jours = "W$day_from-$day_from W$day_to-$day_to";
								if( $day_3 )
									$jours .= "W$day_3-$day_3 ";
								if( $day_4 )
									$jours .= "W$day_4-$day_4 ";

								echo $jours;
							}
							else if( $m[4] ) // Présence du "AU"
							{
								$start = $m[3];
								$end = $m[5];

								for($i=0;$i<count($arrDays);$i++)
								{

									if( preg_match("#^".$arrDays[$i]."#i", $start) )
										$start = $i+1;
									if( preg_match("#^".$arrDays[$i]."#i",$end) )
										$end =$i+1;
								}

								$jours = "W$start-$end";
								echo $jours."\n";
							}

						}
					}
					if( preg_match("#$weekday#i", $descr_rpa) && !preg_match("#MARS#i",$descr_rpa))
					{
						//					echo "Quels sont les jours ?";
						//					$jours = fgets(STDIN);
						//					break;
						echo "erreur dans les jours sem\n";
						$success_intervalles = false;

					}
					$j++;
				}
				$descr_rpa = reg_extract("#^ *- #iu",$descr_rpa);
				$descr_rpa = reg_extract("#^ *- #iu",$descr_rpa);

				echo "here Months ".$descr_rpa."\n";
				// intervalles dates JJ MM
				$REG = "#^ *(DU )?(?:($monthDay ?)?($month)( AU | A | à | ?- ?)($monthDay ?)?($month))#iu";
				$descr_rpa = reg_extract($REG,$descr_rpa,$m);
				if(count($m))
				{
					$dayMonth1 = trim($m[1]);
					$month1 =$m[2];
					$dayMonth2 =trim( $m[4]);
					$month2 = $m[5];


					if($dayMonth1 =="1er")
						$dayMonth1 =1;
					if($dayMonth2 == "1er")
						$dayMonth2 = 1;

					$arrMo = array("JAN","FEV","MAR","AVR", "MAI", "JUIN", "JUILL", "AO.T","SEPT","OCT","NOV","D[E|é]C");
					foreach(range(0,count($arrMo)-1) as $i)
					{
						if( preg_match( "#^".$arrMo[$i]."#i", $month1))
							$month1 = $i+1;
						if( preg_match( "#^".$arrMo[$i]."#i", $month2))
							$month2 = $i+1;

					}
					$mois .= "Y".$month1;
					if( $dayMonth1 != '')
						$mois .= '/'.$dayMonth1;

					$month3='';
					$month4='';

					if( intval($month1) > intval($month2) )
					{
						$month3 = 1;
						$dayMonth3 = '';
						$month4 = $month2;
						$dayMonth4 = $dayMonth2;
						$month2 = 12;
					}



					$mois.= '-'.$month2;
					if($dayMonth2!='')
						$mois.= "/$dayMonth2";

					if( $month3 !='')
						$mois .= " Y$month3";
					if( $dayMonth3 != '')
						$mois .= '/'.$dayMonth3;
					if($month4!='')
						$mois.= '-'.$month4;
					if($dayMonth4!='')
						$mois.= "/$dayMonth4";



					echo "MOIS : ".$mois."\n";

				}
				if( preg_match("#$month#i", $descr_rpa))
				{
					echo "erreur dans les mois\n";
					$success_intervalles = false;
				}

				if( $success_intervalles )
				{
				if( $mois!='' || $jours!='' || $heures!='')
					$schedules[] = array( "cas"=>($jours_ecole?"SCHOOL DAYS":"ALL"), "schedule"=>"$mois $jours $heures");
				else
					$schedules[] = array("cas"=>($jours_ecole?"SCHOOL DAYS":"ALL"), 'schedule'=>$estPannonceau?"":"Y1-12");
				} 


				$descr_rpa=reg_extract("#^ *,#",$descr_rpa,$m);
				echo "FUUUUCK\n";
				if( count($m) )
				{
					echo "CONTINUE ".$descr_rpa."\n";
				}
				else
					break;


			}


			$path = '/home/adimux/cherti.name/adam.cherti.name/CREE TA VILLE/images_panneau/'.$code;
			echo $path."\n";
			$ext = ".JPG";
			$exts = array(".PNG",".png",".jpg", ".JPG", ".gif",".GIF");
			foreach($exts as $ext)
			{
				if( file_exists($path.$ext) )
				{
					$image = 'http://adam.cherti.name/CREE%20TA%20VILLE/images_panneau/'.$code.$ext;
					break;
				}
			}

			echo "Image : $image\n";


			if( !$success_intervalles )
			{
				echo "Donnez les intervalles : ";
				$res = fgets(STDIN);
				if( preg_match("#(con|continue)#i",$res) )
				{
					echo "ok forget  abbout it\n";
					continue;
				}
				else if( preg_match("#^(DEL|DELETE)#i",$res) )
				{
					echo "delete $code ? ";

					if( preg_match("#^ye?s?#i",fgets(STDIN)))
							DB::query("DELETE FROM  `signalisation-descriptif-rpa-beta` WHERE CODE_RPA=%s", $code);
				}
				else
				{
					$liste = explode(",", $res );
					foreach( $liste as $l)
					{
						$arr=explode("=",$l);

						$cas = "ALL";
						if( count($arr) > 1 )
						{
							$cas = $arr[0];
							$sc = $arr[1];
						}
						else
							$sc =$arr[0];
						$schedules[] = array("cas"=>$cas,"schedule"=> $sc);
					}
				}
			}
			else
			{
			/*	if( $mois!='' || $jours!='' || $heures!='')
					$schedules[] = array( "cas"=>($jours_ecole?"SCHOOL DAYS":"ALL"), "schedule"=>"$mois $jours $heures");
				else
					$schedules[] = array("cas"=>($jours_ecole?"SCHOOL DAYS":"ALL"), 'schedule'=>"Y1-12");
			 */
			}
			
			echo "RESULTS\n";

			foreach($schedules as $sc)
				echo $sc["cas"]." : ". $sc["schedule"]."\n";


			echo "GOOD ?";
			$insert=false;
//			$r=fgets(STDIN);
			if( preg_match("#^(y|yes)#i", $r) || 1==1)
			{
				$insert=true;
			}
			else if( preg_match("#^(del|delete)#i", $r) )
			{
				if( preg_match("#(y|yes)#i", fgets(STDIN) ) )
				{
					echo "delete it $code !\n";
					DB::query("DELETE FROM  `signalisation-descriptif-rpa-beta` WHERE CODE_RPA=%s", $code);
				}

			}
			else if( preg_match("#^(con|continue)#i",$r))
			{
				continue;
			}
			else if(preg_match("#^(no|n)#i", $r))
			{
				echo "autorisé ?";
				$estAutorise = intval(fgets(STDIN));
				echo "Entrez les intervalles : ";
				$schedules=array();

				$res=fgets(STDIN);
					$liste = explode(",", $res );
					foreach( $liste as $l)
					{
						$arr=explode("=",$l);

						$cas = "ALL";
						if( count($arr) > 1 )
						{
							$cas = $arr[0];
							$sc = $arr[1];
						}
						else
							$sc =$arr[0];
						$schedules[] = array("cas"=>$cas,"schedule"=> $sc);
					}
					$insert=true;
			}


		}
        else
		{
			echo "QUE FAIRE ?\n";
			if( preg_match("#^(DEL|DELETE)#i",fgets(STDIN)) )
			{
				echo "delete $code ? ";
//				if( preg_match("#^ye?s?#i",fgets(STDIN)))
//				{
					DB::query("DELETE FROM `signalisation-descriptif-rpa-beta` WHERE CODE_RPA=%s", $code);
//				}
				//$deleteQuery->execute(array(":code_rpa"=>$code));
			}
			else
			{
				$insert = true;
				echo "type ? ";
				$type_signalisation = fgets(STDIN);

				echo "tps max ? ";
				$duree_max = fgets(STDIN);
				echo "RESERVE à ? ";
				$reserve_a = fgets(STDIN);

				echo "autorisé ?";
				$estAutorisation = intval(fgets(STDIN));

				echo "excepté ?";
				$id_veh=intval(fgets(STDIN));
				$vehicules_autorises =array( array("id"=>$id_veh) );

				echo "Entrez les intervalles : ";
				$schedules=array();


				$res=fgets(STDIN);
					$liste = explode(",", $res );
					foreach( $liste as $l)
					{
						$arr=explode("=",$l);

						$cas = "ALL";
						if( count($arr) > 1 )
						{
							$cas = $arr[0];
							$sc = $arr[1];
						}
						else
							$sc =$arr[0];
						$schedules[] = array("cas"=>$cas,"schedule"=> $sc);
					}
					$insert=true;


			}


		}
			if($insert)
			{
				echo "insert shit !\n";
				Panneau::updatePan($code, array("tarifie"=>$tarifie,"amende"=>$amende,"autorise"=>$estAutorisation,"reserve"=>$reserve_a,"temps_max"=>$duree_max, "image"=>$image,"type"=>$type_signalisation), $schedules, $vehicules_autorises);
			}
		echo "\n";
}
if( DB::count()==0)
{
echo "ALL is good :)\n";
}
//$conn->prepare("DELETE FROM TABLE `signalisation-descriptif-rpa` WHERE CODE_RPA=:code_rpa");
//foreach($delete_code_rpas as $code_rpa)
//{
//	echo "Delete sign where coderpa = $code_rpa\n";
//	$conn->execute(array(":coderpa"=>$code_rpa));
//}
?>
