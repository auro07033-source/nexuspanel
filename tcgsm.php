<?php
// tcgsm.php - TC → GSM Sorgu (mayte.pro API Proxy)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parametreyi al (POST veya GET fallback)
$tc = $_POST['tc'] ?? $_GET['tc'] ?? $_POST['tckn'] ?? $_GET['tckn'] ?? '';

// TCKN doğrulama (11 hane, rakam)
if (!preg_match('/^[0-9]{11}$/', $tc)) {
    echo json_encode([
        "ok" => false,
        "message" => "Geçersiz TCKN. 11 haneli rakam olmalıdır."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = "https://mayte.pro/api/query/ana-tcgsm?page=1";

$payload = json_encode(["tc" => $tc], JSON_UNESCAPED_UNICODE);

$headers = [
    "Accept: */*",
    "Accept-Language: tr-TR,tr;q=0.9",
    "Content-Type: application/json",
    "Alt-Used: mayte.pro",
    "Origin: https://mayte.pro",
    "Referer: https://mayte.pro/",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => ""
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode([
        "ok" => false,
        "message" => "cURL hatası: " . $err
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        "ok" => false,
        "message" => "Dış API hatası: HTTP $httpCode",
        "raw" => substr($response, 0, 300)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// JSON doğrulama
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "ok" => false,
        "message" => "Geçersiz JSON yanıtı",
        "raw" => substr($response, 0, 300)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sonucu doğrudan geçir
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);