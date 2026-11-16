<?php
// denizli-otobus.php - Denizli Büyükşehir Belediyesi otobüs hat ve durak sorgulama
// Kullanım:
//   ?action=hatlar&q=             → tüm hatlar
//   ?action=hatlar&q=gökpınar     → hatta göre ara
//   ?action=hat&lineNo=201        → hat detayı (durak listesi)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$BASE = "https://ulasim.denizli.bel.tr/api";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

function req($url, $opts = []) {
    global $UA;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_USERAGENT => $UA,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json, text/plain, */*",
            "Accept-Language: tr-TR,tr;q=0.9",
            "x-requested-by: portal",
            "Origin: https://ulasim.denizli.bel.tr",
            "Referer: https://ulasim.denizli.bel.tr/"
        ],
    ] + $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ["body" => $body, "code" => $code, "error" => $err];
}

$action = $_GET['action'] ?? 'hatlar';
$q = $_GET['q'] ?? '';
$lineNo = $_GET['lineNo'] ?? '';

// ==================== HATLAR ====================
if ($action === 'hatlar') {
    $url = "$BASE/routes?text=" . urlencode($q) . "&returns=false";
    $r = req($url);
    
    if ($r['error']) {
        echo json_encode(["success" => false, "message" => "cURL: " . $r['error']]);
        exit;
    }
    if ($r['code'] !== 200) {
        echo json_encode(["success" => false, "message" => "HTTP " . $r['code']]);
        exit;
    }
    
    $data = json_decode($r['body'], true);
    if (!$data || !isset($data['value'])) {
        echo json_encode(["success" => false, "message" => "Geçersiz yanıt", "raw" => substr($r['body'], 0, 300)]);
        exit;
    }
    
    // Sadeleştirilmiş liste
    $hatlar = [];
    foreach ($data['value'] as $h) {
        $hatlar[] = [
            "lineNo" => $h['lineNo'] ?? null,
            "lineCode" => $h['lineCode'] ?? '',
            "lineName" => $h['lineName'] ?? '',
            "shortName" => $h['shortLineName'] ?? ''
        ];
    }
    
    echo json_encode([
        "success" => true,
        "query" => $q,
        "count" => count($hatlar),
        "hatlar" => $hatlar
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==================== HAT DETAYI (DURAKLAR) ====================
if ($action === 'hat') {
    if (!$lineNo) {
        echo json_encode(["success" => false, "message" => "lineNo gerekli"]);
        exit;
    }
    
    // Önce hattı bul
    $r = req("$BASE/routes?text=&returns=false");
    $routes = json_decode($r['body'], true)['value'] ?? [];
    
    $hat = null;
    foreach ($routes as $h) {
        if ((string)$h['lineNo'] === (string)$lineNo) {
            $hat = $h;
            break;
        }
    }
    
    if (!$hat) {
        echo json_encode(["success" => false, "message" => "Hat bulunamadı: $lineNo"]);
        exit;
    }
    
    // Durak listesi (tahmini endpoint - denenmesi lazım)
    $durakUrl = "$BASE/route/$lineNo/stations";
    $r2 = req($durakUrl);
    
    // Alternatif endpoint'ler
    if ($r2['code'] !== 200) {
        $durakUrl = "$BASE/stations?routeNo=$lineNo";
        $r2 = req($durakUrl);
    }
    
    echo json_encode([
        "success" => true,
        "hat" => $hat,
        "durak_url" => $durakUrl,
        "durak_http" => $r2['code'],
        "duraklar" => json_decode($r2['body'], true) ?? $r2['body']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode(["success" => false, "message" => "Geçersiz action (hatlar | hat)"]);