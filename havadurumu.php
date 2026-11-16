<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// config.php KALDIRILDI (gerekmiyor)

$il = trim($_REQUEST['il'] ?? '');
if ($il === '') {
    echo json_encode(["error" => "il parametresi gerekli"]);
    exit;
}

function http_get($url, $timeout = 15) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'HavaBot/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    return json_decode($body, true);
}

// 1. Geocoding
$geo = http_get('https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($il) . '&count=1&language=tr&format=json');
if (!$geo || empty($geo['results'])) {
    echo json_encode(["error" => "'$il' bulunamadı"], JSON_UNESCAPED_UNICODE);
    exit;
}

$g = $geo['results'][0];

// 2. Hava durumu
$url = "https://api.open-meteo.com/v1/forecast" .
    "?latitude={$g['latitude']}&longitude={$g['longitude']}" .
    "&current=temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code,apparent_temperature,pressure_msl" .
    "&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max,sunrise,sunset,uv_index_max" .
    "&hourly=temperature_2m,precipitation_probability,weather_code,wind_speed_10m" .
    "&timezone=" . urlencode($g['timezone'] ?? 'Europe/Istanbul');

$hava = http_get($url);

if (!$hava) {
    echo json_encode(["error" => "Hava verisi alınamadı"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sonucu ek bilgilerle zenginleştir
$hava['_il'] = $il;
$hava['_konum'] = [
    'ad' => $g['name'] ?? $il,
    'ulke' => $g['country'] ?? '',
    'enlem' => $g['latitude'],
    'boylam' => $g['longitude'],
];

echo json_encode($hava, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);