<?php
// doviz.php - Gazete Vatan canlı döviz/altın/borsa bilgisi
// Kullanım: ?type=all | ?type=doviz | ?type=altin | ?type=borsa
// ?filter=SUSD    → tek bir sembol getir

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$URL = "https://www.gazetevatan.com/api/currency/getcurrency";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

$type = strtolower($_GET['type'] ?? 'all');
$filter = strtoupper(trim($_GET['filter'] ?? ''));

$ch = curl_init($URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_USERAGENT => $UA,
    CURLOPT_HTTPHEADER => [
        "Accept: application/json, */*",
        "Accept-Language: tr-TR,tr;q=0.9",
        "Referer: https://www.gazetevatan.com/",
        "Origin: https://www.gazetevatan.com"
    ],
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

$data = json_decode($response, true);
if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "Geçersiz JSON", "raw" => substr($response, 0, 300)]);
    exit;
}

// Türkçe sayı formatını float'a çevir: "6.296,36" → 6296.36
function trToFloat($val) {
    if ($val === null || $val === '') return null;
    $val = str_replace('.', '', $val);     // binlik noktaları kaldır
    $val = str_replace(',', '.', $val);    // ondalık virgülü noktaya çevir
    return is_numeric($val) ? (float)$val : null;
}

// Her öğeyi normalize et
$normalized = [];
foreach ($data as $item) {
    $sym = $item['Symbol'] ?? '';
    
    // Filtre
    if ($filter && $sym !== $filter) continue;
    
    // Tip filtresi
    $tip = strtolower($item['Type'] ?? '');
    if ($type === 'doviz' && !preg_match('/dolar|euro|sterlin|frank|yen|yuan|ruble/i', $tip)) continue;
    if ($type === 'altin' && strpos($tip, 'altın') === false) continue;
    if ($type === 'borsa' && strpos($tip, 'endeks') === false) continue;
    
    $normalized[] = [
        "sembol"        => $sym,
        "ad"            => $item['Type'] ?? '',
        "alis"          => trToFloat($item['Close'] ?? null),
        "satis"         => trToFloat($item['Ask'] ?? null),
        "degisim_yuzde" => trToFloat($item['Percent'] ?? null),
        "degisim_tl"    => trToFloat($item['DailyChange'] ?? null),
        "onceki_kapanis"=> trToFloat($item['CloseBefore'] ?? null),
        "acilis"        => trToFloat($item['Open'] ?? null),
        "yon"           => $item['Status'] ?? '',
        "saat"          => $item['Time'] ?? '',
        "ham"           => [
            "Close" => $item['Close'] ?? null,
            "Ask" => $item['Ask'] ?? null,
            "Percent" => $item['Percent'] ?? null
        ]
    ];
}

if (empty($normalized)) {
    echo json_encode([
        "success" => false,
        "message" => "Sonuç bulunamadı",
        "filter" => $filter,
        "type" => $type
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "type" => $type,
    "filter" => $filter ?: null,
    "count" => count($normalized),
    "guncelleme" => date("Y-m-d H:i:s"),
    "kurlar" => $normalized
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);