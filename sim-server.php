<?php
error_reporting(0); 
include "Simulation.php";

$historian_plantname = $_GET['plantname'];
if(!$historian_plantname) die ("{'Error':'invalid plant_id'}");
$strSQL = "SELECT PModelName FROM plants WHERE plant_id='".$historian_plantname."'";
$Connector = new DbConnector();
$Query = $Connector->query($strSQL);
$plantname = mysql_fetch_row($Query);
if(!$plantname) die ("{'Error':'invalid plant - it does not have model already'}");

$start = $_GET['start'];
$end = $_GET['end'];

if(!$start) die ("{'Error':'invalid period beggining'}");
if(!$end) die ("{'Error':'invalid period ending'}");

$d_start = strtotime($start);
$d_end = strtotime($end);
$horas = ($d_end-$d_start)/3600;
$energy = $_GET['energy'];

$keys = array("plantname","start","end","energy");
$products = array_diff_key($_GET,array_flip($keys)); //linea magistral!

if($horas<150){
$products = array_map('represent1000',$products);
}else{
$products = array_map('represent',$products);
}

$strJson = json_encode($products);

if($products['LOX']||$products['LIN']){
$asu=new Simulation();
$asu->PlantName = $plantname[0];
$asu->productions($strJson); //
$asu->energy = $energy;
$asu->interval($start,$end);
$gap=$asu->simulate();

$simResults=Array();
$simResults["Plant"] = $asu->PlantName;
$simResults["Period"] = $asu->CalendarTime;
$simResults["energyACTUAL"] = $gap['mwhActual'];
$simResults["energyMODEL"] = $gap['mwhModel'];
$simResults["energyGAP"] = $gap['energyGap'];
$simResults["LARACTUAL"] = $gap['larActual'];
$simResults["LARMODEL"] = $gap['larModel'];
$simResults["LARGAP"] = $gap['larGap'];
$simResults["LCU"] = $gap['LiqCap'];
$simResults["ACU"] = $gap['AirCap'];
$simResults["RCU"] = $gap['Rec1Cap'];
$simResults["Air"] = $gap['AirFlow'];
$simResults["recycleFlow"] = $gap['Recycle1Flow'];
$simResults["excessAir"] = $gap['ExcessAirforAr'];
	
	
header("Content-type: application/json");
$json = json_encode($simResults);
echo $json;	

}
function represent($number,$dig = 4,$div = 1){
	return number_format($number/$div,$dig,".","");
}
function represent1000($number){
	return represent($number,2,1000);
}

?>
