<?php
// sulaleprime.php - primechecker.store Sülale Sorgu Proxy
// GET: ?tc=11111111119
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$tc = $_GET['tc'] ?? $_POST['tc'] ?? '';
if (!preg_match('/^[0-9]{11}$/', $tc)) {
    echo json_encode(["success" => false, "message" => "Geçersiz TCKN."]);
    exit;
}

$cookieFile = sys_get_temp_dir() . "/pc_" . md5($tc . session_id()) . ".txt";

// 1. Ana sayfa
$ch = curl_init("https://primechecker.store/sorgu/sulale-sorgu");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ],
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => ""
]);
$html = curl_exec($ch);
curl_close($ch);

// 2. Token çıkar
$csrfToken = null;
$nonce = null;
if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']/i', $html, $m)) $csrfToken = $m[1];
if (preg_match('/name=["\']nonce["\']\s+value=["\']([a-f0-9]+)["\']/i', $html, $m)) $nonce = $m[1];
if (!$csrfToken && preg_match('/csrfToken\s*=\s*["\']([a-f0-9]{64})["\']/i', $html, $m)) $csrfToken = $m[1];

if (!$csrfToken) {
    echo json_encode([
        "success" => false,
        "message" => "csrf_token bulunamadı",
        "debug" => substr(strip_tags($html), 0, 500)
    ]);
    @unlink($cookieFile);
    exit;
}

// 3. POST
$ch = curl_init("https://primechecker.store/sorgu/run.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        "tc" => $tc,
        "slug" => "sulale-sorgu",
        "csrf_token" => $csrfToken,
        "nonce" => $nonce ?? ""
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => [
        "Accept: */*",
        "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
        "X-Requested-With: XMLHttpRequest",
        "Referer: https://primechecker.store/sorgu/sulale-sorgu",
        "Origin: https://primechecker.store",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => ""
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
@unlink($cookieFile);

if ($err || $httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => $err ? "cURL: $err" : "HTTP $httpCode",
        "raw" => substr($response, 0, 300)
    ]);
    exit;
}

echo $response;