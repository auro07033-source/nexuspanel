<?php
// ucus.php - DHMİ canlı uçuş bilgisi
// Kullanım:
//   ?airport=2&da=DD&i=D    → İstanbul Sabiha Gökçen, Gidiş, İç Hat
//   ?airport=1&da=DA&i=I    → İstanbul Havalimanı, Geliş, Dış Hat

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$BASE_PAGE = "https://www.dhmi.gov.tr/Sayfalar/TumUcuslar.aspx";
$BASE_API = "https://flightwebsvc.dhmi.gov.tr";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

$airport = $_GET['airport'] ?? '2';
$da = strtoupper($_GET['da'] ?? 'DD');  // DA = Geliş, DD = Gidiş
$i = strtoupper($_GET['i'] ?? 'D');     // D = İç Hat, I = Dış Hat

// Doğrulama
if (!in_array($da, ['DA', 'DD'])) {
    echo json_encode(["success" => false, "message" => "da: DA veya DD olmalı"]);
    exit;
}
if (!in_array($i, ['D', 'I'])) {
    echo json_encode(["success" => false, "message" => "i: D veya I olmalı"]);
    exit;
}

$cf = sys_get_temp_dir() . "/dhmi_" . md5(uniqid()) . ".txt";

// 1. Ana sayfaya git (KToken ve cookie için)
$ch = curl_init($BASE_PAGE);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cf,
    CURLOPT_COOKIEFILE => $cf,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => $UA,
    CURLOPT_HTTPHEADER => [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: tr-TR,tr;q=0.9",
        "Upgrade-Insecure-Requests: 1"
    ],
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    @unlink($cf);
    echo json_encode(["success" => false, "message" => "cURL: $err"]);
    exit;
}

if ($httpCode !== 200) {
    @unlink($cf);
    echo json_encode([
        "success" => false,
        "message" => "Ana sayfa HTTP $httpCode",
        "tried_url" => $BASE_PAGE
    ]);
    exit;
}

// 2. KToken çıkar
$kToken = null;

// Pattern: <input ... name="...$Ktoken" ... value="..." />
$patterns = [
    '/name="[^"]*\$Ktoken"\s+id="Ktoken"\s+value="([^"]+)"/i',
    '/id="Ktoken"\s+name="[^"]*\$Ktoken"\s+value="([^"]+)"/i',
    '/name="[^"]*\$Ktoken"[^>]*value="([^"]+)"/i',
    '/Ktoken["\']?\s*[:=]\s*["\']([^"\']+)["\']/i',
];

foreach ($patterns as $pattern) {
    if (preg_match($pattern, $html, $m)) {
        $kToken = $m[1];
        break;
    }
}

if (!$kToken) {
    @unlink($cf);
    echo json_encode([
        "success" => false,
        "message" => "KToken bulunamadı",
        "html_length" => strlen($html),
        "debug" => "İlk 300: " . substr(strip_tags($html), 0, 300)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 3. API'ye istek at
$apiUrl = "$BASE_API/api/Flights/$airport/$da/$i";

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cf,
    CURLOPT_COOKIEFILE => $cf,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => $UA,
    CURLOPT_HTTPHEADER => [
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: tr-TR,tr;q=0.9",
        "KToken: $kToken",
        "X-Requested-With: XMLHttpRequest",
        "Referer: $BASE_PAGE",
        "Origin: https://www.dhmi.gov.tr"
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
@unlink($cf);

if ($err) {
    echo json_encode(["success" => false, "message" => "cURL: $err"]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "API HTTP $httpCode",
        "url" => $apiUrl,
        "raw" => substr($response, 0, 500)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "success" => false,
        "message" => "Geçersiz JSON",
        "raw" => substr($response, 0, 500)
    ]);
    exit;
}

// Uçuşları Türkçe alanlarla map'le
$flights = [];
foreach ((array)$data as $f) {
    $flights[] = [
        "ucus_no"     => $f['Number'] ?? '',
        "tarih"       => $f['Date'] ?? '',
        "havayolu"    => $f['Airline'] ?? '',
        "yer"         => $f['SrcDst'] ?? '',
        "planlanan"   => $f['Planned'] ?? '',
        "tahmini"     => $f['Estimated'] ?? '',
        "bagaj_alim"  => $f['Gate'] ?? '',
        "durum"       => $f['Status'] ?? '',
        "renk"        => $f['ColorCode'] ?? '',
        "aktif"       => $f['Active'] ?? 0
    ];
}

echo json_encode([
    "success" => true,
    "airport" => $airport,
    "da" => $da,       // DA=Geliş, DD=Gidiş
    "i" => $i,         // D=İç Hat, I=Dış Hat
    "count" => count($flights),
    "flights" => $flights
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);