<?php
// adsoyad_sorgu.php - Ad Soyad ile TCKN ve Aile Bilgisi Sorgulama

function adSoyadSorgula($ad, $soyad, $il = "", $ilce = "") {
    $url = "https://bedavasorgupanel.fit/api/req/adsoyad.php";

    // Gönderilecek veriler (URL Encoded Form Data)
    $postData = http_build_query([
        "ad" => $ad,
        "soyad" => $soyad,
        "il" => $il,
        "ilce" => $ilce
    ]);

    $headers = [
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: tr-TR",
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With: XMLHttpRequest",
        "Alt-Used: bedavasorgupanel.fit",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
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

    if ($httpCode !== 200) {
        return ["hata" => "HTTP Hatası: " . $httpCode];
    }

    // JSON Çözümle
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ["hata" => "JSON Çözümleme Hatası: " . json_last_error_msg()];
    }

    return $data;
}

// Web'den gelen parametreler
header('Content-Type: application/json; charset=utf-8');

$ad = $_GET['ad'] ?? '';
$soyad = $_GET['soyad'] ?? '';
$il = $_GET['il'] ?? '';
$ilce = $_GET['ilce'] ?? '';

if (empty($ad) || empty($soyad)) {
    echo json_encode(["hata" => "Ad ve Soyad parametreleri zorunludur. Örnek: ?ad=Burhan&soyad=Yıldırım&il=van"]);
    exit;
}

$sonuc = adSoyadSorgula($ad, $soyad, $il, $ilce);
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);