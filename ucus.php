<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$BASE_API = "https://flightwebsvc.dhmi.gov.tr";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

$airport = $_GET['airport'] ?? '2';
$da = strtoupper($_GET['da'] ?? 'DD');
$i = strtoupper($_GET['i'] ?? 'D');

if (!in_array($da, ['DA', 'DD'])) {
    echo json_encode(["success" => false, "message" => "da: DA veya DD"]);
    exit;
}
if (!in_array($i, ['D', 'I'])) {
    echo json_encode(["success" => false, "message" => "i: D veya I"]);
    exit;
}

// 1. Ana sayfaya git, cookie + KToken
$cf = sys_get_temp_dir() . "/dhmi_" . md5(uniqid()) . ".txt";
$ch = curl_init("https://www.dhmi.gov.tr/Sayfalar/TumUcuslar.aspx");
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
        "Accept: text/html,application/xhtml+xml",
        "Accept-Language: tr-TR,tr;q=0.9",
        "Upgrade-Insecure-Requests: 1"
    ],
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    @unlink($cf);
    echo json_encode([
        "success" => false,
        "message" => "DHMİ ana sayfa HTTP $httpCode",
        "debug" => "Sayfa çökmüş veya değişmiş olabilir"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2. KToken çıkar (birden fazla pattern)
$kToken = null;
$patterns = [
    '/name="[^"]*\$Ktoken"\s+[^>]*value="([^"]+)"/i',
    '/id="Ktoken"[^>]*value="([^"]+)"/i',
    '/Ktoken["\']?\s*[:=]\s*["\']([^"\']+)["\']/i',
    '/"Ktoken"\s*:\s*"([^"]+)"/i',
    '/Ktoken["\']\s*value=["\']([^"\']+)["\']/i',
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
        "message" => "KToken bulunamadı - sayfa değişmiş olabilir",
        "html_length" => strlen($html),
        "preview" => substr(strip_tags($html), 0, 300)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 3. API çağrısı
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
        "Referer: https://www.dhmi.gov.tr/Sayfalar/TumUcuslar.aspx",
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
if (!is_array($data)) {
    echo json_encode([
        "success" => false,
        "message" => "Geçersiz JSON",
        "raw" => substr($response, 0, 500)
    ]);
    exit;
}

$flights = [];
foreach ($data as $f) {
    $flights[] = [
        "ucus_no" => $f['Number'] ?? '',
        "tarih" => $f['Date'] ?? '',
        "havayolu" => $f['Airline'] ?? '',
        "yer" => $f['SrcDst'] ?? '',
        "planlanan" => $f['Planned'] ?? '',
        "tahmini" => $f['Estimated'] ?? '',
        "bagaj_alim" => $f['Gate'] ?? '',
        "durum" => $f['Status'] ?? '',
        "renk" => $f['ColorCode'] ?? '',
        "aktif" => $f['Active'] ?? 0
    ];
}

echo json_encode([
    "success" => true,
    "airport" => $airport,
    "da" => $da,
    "i" => $i,
    "count" => count($flights),
    "flights" => $flights
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);