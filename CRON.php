<?php
$port = "/dev/ttyUSB0";

/*
exec("sudo fuser -k $port 2>&1", $output, $return_var);
if ($return_var !== 0) {
	exit;
}
usleep(500000);
*/

shell_exec("stty -F $port 2400 cs8 -cstopb -parenb -crtscts");

$fp = fopen($port, "r+");
if (!$fp) {
    die("Nu pot deschide portul serial!\n");
}
stream_set_blocking($fp, false);
stream_set_timeout($fp, 2);

$crc = 43447;
$cmd = "QPIGS" . chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF) . "\r";

fwrite($fp, $cmd);
fflush($fp);
usleep(200000);

$response = '';
$start = microtime(true);
$timeout = 2.0; // 2 secunde timeout pentru citire

while (microtime(true) - $start < $timeout) {
    $chunk = fread($fp, 64);
    if ($chunk === false) {
        break;
    }
    $response .= $chunk;
    if (strpos($chunk, "\r") !== false || strpos($chunk, "\n") !== false) {
        break;
    }
    usleep(50000);
}
fclose($fp);

$inverterDATA = $response;

//save to DB
$servername = "localhost";
$username = "***";
$password = "***";
$dbname = "shpe";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}

$inverterDATA=explode(" ",substr($inverterDATA,1));
$pre="";$sqlA=$sqlB="";
for($i=0;$i<sizeof($inverterDATA);$i++){
	$sqlA.=$pre." reg".$i;
	$sqlB.=$pre."'".floatval($inverterDATA[$i])."'";
	$pre=",";
}
$sql="INSERT INTO inverter ($sqlA) VALUES ($sqlB)";
$r=$conn->query($sql);
$conn->close();
