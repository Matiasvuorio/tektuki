<?php
// tilastot.php — kävijäraportti counter.php:n stats.jsonista
// Lukee muotoa: 'Y-m-d' => ['views'=>int,'uniques'=>int,'seen'=>array]
// Näyttää: tänään/viikko/kuukausi + %-muutokset, 30 pv trendin ja taulukon.

declare(strict_types=1);
date_default_timezone_set('Europe/Helsinki');

const STATS_FILE  = __DIR__ . '/stats.json';
const DAYS_WINDOW = 30; // trendit & listaus

// --- LUE & NORMALISOI ---
$raw = [];
if (is_file(STATS_FILE)) {
  $json = file_get_contents(STATS_FILE);
  $data = json_decode((string)$json, true);
  if (is_array($data)) $raw = $data;
}
$stats = []; // 'Y-m-d' => ['views'=>int,'uniques'=>int]
foreach ($raw as $day => $row) {
  if (is_array($row)) {
    $views   = (int)($row['views']   ?? 0);
    $uniques = (int)($row['uniques'] ?? 0);
  } else {
    // taaksepäinyhteensopivuus (vanha malli)
    $views = (int)$row; $uniques = 0;
  }
  $dt = DateTimeImmutable::createFromFormat('Y-m-d', (string)$day);
  if ($dt) $stats[$dt->format('Y-m-d')] = ['views'=>$views, 'uniques'=>$uniques];
}
ksort($stats);

// Täydennä puuttuvat päivät nollilla (helpottaa laskentaa)
if (!empty($stats)) {
  $first = new DateTimeImmutable(array_key_first($stats));
  $last  = new DateTimeImmutable(array_key_last($stats));
  for ($d=$first; $d <= $last; $d=$d->modify('+1 day')) {
    $k = $d->format('Y-m-d');
    if (!isset($stats[$k])) $stats[$k] = ['views'=>0,'uniques'=>0];
  }
  ksort($stats);
}

// --- APURI ---
$sumRange = function(array $stats, DateTimeImmutable $start, DateTimeImmutable $end, string $key): int {
  $sum = 0;
  foreach ($stats as $day => $row) {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $day);
    if ($dt && $dt >= $start && $dt <= $end) $sum += (int)$row[$key];
  }
  return $sum;
};
$pctChange = function(int $cur, int $prev): string {
  if ($prev <= 0) return $cur > 0 ? '+100%' : '0%';
  $v = (($cur - $prev) / $prev) * 100;
  return ($v > 0 ? '+' : '') . number_format($v, 1) . '%';
};
function trendBadge(int $cur, int $prev): string {
  $diff = $cur - $prev;
  $pct  = $prev>0 ? (($cur-$prev)/$prev*100) : ($cur>0?100:0);
  $dir  = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
  $txt  = ($pct>0?'+':'').number_format($pct,1).'%';
  $sym  = $dir==='up'?'▲':($dir==='down'?'▼':'■');
  $cls  = $dir==='up'?'trend-up':($dir==='down'?'trend-down':'trend-flat');
  return '<span class="trend '.$cls.'" title="Muutos: '.$txt.'">'.$sym.' '.$txt.'</span>';
}

// --- AIKAVÄLIT ---
$today     = new DateTimeImmutable('today');
$yesterday = $today->modify('-1 day');
$todayStr  = $today->format('Y-m-d');

$weekStart = new DateTimeImmutable('monday this week');
if ((int)$today->format('N') === 1) $weekStart = $today;
$prevWeekEnd   = $weekStart->modify('-1 day');
$prevWeekStart = $weekStart->modify('-7 days');

$monthStart     = $today->modify('first day of this month');
$prevMonthStart = $monthStart->modify('-1 month');
$prevMonthEnd   = $monthStart->modify('-1 day');

// --- SARJAT ---
$viewsSeries = array_column($stats, 'views', 'Y-m-d');
$uniqsSeries = array_column($stats, 'uniques', 'Y-m-d');
// PHP ei tue yllä suoraan: tehdään perinteisesti
$viewsSeries = []; $uniqsSeries = [];
foreach ($stats as $d => $row) { $viewsSeries[$d]=(int)$row['views']; $uniqsSeries[$d]=(int)$row['uniques']; }

// --- AGGREGAATIT ---
$todayViews   = $viewsSeries[$todayStr]   ?? 0;
$todayUniques = $uniqsSeries[$todayStr]   ?? 0;
$yestViews    = $viewsSeries[$yesterday->format('Y-m-d')] ?? 0;
$yestUniques  = $uniqsSeries[$yesterday->format('Y-m-d')] ?? 0;

$weekViews      = $sumRange($stats, $weekStart, $today, 'views');
$weekUniques    = $sumRange($stats, $weekStart, $today, 'uniques');
$prevWeekViews  = $sumRange($stats, $prevWeekStart, $prevWeekEnd, 'views');
$prevWeekUniques= $sumRange($stats, $prevWeekStart, $prevWeekEnd, 'uniques');

$monthViews     = $sumRange($stats, $monthStart, $today, 'views');
$monthUniques   = $sumRange($stats, $monthStart, $today, 'uniques');
$prevMonthViews = $sumRange($stats, $prevMonthStart, $prevMonthEnd, 'views');
$prevMonthUniqs = $sumRange($stats, $prevMonthStart, $prevMonthEnd, 'uniques');

$totalViews   = array_sum(array_column($stats, 'views'));
$totalUniques = array_sum(array_column($stats, 'uniques'));

// 30 pv ikkunat
$start30 = $today->modify('-'.(DAYS_WINDOW-1).' days');
$daily30 = array_filter($stats, function($v,$k) use($start30,$today){
  $dt = new DateTimeImmutable($k);
  return $dt >= $start30 && $dt <= $today;
}, ARRAY_FILTER_USE_BOTH);
ksort($daily30);

// --- HTML ---
?>
<!DOCTYPE html>
<html lang="fi">
<head>
<meta charset="UTF-8">
<title>Kävijätilastot</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { color-scheme: light dark; }
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 24px; line-height: 1.35; }
  header { display:flex; align-items:baseline; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  h1 { margin:0; font-size: clamp(22px, 3vw, 28px); }
  .muted { opacity:.75; }
  .grid { display:grid; gap:14px; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); margin:18px 0 24px; }
  .card { border:1px solid rgba(127,127,127,.25); border-radius:14px; padding:14px 16px; background:rgba(127,127,127,.05); }
  .card h2 { font-size:12px; margin:0 0 8px; opacity:.8; letter-spacing:.2px; text-transform:uppercase; }
  .big { font-size:28px; font-weight:600; margin:2px 0; }
  .pill { display:inline-block; padding:2px 8px; border-radius:999px; background:rgba(127,127,127,.12); font-size:12px; margin-left:8px; }
  .trend { font-size:12px; padding:2px 6px; border-radius:999px; margin-left:8px; vertical-align:middle; }
  .trend-up { background: rgba(0,160,0,.12); color:#008200; }
  .trend-down { background: rgba(200,0,0,.12); color:#aa0000; }
  .trend-flat { background: rgba(120,120,120,.12); color:#5a5a5a; }
  table { width:100%; border-collapse: collapse; }
  th, td { border-bottom:1px solid rgba(127,127,127,.25); padding:8px 6px; text-align:left; }
  th { font-size:12px; letter-spacing:.2px; text-transform:uppercase; opacity:.8; }
  .small { font-size:12px; opacity:.8; }
</style>
</head>
<body>
<header>
  <h1>Kävijätilastot</h1>
  <div class="muted">Päivä: <?=htmlspecialchars($today->format('Y-m-d'))?> (Europe/Helsinki)</div>
</header>

<div class="grid">
  <div class="card">
    <h2>Tänään</h2>
    <div class="big"><?=$todayViews?> <span class="pill">näytöt</span> <?=trendBadge($todayViews,$yestViews)?></div>
    <div><?=$todayUniques?> uniikkia <?=trendBadge($todayUniques,$yestUniques)?></div>
  </div>

  <div class="card">
    <h2>Tällä viikolla</h2>
    <div class="big"><?=$weekViews?> <span class="pill">näytöt</span> <?=trendBadge($weekViews,$prevWeekViews)?></div>
    <div><?=$weekUniques?> uniikkia <?=trendBadge($weekUniques,$prevWeekUniques)?></div>
    <div class="small">Jakso: <?=$weekStart->format('Y-m-d')?> – <?=$today->format('Y-m-d')?></div>
  </div>

  <div class="card">
    <h2>Tässä kuussa</h2>
    <div class="big"><?=$monthViews?> <span class="pill">näytöt</span> <?=trendBadge($monthViews,$prevMonthViews)?></div>
    <div><?=$monthUniques?> uniikkia <?=trendBadge($monthUniques,$prevMonthUniqs)?></div>
    <div class="small">Ed. kk: <?=$prevMonthStart->format('Y-m-d')?> – <?=$prevMonthEnd->format('Y-m-d')?></div>
  </div>

  <div class="card">
    <h2>Kumulatiiviset</h2>
    <div class="big"><?=$totalViews?> <span class="pill">näytöt</span></div>
    <div><?=$totalUniques?> uniikkia</div>
  </div>
</div>

<div class="card">
  <h2>Viimeiset <?=DAYS_WINDOW?> päivää (taulukko)</h2>
  <table>
    <thead><tr><th>Päivä</th><th>Näytöt</th><th>Uniikit</th><th>DoD %</th></tr></thead>
    <tbody>
      <?php
        $prev = null;
        foreach ($daily30 as $d => $row):
          $v = (int)$row['views']; $u = (int)$row['uniques'];
          $dod = ($prev===null) ? '—' : (($prev>0? (($v-$prev)/$prev*100): ($v>0?100:0)));
      ?>
        <tr>
          <td><?=htmlspecialchars($d)?></td>
          <td><?=$v?></td>
          <td><?=$u?></td>
          <td><?= $prev===null ? '—' : ((($v-$prev)>=0?'+':'').number_format($dod,1).'%' ) ?></td>
        </tr>
      <?php $prev=$v; endforeach; if (empty($daily30)): ?>
        <tr><td colspan="4" class="small">Ei dataa vielä.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="small" style="margin-top:14px;">
  Huom: “Tällä viikolla” = kuluvan viikon maanantaista tähän päivään; %-muutos vertaa edelliseen täyteen viikkoon. Kuukausimuutos vertaa edelliseen kuukauteen.
</p>
</body>
</html>
