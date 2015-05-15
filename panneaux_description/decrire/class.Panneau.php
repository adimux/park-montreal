<?php
require_once('class.DateSchedule.php');
require_once('class.DBTranslatable.php');
require_once('dbcon.php');
/**
 * classe Panneau
 * Avec les fonctions statiques, on peut :
 * Enregistrer les modifications d'un Panneau dans une base de données à partir de son CODE_RPA qui est son id
 * Extraire un panneau de la bdd à l'aide de son id (CODE_RPA) : la fonction retourne un objet Panneau
 *
 * Fonctions non-statiques :
 * Lister les schedules (périodes d'interdiction)
 * Lister les véhicules autorisés
 * ...
 * 
 *
 * @author Adimux <adam@cherti.name>
 * @version 0.1
 * @copyright (C) 2015 Adimux <adam@cherti.name>
 * @license MIT
 */




class Panneau
{
	private $code;

	private $estAutorisation; // Une autorisation ou une interdiction ? le plus souvent interdiction
	private $amende; // int : combien d'amende indique le panneau
	private $tarifie; // bool

	private $type; // PX, A ou S ou PX-PH (pannonceau excpté période interdite) 
	// ou PX-REMORQUAGE : Zone de remorquage
	// ou PX-AMENDE
	private $descr_rpa;
	private $image_link; // link to image
	private $image_path; // path to image in the server

	private $temps_max; // temps max autorisé à stationner

	private $schedules; // arrya(str cas => objet DateSchedule, ...) : array de schedules où s'applique le panneau
	// Cas = 'ALL' pour la plupart des fois
	private $schedules_text; // array(str cas => str schedule, ..)
	private $vehicules_autorises; // array(DBTranslatable) : array des véhicules autorisés (auxquels ne s'applique pas l'interdiction)

	private $reserve_a; // Espace de stationnement Réservé à '',  si applicable

	// update le panneau dans la bdd
	public static function updatePan($code, $fields_vals, $schedules, $vehicules_aut)
	{
		DB::update("signalisation-descriptif-rpa-beta", $fields_vals, "CODE_RPA=%s",$code );
		foreach($schedules as $item)
		{
			$sc = trimUltime($item["schedule"] );
			$cas = trimUltime($item["cas"]);
			DB::query("SELECT id FROM `".tbl_pan_schedules."` WHERE CODE_RPA=%s AND cas=%s AND schedule=%s ", $code, $cas, $sc);
			if ( DB::count() == 0)
			{
				DB::insert("rpa-schedules", array("CODE_RPA"=>$code, "cas"=>$cas, "schedule"=>$sc));
			}

		}
		foreach($vehicules_aut as $veh)
		{
			if( !$veh['id'] )
				continue;
			DB::query("SELECT id FROM `".tbl_pan_vehicules."` WHERE CODE_RPA=%s AND id_vehicule=%i", $code, $veh["id"] );
			if( DB::count() == 0)
			{
				DB::insert("rpa-vehicules-autorises", array("CODE_RPA"=>$code, "id_vehicule"=>$veh["id"] ) );
			}
		}
	}
	public static function getPanneau($code)
	{
		return self::getPanneauFromRow(DB::query("SELECT * FROM `signalisation-descriptif-rpa-beta` WHERE type != '' AND CODE_RPA=%s LIMIT 1",$code)[0] );
	}
	public static function getPanneauFromRow($row)
	{
		$pan = new Panneau();

		$requiredFields = array('CODE_RPA','DESCRIPTION_RPA','autorise','type','image','temps_max','reserve','amende','tarifie');
		foreach($requiredFields as $field)
		{
			if( !isset($row[$field]))
				throw new Exception("Error : Field '$field' required to create Panneau");
		}

		$pan->code = $row['CODE_RPA'];
		$code = $pan->code;
		$pan->estAutorisation=$row['autorise'];
		$pan->type = $row['type'];
		$pan->image_link = $row['image'];
		$pan->temps_max = $row['temps_max'];
		$pan->descr_rpa = $row['DESCRIPTION_RPA'];

		// extraire les schedules texte et créer les obj DateSchedule
		$pan->schedules = array();
		$pan->schedules_text =array();
		$sched = DB::query("SELECT * FROM `".tbl_pan_schedules."` WHERE CODE_RPA=%s",$pan->code);
		foreach($sched as $sc)
		{
			$cas = $sc["cas"];
			$sched_text = $sc["schedule"];
			$pan->schedules[] =array($cas => (new DateSchedule($sched_text) ));
			$pan->schedules_text=array($cas=>$sched_text);
		}

		// extraire les véhicules autorisés
		$pan->vehicules_autorises =array();
		$res = DB::query("SELECT * FROM `".tbl_pan_vehicules."`  INNER JOIN `".tbl_vehicules."` ON ( `".tbl_pan_vehicules."`.id_vehicule = `".tbl_vehicules."`.id ) WHERE `".tbl_pan_vehicules."`.CODE_RPA = '$code'");

		foreach($res as $v)
		{
			// Pour avoir la traduction du nom dans diff langues
			$veh = new DBTranslatable(tbl_vehicules, tbl_vehicules_tr, "id", "id_vehicule",array("nom"=>"translation"));
			$veh->affect($v);
			$pan->vehicules_autorises[]=$veh;
		}

		return $pan;
	}
	public function getDescription()
	{
		return $this->descr_rpa;
	}
	public function getSchedules()
	{
		return $this->schedules;
	}
	public function getSchedulesText()
	{
		return $this->schedules_text;
	}

	public function getVehicules()
	{
		return $this->vehicules_autorises;
	}
}

$pan = Panneau::getPanneau("AD-TT");

$schedules_txt = $pan->getSchedulesText();
$schedules = $pan->getSchedules();
$now=new Datetime();

$sc= $schedules[0]['ALL'];
echo "YES/NO ".intval( $sc->inPeriod($now))."\n";
var_dump($schedules_txt);

echo $pan->getDescription()."\n";
?>
