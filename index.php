<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">

<style>
	body{
	font-family: "Open Sans", sans-serif;
}
	
<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if(isset($_GET["cmd"])){
	require_once("CRON.php");
}
$_GET["cmd"]="QMOD";
require_once("CRON.php");

date_default_timezone_set('Europe/Bucharest');

$servername = "localhost";
$username = "***";
$password = "***";
$dbname = "shpe";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune e?uata: " . $conn->connect_error);
}

if(isset($_GET["days"])&&isset($_GET["debug"])){
	echo "<pre>";
	$r=$conn->query("SELECT * FROM inverter WHERE type='day' ORDER BY -dta");
	while($row=$r->fetch_assoc()){
		echo "<br>".$row["dta"].":".$row["total"].":".$row["PVw"];
	}
	exit;
}
?><style>
* { margin:0; padding:0; }
html, body { width:100%; height:100%; }
canvas { display:block; }
.button{
	float:left;
	width:16.66%;
	height:100px;
	background:#999;

	display:flex;
	align-items:center;
	justify-content:center;

	font-size:2.5em;
	line-height: 1em;
	text-align: center;
	cursor:pointer;
}
</style>
<?php
if($CRON=="(L"){$cmode="#00F";}
if($CRON=="(A"){$cmode="#F0F";}
if($CRON=="(B"){$cmode="#F00";}
?>
<div class="button" onClick="document.location='?';">today</div>
<div class="button" onClick="document.location='?days';">last 30days</div>
<div class="button" onClick="document.location='?days&days90';">last 90days</div>
<div class="button" onclick="if(confirm('line mode?'))document.location='?cmd=POP00'" style="background:<?php echo $cmode?>;">USB</div>
<div class="button" onclick="if(confirm('default mode?'))document.location='?cmd=POP01'" style="background:<?php echo $cmode?>;">SUB</div>
<div class="button" onclick="if(confirm('battery mode?'))document.location='?cmd=POP02'" style="background:<?php echo $cmode?>;">SBU</div>

<?php
$regName = [
  'grid_voltage',           // 202.1        => Voltaj re?ea de intrare (AC Input Voltage)
  'grid_frequency',         // 49.9         => Frecven?a re?ea (AC Input Frequency)
  'ac_output_voltage',      // 202.1        => Voltaj ie?ire invertor (AC Output Voltage)
  'ac_output_frequency',    // 49.9         => Frecven?a ie?ire invertor
  'ac_output_apparent_power', // 0541      => Putere aparenta livrata (VA)
  'ac_output_active_power',   // 0541      => Putere activa (W)
  'load_percentage',        // 005          => % încarcare (load) raportat la puterea maxima
  'bus_voltage',            // 271          => Tensiune magistrala interna (DC bus)
  'battery_voltage',        // 00.10        => Tensiune baterie
  'battery_charging_current', // 000        => Curent de încarcare
  'battery_capacity',       // 000          => Capacitate baterie în %
  'inverter_heat_sink_temp', // 0025        => Temperatura radiator invertor (°C)
  'pv_input_current',       // 00.1         => Curent de la panouri (A)
  'pv_input_voltage',       // 000.0        => Tensiune panouri (V)
  'battery_voltage_scc',    // 00.00        => Tensiune baterie la controllerul solar (SCC)
  'battery_discharge_current', // 00000     => Curent descarcare baterie
  'device_status',          // 00010000     => Status invertor (biti)
  'reserved_1',             // 00
  'reserved_2',             // 00
  'battery_voltage_offset', // 00004        => Ajustare tensiune baterie
  'eeprom_version',         // 010          => Versiune EEPROM / firmware];
];

$date=date("Y-m-d");
//$date="2025-09-19";

if(isset($_GET["days"])){
	$r=$conn->query("
	SELECT 
		SUM(total) AS total, 
		SUM(totalT) AS totalT, 
		MAX(total) AS max, 
		SUM(PVw) AS PVw, 
		SUM(PVloss) AS PVloss
	FROM (
		SELECT * 
		FROM inverter 
		WHERE type='day'
		ORDER BY dta DESC
		LIMIT ".(isset($_GET["days90"])?90:30)."
	) AS last30
	");
}else{
	if(!isset($_GET["hour"])){
		$r=$conn->query("SELECT SUM(teslaV*teslaA)/60 AS totalT, SUM(reg5)/60 AS total, MAX(reg4) AS max, SUM(reg12*reg13)/60 AS PVw, SUM(IF(reg12*reg13-reg5>0,reg12*reg13-reg5,0)/60) AS PVloss FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id");
	}else{
		$hour=explode(",",$_GET["hour"].",");
		if(intval($hour[1])<=0)$hour[1]=1;
		$r=$conn->query("SELECT SUM(teslaV*teslaA)/60 AS totalT, SUM(reg5)/60 AS total, MAX(reg4) AS max, SUM(reg12*reg13)/60 AS PVw, SUM(IF(reg12*reg13-reg5>0,reg12*reg13-reg5,0)/60) AS PVloss FROM inverter WHERE dta >= CURDATE() + INTERVAL ".intval($hour[0])." HOUR AND dta <  CURDATE() + INTERVAL ".(intval($hour[0])+intval($hour[1]))." HOUR ORDER BY id");
	}
}
$row=$r->fetch_assoc();
$canvasHeight=512;
$canvasWidth=1440;
$k=$canvasHeight/min(max($row["max"],1),isset($_GET["days"])?70000:7000);?>

<span style="font-size: 2em;">
ALL: <b><?php echo round($row["total"]/1000,2);?>KW</b>
TESLA: <b><?php echo round($row["totalT"]/1000,2);?>KW</b>
PV: <b><?php echo round($row["PVw"]/1000,2);?>KW</b>
PV LOST: <b><?php echo round($row["PVloss"]/1000,2);?>KW</b>
</span>

<br>
<canvas id="canvas" width="<?php echo $canvasWidth; ?>" height="<?php echo $canvasHeight;?>" style="width:100%;height:<?php echo $canvasHeight;?>px"></canvas>
<br>
<script>
const canvas = document.getElementById("canvas");
if (canvas.getContext) {
	const ctx = canvas.getContext("2d");
	ctx.fillStyle = "#000";
	ctx.fillRect(0, 0, canvas.width, canvas.height);

	ctx.beginPath();
	step=<?php echo isset($_GET["days"])?10:1?>;
	for(i=0;i<100;i+=2*step){
		ctx.moveTo(0,canvas.height-i*1000*<?php echo $k?>);
		ctx.lineTo(canvas.width,canvas.height-i*1000*<?php echo $k?>);
		ctx.lineTo(canvas.width,canvas.height-(i+step)*1000*<?php echo $k?>);
		ctx.lineTo(0,canvas.height-(i+step)*1000*<?php echo $k?>);
	}
	ctx.lineWidth=1;
    ctx.strokeStyle="#080";
    ctx.stroke();

	ctx.font = "15px Arial, Helvetica, sans-serif";
	ctx.fillStyle="#0F0";
	ctx.beginPath();
	K=<?php echo isset($_GET["days"])?24/30:1;?>;
	for(i=0;i<31;i+=2){
		ctx.moveTo(60*i*K,canvas.height);
		ctx.lineTo(60*i*K,0);
		ctx.lineTo(60*(i+1)*K,0);
		ctx.lineTo(60*(i+1)*K,canvas.height);
		ctx.fillText(i+1, 60*(i+1)*K+1, canvas.height-8);
		ctx.fillText(i, 60*i*K+1, canvas.height-8);
	}
	ctx.lineWidth=1;
    ctx.strokeStyle="#080";
    ctx.stroke();

	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gpower&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#F00");});
	var date = new Date();
    var curDate = null;
    do { curDate = new Date(); }
    while(curDate-date < 1000);
	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gpv&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#FF0",1);});
	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gbat&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#0F0",1);});
	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gv&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#88F",1);});
	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gvv&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#33F",1);});
	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gta&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#FFF",1);});
	loadAJAX('graph.php?<?php if(isset($_GET["days"]))echo "days&";?><?php if(isset($_GET["days90"]))echo "days90&";?>gtv&canvasHeight=<?php echo $canvasHeight;?>&k=<?php echo $k;?>', function (data) {drawGraph(data, "#E00",1);});

	function loadAJAX(url, callback){
		var xhr = new XMLHttpRequest();
		xhr.onloadend = function(e){
			callback(xhr);
		};
		xhr.open('GET', url);
		xhr.send();
	}
	
	function drawGraph(data,color,lw) {
		ctx.beginPath();
		ctx.moveTo(0,1440);

		graph=data.response.split(";");
		for (i = 0; i < graph.length; i++) {
    		graph[i] = graph[i].split(",");
			if(color!="#FFF"&&color!="#E00"){
				ctx.lineTo(graph[i][0],(i!=0&&graph[i][1]==<?php echo $canvasHeight?>)?graph[i-1][1]:graph[i][1]);
			}else{
				ctx.lineTo(graph[i][0],graph[i][1]);
			}
		}

		ctx.lineWidth=lw;
		ctx.strokeStyle=color;
		ctx.stroke();
	}

}
</script>

<span style="font-size: 2em">
<?php
$r=$conn->query("SELECT * FROM inverter ORDER BY -id LIMIT 0,1");
while($row = $r->fetch_assoc()) {
	echo $row["dta"];
	for($i=0;$i<20;$i++){
		echo "<br>".$regName[$i].": <b>".$row["reg".$i]."</b>";
	}
}?>
</span>

<?php 
$conn->close();
?>
