<?php
$lock = fopen('/tmp/tesla.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    exit; // deja rulează altul
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Bucharest');

//QPIGS:43447
//QPIWS:55989
//QPI:44223
//QPIRI:21753
//QMOD:49482 (L..
//POP00 (linemode USB): 18626
//POP02 27090
//POP02 3042
$cmdcrc=[
	"QPIGS"=>43447,
	"QPIWS"=>55988,
	"QPI"=>44222,
	"QPIRI"=>21753,
	"QMOD"=>49481,
	"POP00"=>18626,
	"POP01"=>27090,
	"POP02"=>3042
];

if(!isset($_GET["cmd"])){
	//exit(); 
}
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
    echo "Nu pot deschide portul serial!";
}else{
	stream_set_timeout($fp, 2);
	stream_set_blocking($fp, false);

	$cmd="QPIGS";
	$crc = $cmdcrc[$cmd];
	if(isset($_GET["cmd"])){
		$cmd=$_GET["cmd"];
		$crc=$cmdcrc[$cmd];
		$request = $cmd . chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF) . "\r";

	    for($i=0;$i<10;$i++){
			fwrite($fp, $request);
			fflush($fp);
			usleep(200000);
			$response = '';
			while (!feof($fp)) {
				$char = fread($fp, 1);
				if ($char === false || $char === '') break;
				$response .= $char;
				if ($char === "\n") break;
			}
			usleep(500000);
			if(in_array(substr($response,0,2),["(L","(B","(A"])||substr($response,0,4)=="(ACK"){
				$CRON=substr($response,0,2);
				return;
			}
	    }
		fclose($fp);
		return;
	}

	$cmd = $cmd . chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF) . "\r";
	echo $crc.":".$cmd;
	fwrite($fp, $cmd);
	fflush($fp);
	usleep(200000);

	$response = '';
	$start = microtime(true);
	$timeout = 2.0; // 2 secunde timeout pentru citire

	while (microtime(true) - $start < $timeout) {
		$chunk = fread($fp, 16);
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
	echo $response;
	if(isset($_GET["cmd"])){
		exit;
	}
	$inverterDATA = $response;
}

//save to DB
$servername = "localhost";
$username = "***";
$password = "***";
$dbname = "shpe";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}


// SUMDAY optimizat, index-friendly și cron-safe
// calculează ultima zi completă pentru tipul 'day'
$conn->query("START TRANSACTION");
try {
    //  Verificăm dacă deja există un rând day pentru ziua precedentă
    $check = $conn->query("
        SELECT 1 
        FROM inverter 
        WHERE type='day' 
          AND dta >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
          AND dta < CURDATE()
        LIMIT 1
    ");

    if($check->num_rows == 0) {
        //  Facem SUM/MAX/PVloss direct în MySQL pentru ziua precedentă
        $sumQuery = "
            SELECT 
                SUM(teslaV*teslaA)/60 AS totalT,
                SUM(reg5)/60 AS total, 
                MAX(reg4) AS max, 
                SUM(reg12*reg13)/60 AS PVw, 
                SUM(IF(reg12*reg13-reg5>0,(reg12*reg13-reg5)/60,0)) AS PVloss
            FROM inverter
            WHERE type IS NULL
              AND dta >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              AND dta < CURDATE()
        ";
        $r = $conn->query($sumQuery);
        $row = $r->fetch_assoc();

        //  Inserăm rândul day
        $conn->query("
            INSERT INTO inverter (totalT, total, max, PVw, PVloss, type, dta)
            VALUES (
                ".floatval($row["totalT"]).",
                ".floatval($row["total"]).",
                ".floatval($row["max"]).",
                ".floatval($row["PVw"]).",
                ".floatval($row["PVloss"]).",
                'day',
                DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            )
        ");

        //  Ștergem rândurile raw pentru ziua precedentă
        $conn->query("
            DELETE FROM inverter
            WHERE type IS NULL
              AND dta >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              AND dta < CURDATE()
        ");
    }

    $conn->query("COMMIT");

} catch(Exception $e) {
    $conn->query("ROLLBACK");
    throw $e;
}

$CVdb=$CAdb=0;
include("tesla.php");

if(!empty($inverterDATA)){
	$inverterDATA=explode(" ",substr($inverterDATA,1));
	$pre="";$sqlA=$sqlB="";
	for($i=0;$i<sizeof($inverterDATA);$i++){
		$sqlA.=$pre." reg".$i;
		$sqlB.=$pre."'".floatval($inverterDATA[$i])."'";
		$pre=",";
	}
	$sql="INSERT INTO inverter ($sqlA,teslaV,teslaA) VALUES ($sqlB,$CVdb,$CAdb)";
	if($inverterDATA[5]<7000){
		$conn->query($sql);
	}
}

echo "end";

$conn->close();
