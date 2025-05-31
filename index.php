<?php 
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Europe/Bucharest');

$servername = "localhost";
$username = "***";
$password = "***";
$dbname = "shpe";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}

if(isset($_GET["crc"])){
	$r=$conn->query("SELECT * FROM inverter LIMIT 0,1");
	$row=$r->fetch_assoc();
	echo "<pre>";var_dump($row);
	exit;
}
?><style>
* { margin:0; padding:0; }
html, body { width:100%; height:100%; }
canvas { display:block; }
.button{
	float:left;
	width:20%;
	background:#999;
	height:100px;
	font-size:3em;
	text-align:center;
padding-top:50px;
}
</style>
<div class="button">&laquo;</div>
<div class="button">day</div>
<div class="button">month</div>
<div class="button">year</div>
<div class="button">&raquo;</div>

<?php
$regName = [
  'grid_voltage',           // 202.1        => Voltaj rețea de intrare (AC Input Voltage)
  'grid_frequency',         // 49.9         => Frecvență rețea (AC Input Frequency)
  'ac_output_voltage',      // 202.1        => Voltaj ieșire invertor (AC Output Voltage)
  'ac_output_frequency',    // 49.9         => Frecvență ieșire invertor
  'ac_output_apparent_power', // 0541      => Putere aparentă livrată (VA)
  'ac_output_active_power',   // 0541      => Putere activă (W)
  'load_percentage',        // 005          => % încărcare (load) raportat la puterea maximă
  'bus_voltage',            // 271          => Tensiune magistrală internă (DC bus)
  'battery_voltage',        // 00.10        => Tensiune baterie
  'battery_charging_current', // 000        => Curent de încărcare
  'battery_capacity',       // 000          => Capacitate baterie în %
  'inverter_heat_sink_temp', // 0025        => Temperatură radiator invertor (°C)
  'pv_input_current',       // 00.1         => Curent de la panouri (A)
  'pv_input_voltage',       // 000.0        => Tensiune panouri (V)
  'battery_voltage_scc',    // 00.00        => Tensiune baterie la controllerul solar (SCC)
  'battery_discharge_current', // 00000     => Curent descărcare baterie
  'device_status',          // 00010000     => Status invertor (biti)
  'reserved_1',             // 00
  'reserved_2',             // 00
  'battery_voltage_offset', // 00004        => Ajustare tensiune baterie
  'eeprom_version',         // 010          => Versiune EEPROM / firmware];
];

echo date("Y-m-d");
$r=$conn->query("SELECT SUM(reg5)/60 AS total, MAX(reg4) AS max, SUM(reg12*reg13)/60 AS PVw, SUM(IF(reg12*reg13-reg5>0,reg12*reg13-reg5,0)/60) AS PVloss FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$row=$r->fetch_assoc();
$canvasHeight=512;
$k=max($canvasHeight/$row["max"],0.01);
echo " active power:<b>".round($row["total"]/1000,2)."KW</b>";
echo " PVpower:<b>".round($row["PVw"]/1000,2)."KW</b>";
echo " PVlost:<b>".round($row["PVloss"]/1000,2)."KW</b>";
?>
<br>
<canvas id="canvas" width="480" height="<?php echo $canvasHeight;?>" style="width:100%;height:<?php echo $canvasHeight;?>px"></canvas>
<br>
<script>
	const canvas = document.getElementById("canvas");
	if (canvas.getContext) {
	const ctx = canvas.getContext("2d");
	ctx.fillStyle = "#000";
	ctx.fillRect(0, 0, canvas.width, canvas.height);

	ctx.beginPath();
	for(i=0;i<10;i+=2){
		ctx.moveTo(0,canvas.height-i*1000*<?php echo $k?>);
		ctx.lineTo(canvas.width,canvas.height-i*1000*<?php echo $k?>);
		ctx.lineTo(canvas.width,canvas.height-(i+1)*1000*<?php echo $k?>);
		ctx.lineTo(0,canvas.height-(i+1)*1000*<?php echo $k?>);
	}
	ctx.lineWidth=1;
        ctx.strokeStyle="#0F0";
        ctx.stroke();

	ctx.beginPath();
	ctx.moveTo(0,1440);
<?php
$r=$conn->query("SELECT * FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$x=0;
while($row = $r->fetch_assoc()) {
	$x+=480/1440;
	?>ctx.lineTo(<?php echo $x;?>,<?php echo $canvasHeight-intval($row["reg5"])*$k;?>);<?php
}
?>
	ctx.lineWidth=1;
	ctx.strokeStyle="#F00";
	ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(0,300);
<?php
$r=$conn->query("SELECT reg12*reg13 AS PVw FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$x=0;
while($row = $r->fetch_assoc()) {
        $x+=480/1440;
        ?>ctx.lineTo(<?php echo $x;?>,<?php echo $canvasHeight-intval($row["PVw"])*$k;?>);<?php
}
?>
        ctx.lineWidth=1;
        ctx.strokeStyle="#FF0";
        ctx.stroke();

  }
</script>
<?php
$r=$conn->query("SELECT * FROM inverter ORDER BY -id LIMIT 0,1");
while($row = $r->fetch_assoc()) {
	echo $row["dta"];
	for($i=0;$i<20;$i++){
	echo "<br>".$regName[$i].":".$row["reg".$i];
	}
}
$conn->close();
