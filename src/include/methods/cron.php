<?php
global $_FN;

require_once ("{$_FN['src_finis']}/include/classes/FN_LoopManager.php");

$lines = array();


function PrintTime()
{
    global $_FN;
    // Simulate some processing work
    echo "\n esecuzione PrintTime ";
    $curtime =  date("Y-m-d H:i:s");
    file_put_contents("{$_FN['src_application']}/misc/curdate.log.txt", "\n5 minuti $curtime", FILE_APPEND);
    echo "\n";
    @ob_end_flush(); // Flush (send) the output buffer
    @flush(); // Force it to send
}

function PrintTime2()
{
    global $_FN;
    // Simulate some processing work
    echo "\n esecuzione PrintTime 2";
    $curtime =  date("Y-m-d H:i:s");
    file_put_contents("{$_FN['src_application']}/misc/curdate.log.txt", "\n2 minuti   ----- $curtime", FILE_APPEND);
    echo "\n";
    @ob_end_flush(); // Flush (send) the output buffer
    @flush(); // Force it to send
}
function PrintTime3()
{
    global $_FN;
    // Simulate some processing work
    echo "\n esecuzione PrintTime 2";
    $curtime =  date("Y-m-d H:i:s");
    file_put_contents("{$_FN['src_application']}/misc/curdate.log.txt", "\n*-*-* *:*/30:05   ----- $curtime", FILE_APPEND);
    echo "\n";
    @ob_end_flush(); // Flush (send) the output buffer
    @flush(); // Force it to send
}


$loopManager = new FN_LoopManager("PrintTime", "*-*-* *:*/5:*",true); // every 5 minutes
$loopManager->run();    
$loopManager2 = new FN_LoopManager("PrintTime2", "*-*-* *:*/2:*",true); // Execute 2 minutes 
$loopManager2->run();    
$loopManager3 = new FN_LoopManager("PrintTime3", "*-*-* *:*/30:05",true); // every 30 seconds
$loopManager3->run();    
/*
$lines[] = array("time"=>"*-*-* *:*:*","url"=>"{$_FN['siteurl']}?mod=");
$lines[] = array("time"=>"*-*-* *:*:10","url"=>"https://devices.techmakers.it/?mod=login");
$lines[] = array("time"=>"*-*-* *:*:20","url"=>"https://devices.techmakers.it/?mod=login");



foreach ($lines as $line)
{
    $loopManager->run();    
}

*/