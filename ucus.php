<?php
// dhmi.php - DHMİ canlı uçuş bilgisi proxy
// Kullanım:
//   ?airport=2&type=DA&dir=D    → İstanbul Atatürk Kalkış
//   ?airport=2&type=DA&dir=A    → İstanbul Atatürk Varış
//   ?airport=1&type=IC&dir=D    → İstanbul Havalimanı (IST) Kalkış

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$BASE = "https://flightwebsvc.dhmi.gov.tr";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

$airport = $_GET['airport'] ?? '2';
$type = $_GET['type'] ?? 'DA';
$dir = $_GET['dir'] ?? 'D';

// Geçerli değerler
if (!in_array($dir, ['D', 'A'])) {
    echo json_encode(["success" => false, "message" => "dir D veya A olmalı"]);
    exit;
}

// KToken almak için önce ana sayfaya git
$cookieFile = sys_get_temp_dir() . "/dhmi_" . md5(uniqid()) . ".txt";

$ch = curl_init("https://www.dhmi.gov.tr/Sayfalar/ucus-bilgileri.aspx");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_TIMEOUT => 20,
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
    @unlink($cookieFile);
    echo json_encode(["success" => false, "message" => "Ana sayfa: HTTP $httpCode"]);
    exit;
}

// HTML'den KToken çıkar
$kToken = null;

// Farklı pattern'ler
$patterns = [
    '/["\']KToken["\']\s*[:=]\s*["\']([^"\']+)["\']/i',
    '/KToken\s*:\s*["\']([^"\']+)["\']/i',
    '/headers\s*:\s*\{[^}]*["\']KToken["\']\s*:\s*["\']([^"\']+)["\']/i',
];

foreach ($patterns as $pattern) {
    if (preg_match($pattern, $html, $m)) {
        $kToken = $m[1];
        break;
    }
}

// Bulunamazsa, ana sayfa URL'sinin sonuna bir API çağrısı yaparak da alınabilir
if (!$kToken) {
    // Yedek: DHMİ'nin kendi script'lerini kontrol et
    if (preg_match('/<script[^>]*src="([^"]*ucus[^"]*\.js[^"]*)"/i', $html, $m)) {
        $jsUrl = $m[1];
        if (strpos($jsUrl, "http") !== 0) {
            $jsUrl = "https://www.dhmi.gov.tr" . (strpos($jsUrl, "/") === 0 ? "" : "/") . $jsUrl;
        }
        
        $ch = curl_init($jsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $UA,
        ]);
        $js = curl_exec($ch);
        curl_close($ch);
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $js, $m)) {
                $kToken = $m[1];
                break;
            }
        }
    }
}

if (!$kToken) {
    @unlink($cookieFile);
    echo json_encode([
        "success" => false,
        "message" => "KToken bulunamadı",
        "html_length" => strlen($html),
        "debug" => "İlk 500: " . substr(strip_tags($html), 0, 500)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// API isteği
$url = "$BASE/api/Flights/$airport/$type/$dir";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => $UA,
    CURLOPT_HTTPHEADER => [
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: tr-TR,tr;q=0.9",
        "KToken: $kToken",
        "X-Requested-With: XMLHttpRequest",
        "Referer: https://www.dhmi.gov.tr/Sayfalar/ucus-bilgileri.aspx",
        "Origin: https://www.dhmi.gov.tr"
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
@unlink($cookieFile);

if ($err) {
    echo json_encode(["success" => false, "message" => "cURL: $err"]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "HTTP $httpCode",
        "raw" => substr($response, 0, 300)
    ]);
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

echo json_encode([
    "success" => true,
    "airport" => $airport,
    "type" => $type,
    "direction" => $dir,
    "kToken_used" => substr($kToken, 0, 30) . "...",
    "flight_count" => is_array($data) ? count($data) : 0,
    "flights" => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);