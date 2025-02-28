<?php
date_default_timezone_set('Europe/Helsinki');
$f=__DIR__.'/stats.json';
$a=[];
if(file_exists($f)){
 $d=file_get_contents($f);
 $j=json_decode($d,true);
 if(is_array($j))$a=$j;
}
$t=date('Y-m-d');
$today=0;$week=0;$month=0;
$td=new DateTime();$ws=(clone $td)->modify('monday this week');$ms=(clone $td)->modify('first day of this month');
foreach($a as $k=>$v){
 $dt=DateTime::createFromFormat('Y-m-d',$k);
 if($k===$td->format('Y-m-d'))$today=$v;
 if($dt>=$ws&&$dt<=$td)$week+=$v;
 if($dt->format('Y-m')===$td->format('Y-m'))$month+=$v;
}
?>
<!DOCTYPE html>
<html lang="fi">
<head><meta charset="UTF-8"><title>Tilastot</title></head>
<body>
<p>Tänään: <?php echo $today; ?></p>
<p>Tällä viikolla: <?php echo $week; ?></p>
<p>Tässä kuussa: <?php echo $month; ?></p>
<hr>
<table border="1" cellpadding="4">
<tr><th>Päivä</th><th>Latauksia</th></tr>
<?php
ksort($a);
foreach($a as $k=>$v){
 echo'<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
}
?>
</table>
</body>
</html>
