<?php
date_default_timezone_set('Europe/Helsinki');
$statsFile=__DIR__.'/stats.json';
$fp=fopen($statsFile,'c+');
if($fp){
    flock($fp,LOCK_EX);
    $size=filesize($statsFile);
    $stats=[];
    if($size>0){
        rewind($fp);
        $data=json_decode(fread($fp,$size),true);
        if(is_array($data))$stats=$data;
    }
    $today=date('Y-m-d');
    if(!isset($stats[$today]))$stats[$today]=0;
    $stats[$today]++;
    ftruncate($fp,0);
    rewind($fp);
    fwrite($fp,json_encode($stats));
    fflush($fp);
    flock($fp,LOCK_UN);
    fclose($fp);
}
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=');
