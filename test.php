<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$url = "https://ulasim.denizli.bel.tr/api/routes?text=&returns=false";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
    CURLOPT_HTTPHEADER => [
        "Accept: */*",
        "Accept-Language: tr-TR,tr;q=0.9",
        "x-requested-by: portal",
        "Origin: https://ulasim.denizli.bel.tr",
        "Referer: https://ulasim.denizli.bel.tr/",
        "Sec-Fetch-Dest: empty",
        "Sec-Fetch-Mode: cors",
        "Sec-Fetch-Site: same-origin",
        "Sec-Ch-Ua: \"Chromium\";v=\"131\", \"Not_A Brand\";v=\"24\"",
        "Sec-Ch-Ua-Mobile: ?0",
        "Sec-Ch-Ua-Platform: \"Windows\"",
        "Priority: u=1, i",
        "DNT: 1"
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo json_encode([
    "success" => $httpCode === 200,
    "http_code" => $httpCode,
    "error" => $err,
    "response_preview" => substr($response, 0, 500)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);