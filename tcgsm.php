<?php
// tcgsm_sorgu.php - TCGSM Sorgulama İsteği

function tcgsmSorgula($tckn) {
    // TCKN 11 haneli mi ve rakam mı kontrol et
    if (!preg_match('/^[0-9]{11}$/', $tckn)) {
        return ["hata" => "Geçersiz TCKN formatı. 11 haneli rakam olmalıdır."];
    }

    // Belirttiğin URL (GET metodu ile)
    // Eğer TCKN'yi URL'ye parametre olarak eklemiyorsa (örnekte yok), form verisi olarak (POST) veya sorgu dizesi olarak (GET) göndermen gerekebilir.
    // Senin verdiğin Network kaydında URL'de parametre yoktu. Bu yüzden POST isteği atıyoruz.
    
    $url = "https://bedavasorgupanel.fit/tcgsm.php";

    $headers = [
        "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: tr-TR",
        "Accept-Encoding: gzip, deflate, br, zstd",
        "Referer: https://bedavasorgupanel.fit/",
        "Sec-Fetch-Dest: document",
        "Sec-Fetch-Mode: navigate",
        "Sec-Fetch-Site: same-origin",
        "Sec-Fetch-User: ?1",
        "Upgrade-Insecure-Requests: 1",
        "Content-Type: application/x-www-form-urlencoded"
    ];

    // TCKN'yi POST verisi olarak gönderiyoruz (genelde bu tür sorgularda form verisi beklenir)
    $postData = http_build_query([
        "tckn" => $tckn // Veya "tc" => $tckn. Siteye göre değişebilir.
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true, // GET ise CURLOPT_HTTPGET => true yapın
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

    // Dönen yanıt HTML formatında (text/html)
    return [
        "durum" => "Başarılı",
        "html_ciktisi" => substr(strip_tags($response), 0, 500) . "..." // HTML etiketlerini temizleyip ilk 500 karakteri göster
    ];
}

header('Content-Type: application/json; charset=utf-8');

$tckn = $_GET['tckn'] ?? '';

if (empty($tckn)) {
    echo json_encode(["hata" => "TCKN parametresi eksik. Örnek: ?tckn=11111111111"]);
    exit;
}

$sonuc = tcgsmSorgula($tckn);
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);