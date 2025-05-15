<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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


$servername = "localhost";
$username = "***";
$password = "***";
$dbname = "shpe";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}
date_default_timezone_set('Europe/Bucharest');
echo date("Y-m-d");
$r=$conn->query("SELECT SUM(reg4)/60 AS total FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$row=$r->fetch_assoc();
echo "<br>active power:".round($row["total"]/1000,2)."KW";

$r=$conn->query("SELECT SUM(reg12)/60 AS PVi, SUM(reg13)/60 AS PVv FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$row=$r->fetch_assoc();
echo "<br>PVpower:".round($row["PVi"]*$row["PVv"]/1000,2)."KW";

?>
<canvas id="canvas" width="1440" height="300"></canvas>
<script>
	const canvas = document.getElementById("canvas");
	if (canvas.getContext) {
	const ctx = canvas.getContext("2d");
	ctx.fillStyle = "#000";
	ctx.fillRect(0, 0, canvas.width, canvas.height);


	ctx.beginPath();
	ctx.moveTo(0,300);
<?php
$r=$conn->query("SELECT * FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$x=0;
while($row = $r->fetch_assoc()) {
	$x++;
	?>ctx.lineTo(<?php echo $x;?>,<?php echo 300-intval($row["reg5"]/5);?>);<?php
}
?>
	ctx.lineWidth=1;
	ctx.strokeStyle="#F00";
	ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(0,300);
<?php
$r=$conn->query("SELECT * FROM inverter WHERE LEFT(dta,10)='".date("Y-m-d")."' ORDER BY id");
$x=0;
while($row = $r->fetch_assoc()) {
        $x++;
        ?>ctx.lineTo(<?php echo $x;?>,<?php echo 300-intval($row["reg13"]/5);?>);<?php
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
