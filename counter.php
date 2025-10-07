<?php
// counter.php — yksinkertainen evästeetön kävijälaskuri
// - Laskee päiväkohtaiset näyttökerrat (views) ja uniikit (uniques)

declare(strict_types=1);
date_default_timezone_set('Europe/Helsinki');

// --- asetukset ---
const STATS_FILE   = __DIR__ . '/stats.json';
const DAYS_TO_KEEP = 90; // säilytä viimeiset N päivää
// ------------------

// HEAD-pyyntö tai tyhjä UA → älä laske, mutta palauta pikseli
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD' || $ua === '') {
    servePixel();
    exit;
}

// Perusbottisuodatus (kevyt lista)
$botPattern = '~(bot|crawler|spider|archiver|preview|slurp|facebook|bingpreview|monitor|statuscake|uptime|validator|whatsapp|telegram)~i';
if (preg_match($botPattern, $ua)) {
    servePixel();
    exit;
}
$dnt = $_SERVER['HTTP_DNT'] ?? '';
if ($dnt === '1') {
    // Käyttäjä toivoo, ettei häntä seurata → älä laske
    servePixel();
    exit;
}

// Päivämäärä ja yksinkertainen "uniikki-avain" (IP+UA+päivä)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// *** Lisäys: tunnista kävijän IP reverse proxyjen takaa ***
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    // Cloudflare
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // Yleinen reverse proxy / load balancer
    $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
    $candidate = $parts[0] ?? '';
    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
        $ip = $candidate;
    }
}
// *** Lisäys päättyy ***

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$uniqKey = sha1($ip . '|' . $ua . '|' . $today);

// Lue olemassa oleva stats.json
$stats = []; // muoto: 'YYYY-MM-DD' => ['views'=>int,'uniques'=>int,'seen'=>['hash'=>true,...]]

$fp = @fopen(STATS_FILE, 'c+');
if ($fp) {
    flock($fp, LOCK_EX);

    $size = filesize(STATS_FILE);
    if ($size > 0) {
        rewind($fp);
        $json = fread($fp, $size);
        $data = json_decode($json, true);
        if (is_array($data)) {
            $stats = $data;
        }
    }

    // Siivoa vanhat päivät
    $cutoff = (new DateTimeImmutable('today -' . (DAYS_TO_KEEP - 1) . ' days'))->format('Y-m-d');
    foreach ($stats as $day => $_row) {
        if ($day < $cutoff) unset($stats[$day]);
    }

    // Alusta nykyinen päivä
    if (!isset($stats[$today])) {
        $stats[$today] = ['views' => 0, 'uniques' => 0, 'seen' => []];
    }

    // Lisää näyttökerta
    $stats[$today]['views']++;

    // Päiväuniikki
    if (empty($stats[$today]['seen'][$uniqKey])) {
        $stats[$today]['seen'][$uniqKey] = true;
        $stats[$today]['uniques']++;
    }

    // Kirjoita takaisin
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);

    flock($fp, LOCK_UN);
    fclose($fp);
}

// Palauta 1x1 GIF ja estä välimuisti
servePixel();
exit;

// ---- helper ----
function servePixel(): void {
    // läpinäkyvä 1x1 GIF
    $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=');

    header('Content-Type: image/gif');
    header('Content-Length: ' . strlen($gif));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $gif;
}
