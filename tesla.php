<?php 
if(date("Hi")>=0&&date("Hi")<=2359){
    $output = file_get_contents("http://127.0.0.1:8080/command?timeout=11&cmd=".urlencode("state charge"));
    $outt=implode('}', array_slice(explode('}', $output), 0, -1)) . '}';
    $outt=json_decode($outt,true);

    $CV=$CVdb=$CA=$CAdb=0;
    if(!empty($outt["chargeState"])){
        $CV=$CVdb=$outt["chargeState"]["chargerVoltage"];
        $CA=$CAdb=$outt["chargeState"]["chargerActualCurrent"];    
    }
    file_put_contents(
        '/var/www/html/inv/inv.txt',
        date("c")." CMD=state charge CV:".$CV." CA:".$CA." OUT=" . substr(var_export($output,true),0,100) . "\n",
        FILE_APPEND
    );

    $update = false;
    if ($CV > 190) {$CA++;$update = true;}
    if ($CV < 180 && $CV > 0) {$CA -= 2;$update = true;}
    if ($CA < 5)  $CA = 5;
    if ($CA > 18) $CA = 18;
    if (!$update && date("i") % 5 == 0) {
        if ($CA < 18) {
            $CA++;
        } else {
            $CA--;
        }
        $update = true;
    }

    if($update){
        $output = file_get_contents("http://127.0.0.1:8080/command?timeout=11&cmd=".urlencode("charging-set-amps ".$CA));
        $outt=implode('}', array_slice(explode('}', $output), 0, -1)) . '}';
        $outt=json_decode($outt,true);
        file_put_contents(
            '/var/www/html/inv/inv.txt',
            date("c")." CMD=charging-set-amps $CA OUT=" . substr(var_export($outt,true),0,100) . "\n",
            FILE_APPEND
        );
    }
}
?>
