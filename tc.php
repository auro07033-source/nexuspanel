<?php
// tc_sorgu.php - TCKN ile Sorgulama (Aile, Adres, GSM)

function tcSorgula($tckn) {
    // TCKN 11 haneli mi ve rakam mı kontrol et
    if (!preg_match('/^[0-9]{11}$/', $tckn)) {
        return ["hata" => "Geçersiz TCKN formatı. 11 haneli rakam olmalıdır."];
    }

    $url = "https://bedavasorgupanel.fit/api/req/tc.php?tckn=" . urlencode($tckn);

    $headers = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: tr-TR,tr;q=0.9",
        "Accept-Encoding: gzip, deflate, br",
        "X-Requested-With: XMLHttpRequest",
        "Referer: https://bedavasorgupanel.fit/",
        "Sec-Fetch-Dest: empty",
        "Sec-Fetch-Mode: cors",
        "Sec-Fetch-Site: same-origin"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false, 
        CURLOPT_ENCODING => ""
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ["hata" => "cURL Hatası: " . $error];
    }

    // 502 Bad Gateway durumunu özel olarak yakala
    if ($httpCode === 502) {
        return ["hata" => "Sunucu aşırı yüklü (502 Bad Gateway). Lütfen 60 saniye sonra tekrar deneyin."];
    }

    if ($httpCode !== 200) {
        return ["hata" => "HTTP Hatası: " . $httpCode];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ["hata" => "JSON Çözümleme Hatası: " . json_last_error_msg()];
    }

    return $data;
}

header('Content-Type: application/json; charset=utf-8');

$tckn = $_GET['tckn'] ?? '';

if (empty($tckn)) {
    echo json_encode(["hata" => "TCKN parametresi eksik. Örnek: ?tckn=11111111111"]);
    exit;
}

$sonuc = tcSorgula($tckn);
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);