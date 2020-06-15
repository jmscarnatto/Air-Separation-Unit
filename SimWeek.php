<?php
require_once "Simulation.php";
class SimWeek extends Simulation{
	public $weekNumber;
	public $yearNumber;
	public $plant;
	public $asu;
	public $GapInfo;
		
	function RunWeekSimulation(){
		if(!$this->weekNumber) die("define a week number");
		if(!$this->yearNumber) die("define a year number");
		$sql = "SELECT sum(kwh) as energy,
				sum(LOX) as LOX,
				sum(LIN) as LIN,
				sum(LAR) as LAR,
				sum(GOX1) as GOX1,
				sum(GOX2) as GOX2,
				sum(GAN1) as GAN1,
				sum(GAN2) as GAN2,
				sum(GAR) as GAR,
				datetime as start,(INTERVAL 7 DAY + datetime) as end FROM cryo__ppddaily WHERE plant='".$this->plant."' AND year(datetime)=".$this->yearNumber." AND weeknumber=".$this->weekNumber;
		//echo $sql."<br><br>";
		$this->asu=new Simulation();
		$this->asu->PlantName = "ASU_".$this->plant;
		$this->asu->getSettings();

		$conn = New DbConnector();
		$query = $conn->query($sql);
		$row = $conn->fetchArray($query);
		$stringProd = '{"LOX":'.$row["LOX"].',
						"LIN":'.$row["LIN"].',
						"LAR":'.$row["LAR"].',
						"GOX1_S1":'.$row["GOX1"].',
						"GOX2_S1":'.$row["GOX2"].',
						"GAN1_S1":'.$row["GAN1"].',
						"GAN2_S1":'.$row["GAN2"].',
						"GAR_S1":'.$row["GAR"].'}';
		$stringEner = $row["energy"];
		$this->asu->productions($stringProd);
		$this->asu->energy = $stringEner;

		$this->asu->interval($row["start"],$row["end"]);
		$this->asu->planned_stop_hours = 0;
		//$this->asu->weather('{"AirTemp":25,"CWTemp":22,"RH":0.77}');
		//$this->asu->otherSettings('{"FeedFlow":4500}');
		//$this->asu->simulate();
		//echo $this->asu->GAPinfo['mwhActual']." => ".$this->asu->GAPinfo['mwhModel']."<br>";
		//echo $this->asu->GAPinfo['energyGap']."<br><br>";
		$this->GapInfo = $this->asu->simulate();;// array('hola','mundo');   //$this->asu->GAPinfo['mwhActual'];
		//echo "<span>".$this->represent($this->asu->ModelAir,8)."</span>";
		return $this->GapInfo;
	}
}

?>
