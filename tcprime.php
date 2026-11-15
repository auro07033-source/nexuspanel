<?php
// tcprime.php - primechecker.store TC Sorgu Proxy
// GET: ?tc=11111111110
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$tc = $_GET['tc'] ?? $_POST['tc'] ?? '';
if (!preg_match('/^[0-9]{11}$/', $tc)) {
    echo json_encode(["success" => false, "message" => "Geçersiz TCKN. 11 haneli olmalı."]);
    exit;
}

$cookieFile = sys_get_temp_dir() . "/pc_" . md5($tc . session_id()) . ".txt";

// 1. Ana sayfaya git, csrf_token ve session cookie al
$ch = curl_init("https://primechecker.store/sorgu/tc-sorgu");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: tr-TR,tr;q=0.9",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ],
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => ""
]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    echo json_encode(["success" => false, "message" => "Ana sayfa yüklenemedi"]);
    exit;
}

// 2. csrf_token ve nonce'u HTML'den çıkar
$csrfToken = null;
$nonce = null;

// csrf_token meta veya hidden input içinde olabilir
if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
} elseif (preg_match('/csrf_token["\']?\s*[:=]\s*["\']([a-f0-9]+)["\']/i', $html, $m)) {
    $csrfToken = $m[1];
}

// nonce aynı şekilde
if (preg_match('/name=["\']nonce["\']\s+value=["\']([a-f0-9]+)["\']/i', $html, $m)) {
    $nonce = $m[1];
} elseif (preg_match('/nonce["\']?\s*[:=]\s*["\']([a-f0-9]+)["\']/i', $html, $m)) {
    $nonce = $m[1];
}

// Alternatif: JS içinde window.csrfToken = "..." şeklinde olabilir
if (!$csrfToken && preg_match('/csrfToken\s*=\s*["\']([a-f0-9]{64})["\']/i', $html, $m)) {
    $csrfToken = $m[1];
}

if (!$csrfToken) {
    echo json_encode([
        "success" => false,
        "message" => "csrf_token bulunamadı. HTML'in ilk 500 karakteri:",
        "debug" => substr(strip_tags($html), 0, 500)
    ]);
    @unlink($cookieFile);
    exit;
}

// 3. run.php'ye POST at
$ch = curl_init("https://primechecker.store/sorgu/run.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        "tc" => $tc,
        "slug" => "tc-sorgu",
        "csrf_token" => $csrfToken,
        "nonce" => $nonce ?? ""
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => [
        "Accept: */*",
        "Accept-Language: tr-TR,tr;q=0.9",
        "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
        "X-Requested-With: XMLHttpRequest",
        "Referer: https://primechecker.store/sorgu/tc-sorgu",
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

if ($err) {
    echo json_encode(["success" => false, "message" => "cURL: " . $err]);
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

// 4. Direkt JSON'u geçir
echo $response;