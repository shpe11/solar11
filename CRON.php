<?php
ini_set('display_errors', 0); 
ini_set('display_startup_errors', 0); 
error_reporting(E_ALL ^ E_WARNING);

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
$servername = "***";
$username = "***";
$password = "***!";
$dbname = "***";
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
                SUM(IF(reg12*reg13-reg5>0,(reg12*reg13-reg5)/60,0)) AS PVloss,
                SUM(COALESCE(plug0, 0))/60 AS plug0,
                SUM(COALESCE(plug1, 0))/60 AS plug1,
                SUM(COALESCE(plug2, 0))/60 AS plug2,
                SUM(COALESCE(plug3, 0))/60 AS plug3,
                SUM(COALESCE(plug4, 0))/60 AS plug4,
                SUM(COALESCE(plug5, 0))/60 AS plug5,
                SUM(COALESCE(plug6, 0))/60 AS plug6,
                SUM(COALESCE(plug7, 0))/60 AS plug7,
                SUM(COALESCE(plug8, 0))/60 AS plug8,
                SUM(COALESCE(plug9, 0))/60 AS plug9
            FROM inverter
            WHERE type IS NULL
              AND dta >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              AND dta < CURDATE()
        ";
        $r = $conn->query($sumQuery);
        $row = $r->fetch_assoc();

        //  Inserăm rândul day
        $conn->query("
            INSERT INTO inverter (totalT, total, max, PVw, PVloss, plug0, plug1, plug2, plug3, plug4, plug5, plug6, plug7, plug8, plug9, type, dta)
            VALUES (
                ".floatval($row["totalT"]).",
                ".floatval($row["total"]).",
                ".floatval($row["max"]).",
                ".floatval($row["PVw"]).",
                ".floatval($row["PVloss"]).",
                ".floatval($row["plug0"]).",
                ".floatval($row["plug1"]).",
                ".floatval($row["plug2"]).",
                ".floatval($row["plug3"]).",
                ".floatval($row["plug4"]).",
                ".floatval($row["plug5"]).",
                ".floatval($row["plug6"]).",
                ".floatval($row["plug7"]).",
                ".floatval($row["plug8"]).",
                ".floatval($row["plug9"]).",
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

$p=[];
foreach([0,1,2,3] as $plug){
    if (date("Hi") == "0000"){
        $cmd="Restart%201";
    }else{
        $cmd="Status%208";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://192.168.100.20$plug/cm?cmnd=".$cmd);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $json = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($json, true);
    $p[$plug] = isset($json['StatusSNS']['ENERGY']['ApparentPower']) ? round($json['StatusSNS']['ENERGY']['ApparentPower'],1) : 0;
}

if(!empty($inverterDATA)){
	$inverterDATA=explode(" ",substr($inverterDATA,1));
	$pre="";$sqlA=$sqlB="";
	for($i=0;$i<sizeof($inverterDATA);$i++){
		$sqlA.=$pre." reg".$i;
		$sqlB.=$pre."'".floatval($inverterDATA[$i])."'";
		$pre=",";
	}
	$sql="INSERT INTO inverter ($sqlA,teslaV,teslaA,plug0,plug1,plug2,plug3) VALUES ($sqlB,$CVdb,$CAdb,$p[0],$p[1],$p[2],$p[3])";
	if($inverterDATA[5]<7000){
		$conn->query($sql);
	}
}

echo "end";

$conn->close();
