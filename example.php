<html>

<style type='text/css'>
body{
text-align:left; /* fundamental para cross browser centering (IE)*/
}
li{
list-style-type:circle;
border-bottom:1px solid #eee;
font-family:verdana;
font-size:10px;
}
span{
color:;
}
</style>

<body>

<?php
include "simulation.php";

$rec = $_GET['rec'];
$air = $_GET['air'];

$asu=new Simulation();
$asu->PlantName = "ASU_fake_plant_name";
$asu->productions('{"LOX":5246,"LIN":3596,"LAR":339,"GOX1_S1":10042,"GAN1_S1":914}'); //
$asu->energy = 11926000;
$asu->interval('1-07-2012','1-08-2012 01:00:00');
$asu->stop_hours(0);// stop_hours(GRAL[, LARadic]);
$asu->weather('{"AirTemp":30,"CWTemp":32,"RH":0.77}');
$asu->otherSettings('{"FeedFlow":6900}');
$gap=$asu->simulate();

	echo "<ul>";
	echo "<li> Plant: <u><i>".$asu->PlantName." </i></u></li>";
	echo "<li class='last'> Period: ".$asu->CalendarTime." hrs</li>";
	echo "<li> Energy ACTUAL: ".$gap['mwhActual']." Mwh</li>";
	echo "<li> Energy MODEL: ".$gap['mwhModel']." Mwh</li>";
	echo "<li> Energy GAP: ".$gap['energyGap']." %</li>";
	echo "<li> LAR ACTUAL: ".$gap['larActual']." kNm3</li>";
	echo "<li> LAR MODEL: ".$gap['larModel']." kNm3</li>";
	echo "<li> LAR GAP: ".$gap['larGap']." %</li>";
	echo "<li> LCU: ".$gap['LiqCap']." %</li>";
	echo "<li> ACU: ".$gap['AirCap']." %</li>";
	echo "<li> RCU: ".$gap['Rec1Cap']." %</li>";
	echo "<li> Air: ".$gap['AirFlow']." Nm3/hr</li>";
	echo "<li> Recycle flow: ".$gap['Recycle1Flow']." Nm3/hr</li>";
	echo "<li> Excess Air for Ar: ".$gap['ExcessAirforAr']." Nm3/hr</li>";
	echo "</ul>";
?>

</body>
</html>
