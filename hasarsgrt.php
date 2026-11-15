<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$BASE = "https://www.korusigorta.com.tr";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

$tck = $_GET['tck'] ?? '';
$plaka = $_GET['plaka'] ?? '';
$hasarTarihi = $_GET['hasarTarihi'] ?? date('Y-m-d');
$vkn = $_GET['vkn'] ?? '';
$sasi = $_GET['sasi'] ?? '';

if (!preg_match('/^[0-9]{11}$/', $tck)) {
    echo json_encode(["success" => false, "message" => "Geçersiz TCKN"]);
    exit;
}

$cf = sys_get_temp_dir() . "/koru_" . md5($tck . uniqid()) . ".txt";

// 1. Form sayfasına git, cookie ve CSRF al
$ch = curl_init("$BASE/hasar-ihbar-formu");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cf,
    CURLOPT_COOKIEFILE => $cf,
    CURLOPT_TIMEOUT => 20,
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
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo json_encode(["success" => false, "message" => "Form sayfası: HTTP $code"]);
    exit;
}

// 2. CSRF token çıkar
$csrfToken = null;
if (preg_match('/name="__RequestVerificationToken"\s+type="hidden"\s+value="([^"]+)"/i', $html, $m)) {
    $csrfToken = $m[1];
} elseif (preg_match('/__RequestVerificationToken["\']?\s*[:=]\s*["\']([^"\']+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
}

if (!$csrfToken) {
    echo json_encode([
        "success" => false,
        "message" => "CSRF token bulunamadı",
        "html_length" => strlen($html),
        "debug" => substr(strip_tags($html), 0, 500)
    ]);
    exit;
}

// 3. POST at
$ch = curl_init("$BASE/Ajax/GetPolicyInfoPopUp");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        "__RequestVerificationToken" => $csrfToken,
        "hasarTarihi" => $hasarTarihi,
        "tck" => $tck,
        "vkn" => $vkn,
        "plaka" => strtoupper($plaka),
        "sasi" => $sasi
    ]),
    CURLOPT_COOKIEJAR => $cf,
    CURLOPT_COOKIEFILE => $cf,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => $UA,
    CURLOPT_HTTPHEADER => [
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: tr-TR,tr;q=0.9",
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With: XMLHttpRequest",
        "Referer: $BASE/hasar-ihbar-formu",
        "Origin: $BASE"
    ],
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($cf);

if ($code !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "HTTP $code",
        "raw" => substr($response, 0, 300)
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "request" => ["tck" => $tck, "plaka" => $plaka, "hasarTarihi" => $hasarTarihi],
    "response" => json_decode($response, true) ?? $response
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);