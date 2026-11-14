<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 81 ilin PTT kodu (plaka kodu ile aynı)
$IL_KODLARI = [
    "ADANA"=>"1","ADIYAMAN"=>"2","AFYONKARAHİSAR"=>"3","AĞRI"=>"4","AMASYA"=>"5",
    "ANKARA"=>"6","ANTALYA"=>"7","ARTVİN"=>"8","AYDIN"=>"9","BALIKESİR"=>"10",
    "BİLECİK"=>"11","BİNGÖL"=>"12","BİTLİS"=>"13","BOLU"=>"14","BURDUR"=>"15",
    "BURSA"=>"16","ÇANAKKALE"=>"17","ÇANKIRI"=>"18","ÇORUM"=>"19","DENİZLİ"=>"20",
    "DİYARBAKIR"=>"21","EDİRNE"=>"22","ELAZIĞ"=>"23","ERZİNCAN"=>"24","ERZURUM"=>"25",
    "ESKİŞEHİR"=>"26","GAZİANTEP"=>"27","GİRESUN"=>"28","GÜMÜŞHANE"=>"29","HAKKARİ"=>"30",
    "HATAY"=>"31","ISPARTA"=>"32","MERSİN"=>"33","İSTANBUL"=>"34","İZMİR"=>"35",
    "KARS"=>"36","KASTAMONU"=>"37","KAYSERİ"=>"38","KIRKLARELİ"=>"39","KIRŞEHİR"=>"40",
    "KOCAELİ"=>"41","KONYA"=>"42","KÜTAHYA"=>"43","MALATYA"=>"44","MANİSA"=>"45",
    "KAHRAMANMARAŞ"=>"46","MARDİN"=>"47","MUĞLA"=>"48","MUŞ"=>"49","NEVŞEHİR"=>"50",
    "NİĞDE"=>"51","ORDU"=>"52","RİZE"=>"53","SAKARYA"=>"54","SAMSUN"=>"55",
    "SİİRT"=>"56","SİNOP"=>"57","SİVAS"=>"58","TEKİRDAĞ"=>"59","TOKAT"=>"60",
    "TRABZON"=>"61","TUNCELİ"=>"62","ŞANLIURFA"=>"63","UŞAK"=>"64","VAN"=>"65",
    "YOZGAT"=>"66","ZONGULDAK"=>"67","AKSARAY"=>"68","BAYBURT"=>"69","KARAMAN"=>"70",
    "KIRIKKALE"=>"71","BATMAN"=>"72","ŞIRNAK"=>"73","BARTIN"=>"74","ARDAHAN"=>"75",
    "IĞDIR"=>"76","YALOVA"=>"77","KARABÜK"=>"78","KİLİS"=>"79","OSMANİYE"=>"80","DÜZCE"=>"81"
];

function pttApi($payload) {
    $ch = curl_init("https://www.ptt.gov.tr/api/posta-kodu");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json, text/plain, */*",
            "Origin: https://www.ptt.gov.tr",
            "Referer: https://www.ptt.gov.tr/posta-kodu",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => ""
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

$il = mb_strtoupper(trim($_GET['il'] ?? ''), 'UTF-8');
$ilce = mb_strtoupper(trim($_GET['ilce'] ?? ''), 'UTF-8');
$mahalle = trim($_GET['mahalle'] ?? '');

if (!$il || !$ilce || !$mahalle) {
    echo json_encode(["success" => false, "message" => "il, ilce ve mahalle zorunlu."]);
    exit;
}

// Türkçe karakter normalizasyonu için
$ilNorm = str_replace(["İ","I"], "I", $il);
$ilKodu = null;
foreach ($IL_KODLARI as $isim => $kod) {
    if (str_replace(["İ","I"], "I", $isim) === $ilNorm) {
        $ilKodu = $kod;
        break;
    }
}

if (!$ilKodu) {
    echo json_encode(["success" => false, "message" => "İl bulunamadı: $il"]);
    exit;
}

// İlçe kodunu bul
$ilceler = pttApi(["action" => "ilceler", "il_kodu" => $ilKodu]);
if (!$ilceler) {
    echo json_encode(["success" => false, "message" => "İlçe listesi alınamadı."]);
    exit;
}

$ilceKodu = null;
$ilceNorm = str_replace(["İ","I"], "I", $ilce);
foreach ($ilceler as $i) {
    if (str_replace(["İ","I"], "I", mb_strtoupper($i['ad'], 'UTF-8')) === $ilceNorm) {
        $ilceKodu = (string) $i['kod'];
        break;
    }
}

if (!$ilceKodu) {
    echo json_encode(["success" => false, "message" => "İlçe bulunamadı: $ilce"]);
    exit;
}

// Posta kodlarını çek
$postakodlari = pttApi([
    "action" => "postakodu",
    "il_kodu" => $ilKodu,
    "ilce_kodu" => $ilceKodu
]);

if (!$postakodlari || !is_array($postakodlari)) {
    echo json_encode(["success" => false, "message" => "Posta kodu verisi alınamadı."]);
    exit;
}

$aranan = mb_strtoupper(str_replace([" MAH.", " MAH"], "", $mahalle), 'UTF-8');
$bulunanlar = [];

foreach ($postakodlari as $item) {
    $m = mb_strtoupper(str_replace([" MAH.", " MAH"], "", $item['mahalleAdi'] ?? ''), 'UTF-8');
    $s = mb_strtoupper($item['sokakAdi'] ?? '', 'UTF-8');
    if (mb_strpos($m, $aranan) !== false || mb_strpos($s, $aranan) !== false) {
        $bulunanlar[] = [
            "Mahalle" => $item['mahalleAdi'],
            "Sokak" => $item['sokakAdi'],
            "Posta Kodu" => $item['posta_Kodu']
        ];
    }
}

echo json_encode([
    "success" => true,
    "il" => $il,
    "ilce" => $ilce,
    "aranan" => $mahalle,
    "sonuc_sayisi" => count($bulunanlar),
    "sonuclar" => array_slice($bulunanlar, 0, 20)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);