<?php
// aile_sorgu.php - Aile ve Yakınlık Sorgulama API İsteği

function aileSorgula($tckn) {
    // TCKN 11 haneli mi ve rakam mı kontrol et
    if (!preg_match('/^[0-9]{11}$/', $tckn)) {
        return ["hata" => "Geçersiz TCKN formatı. 11 haneli rakam olmalıdır."];
    }

    $url = "https://bedavasorgupanel.fit/api/req/aile.php?tckn=" . urlencode($tckn);

    $headers = [
        "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: tr-TR",
        "Accept-Encoding: gzip, deflate, br, zstd",
        "X-Requested-With: XMLHttpRequest",
        "Referer: https://bedavasorgupanel.fit/aile.php",
        "Sec-Fetch-Dest: empty",
        "Sec-Fetch-Mode: cors",
        "Sec-Fetch-Site: same-origin"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false, // Test ortamı için false, canlıda true yapın
        CURLOPT_ENCODING => "" // Gzip, deflate, br otomatik çözümleme
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ["hata" => "cURL Hatası: " . $error];
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

// Web'den gelen istek kontrolü
header('Content-Type: application/json; charset=utf-8');

$tckn = $_GET['tckn'] ?? '';

if (empty($tckn)) {
    echo json_encode(["hata" => "TCKN parametresi eksik. Örnek: ?tckn=11111111111"]);
    exit;
}

$sonuc = aileSorgula($tckn);
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);