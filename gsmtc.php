<?php
// gsmtc_sorgu.php - GSM'den TCKN Sorgulama

function gsmTcSorgula($gsm) {
    // GSM numarası temizle (boşluk, tire vb. kaldır)
    $gsm = preg_replace('/[^0-9]/', '', $gsm);
    
    // 10 haneli (5xx...) veya 11 haneli (05xx...) kontrolü
    if (strlen($gsm) < 10 || strlen($gsm) > 11) {
        return ["hata" => "Geçersiz GSM formatı. 10 veya 11 haneli olmalıdır."];
    }

    $url = "https://bedavasorgupanel.fit/gsmtc.php";

    // Senin kaydında URL'de parametre yoktu. 
    // Ancak sayfa içi form yapısına göre veri POST veya GET ile gönderiliyor olabilir.
    // Aşağıda POST ile gönderiyoruz. Eğer HTML formu GET kullanıyorsa ?gsm=... şeklinde ekleyebilirsin.
    
    $postData = http_build_query([
        "gsm" => $gsm,     // Formdaki input name="gsm" ise
        "telefon" => $gsm  // Formdaki input name="telefon" ise
    ]);

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

    // HTML yanıtını temizle ve metin olarak döndür
    return [
        "durum" => "Başarılı",
        "html_ciktisi" => substr(strip_tags($response), 0, 1000) . "..."
    ];
}

header('Content-Type: application/json; charset=utf-8');

$gsm = $_GET['gsm'] ?? '';

if (empty($gsm)) {
    echo json_encode(["hata" => "GSM parametresi eksik. Örnek: ?gsm=5536653765"]);
    exit;
}

$sonuc = gsmTcSorgula($gsm);
echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);