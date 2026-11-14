<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$gsm = $_POST['gsm'] ?? $_GET['gsm'] ?? '';
$gsm = preg_replace('/[^0-9]/', '', $gsm);

if (strlen($gsm) < 10 || strlen($gsm) > 11) {
    echo json_encode(["success" => false, "message" => "Geçersiz GSM. 10-11 haneli olmalı."]);
    exit;
}

$ch = curl_init("https://bedavasorgupanel.fit/api/req/tcgsm.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(["gsm" => $gsm]),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "Accept: application/json, text/javascript, */*; q=0.01",
        "X-Requested-With: XMLHttpRequest",
        "Referer: https://bedavasorgupanel.fit/",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ],
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => ""
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(["success" => false, "message" => "cURL: $err"]);
    exit;
}
if ($httpCode !== 200) {
    echo json_encode(["success" => false, "message" => "HTTP $httpCode"]);
    exit;
}

// Dış API JSON dönüyor, doğrudan geçir
$data = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(["success" => false, "message" => "Geçersiz JSON", "raw" => substr($response, 0, 300)]);
}