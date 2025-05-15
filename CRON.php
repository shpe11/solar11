<?php
$port="/dev/ttyUSB0";
shell_exec("stty -F $port 2400 cs8 -cstopb -parenb -crtscts");
$fp = fopen($port, "r+");
if (!$fp) {
    die("Nu pot deschide portul serial!\n");
}
stream_set_timeout($fp, 1);
stream_set_blocking($fp, true);

$crc = 43447;
$cmd = "QPIGS" . chr($crc % 256) . chr(intval($crc / 256));

fwrite($fp, $cmd);
fflush($fp);

$response = '';
while (!feof($fp)) {
    $char = fread($fp, 1);
    if ($char === false || $char === '') break;
    $response .= $char;
    if ($char === "\r" || $char === "\n") break;
}
$inverterDATA=$response;
fclose($fp);

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
