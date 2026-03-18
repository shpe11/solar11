<?php 

date_default_timezone_set('Europe/Bucharest');

$servername = "***";
$username = "****";
$password = "***";
$dbname = "***";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune e?uata: " . $conn->connect_error);
}

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

$canvasHeight=$_GET["canvasHeight"];
$k=$_GET["k"];

if(isset($_GET["gpower"]))graph(isset($_GET["days"])?"SELECT * FROM inverter WHERE type='day' ORDER BY -dta":"SELECT * FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#F00","total","reg5");

if(isset($_GET["gpv"]))graph(isset($_GET["days"])?"SELECT PVw FROM inverter WHERE type='day' ORDER BY -dta":"SELECT reg12*reg13 AS PVw FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#FF0","PVw","PVw");
 
if(isset($_GET["gbat"]))graph(isset($_GET["days"])?"SELECT POW(reg8,2)-1700 AS REG8,dta FROM inverter WHERE type='day' ORDER BY -dta":"SELECT reg8*1000/52.5 AS REG8 FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#0F0","reg8","REG8");

if(isset($_GET["gv"]))graph(isset($_GET["days"])?"SELECT * FROM inverter WHERE type='day' ORDER BY -dta":"SELECT reg0*10 AS REG0 FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#00F","reg0","REG0");

if(isset($_GET["gvv"]))graph(isset($_GET["days"])?"SELECT * FROM inverter WHERE type='day' ORDER BY -dta":"SELECT reg2*10 AS REG2 FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#00F","reg2","REG2");

if(isset($_GET["gta"]))graph(isset($_GET["days"])?"SELECT * FROM inverter WHERE type='day' ORDER BY -dta":"SELECT teslaA*100 AS TA FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#FFF","totalT","TA");

if(isset($_GET["gtv"]))graph(isset($_GET["days"])?"SELECT * FROM inverter WHERE type='day' ORDER BY -dta":"SELECT teslaV*10 AS TV FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#FCC","totalT","TV");

foreach([0,1,2,3] as $plug){
    if(isset($_GET["gp".$plug]))graph(isset($_GET["days"])?"SELECT * FROM inverter WHERE type='day' ORDER BY -dta":"SELECT plug".$plug." FROM inverter WHERE dta >= CURDATE() AND dta < CURDATE() + INTERVAL 1 DAY ORDER BY id","#00F","plug".$plug,"plug".$plug);
}

function graph($sql,$color,$R1,$R2){
	global $conn,$k,$canvasHeight;
    $r=$conn->query($sql);
    $x=0;
	$canvasWidth=1440;
    while($row = $r->fetch_assoc()) {
      $x+=$canvasWidth/1440;
      if(isset($_GET["days"])){
        $x+=47*$canvasWidth/1440*(isset($_GET["days90"])?0.333:1);
        echo round($x).",".round($canvasHeight-intval($row[$R1])*$k).",".$row["dta"].";";
	  }else{
        echo round($x).",".round($canvasHeight-intval($row[$R2])*$k).";";
      }
    }
}
$conn->close();
?>
