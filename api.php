<?php
/**
 * Instagram Downloader - بدون ثبت‌نام، بدون API Key
 * استفاده از Cobalt API (open-source و رایگان)
 * 
 * استفاده:
 * GET: instagram.php?url=https://www.instagram.com/reel/ABC123/
 * POST: {"url": "https://www.instagram.com/reel/ABC123/"}
 */

// ═══════════════════════════════════════════════════════
// Headers
// ═══════════════════════════════════════════════════════
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ═══════════════════════════════════════════════════════
// تنظیمات Cobalt API
// ═══════════════════════════════════════════════════════
$config = [
    // Cobalt instances (اگر یکی کار نکرد، دیگری را امتحان می‌کنیم)
    'instances' => [
        'https://api.cobalt.tools/',          // رسمی
        'https://co.wuk.acocot.com/',         // instance جایگزین
    ],
    
    'timeout' => 30,
    'user_agent' => 'InstagramDownloader/1.0',
];

// ═══════════════════════════════════════════════════════
// دریافت URL
// ═══════════════════════════════════════════════════════
$url = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $url = $_GET['url'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? $_POST['url'] ?? '';
}

if (empty($url)) {
    sendError(400, 'پارامتر url الزامی است');
}

if (!preg_match('/instagram\.com\/(p|reel|tv)\//', $url)) {
    sendError(400, 'آدرس اینستاگرام نامعتبر است');
}

// ═══════════════════════════════════════════════════════
// پردازش
// ═══════════════════════════════════════════════════════
try {
    $result = fetchFromCobalt($url, $config);
    
    if ($result === false) {
        sendError(500, 'دریافت لینک دانلود ناموفق بود. لینک را بررسی کنید یا بعداً امتحان کنید.');
    }
    
    sendSuccess($result);
    
} catch (Exception $e) {
    sendError(500, 'خطا: ' . $e->getMessage());
}

// ═══════════════════════════════════════════════════════
// توابع
// ═══════════════════════════════════════════════════════

function fetchFromCobalt(string $url, array $config): array|false
{
    // Payload درخواست
    $payload = [
        'url' => $url,
        'downloadMode' => 'auto', // auto, audio, mute
        'filenameStyle' => 'basic',
    ];
    
    // امتحان کردن هر instance
    foreach ($config['instances'] as $instance) {
        $result = callCobaltAPI($instance, $payload, $config['timeout']);
        
        if ($result !== false) {
            return parseCobaltResponse($result, $url);
        }
    }
    
    return false;
}

function callCobaltAPI(string $instance, array $payload, int $timeout): array|false
{
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($instance, '/') . '/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || $httpCode !== 200 || empty($response)) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    // Cobalt دو نوع پاسخ دارد
    if (isset($data['status']) && $data['status'] === 'error') {
        return false;
    }
    
    return $data ?: false;
}

function parseCobaltResponse(array $data, string $originalUrl): array|false
{
    // استخراج shortcode
    preg_match('/instagram\.com\/(?:p|reel|tv)\/([A-Za-z0-9_-]+)/', $originalUrl, $m);
    $shortcode = $m[1] ?? '';
    
    $result = [
        'shortcode' => $shortcode,
        'original_url' => $originalUrl,
        'media_type' => 'unknown',
        'download_links' => [],
        'metadata' => [],
    ];
    
    // Cobalt پاسخ را در این ساختار می‌دهد:
    // { "status": "tunnel"/"redirect"/"picker", "url": "...", "filename": "..." }
    // یا برای چند فایل: "picker": [{ "url": "..." }, ...]
    
    $status = $data['status'] ?? '';
    
    if ($status === 'picker' && !empty($data['picker'])) {
        // کاروسل (چند عکس/ویدیو)
        $result['media_type'] = 'carousel';
        foreach ($data['picker'] as $index => $item) {
            $type = isset($item['type']) && $item['type'] === 'video' ? 'video' : 'image';
            $result['download_links'][] = [
                'type' => $type,
                'index' => $index + 1,
                'url' => $item['url'] ?? '',
                'thumb' => $item['thumb'] ?? '',
            ];
        }
    } elseif (!empty($data['url'])) {
        // تک فایل
        $filename = $data['filename'] ?? '';
        $isVideo = stripos($filename, '.mp4') !== false || stripos($filename, 'video') !== false;
        
        $result['media_type'] = $isVideo ? 'video' : 'image';
        $result['download_links'][] = [
            'type' => $isVideo ? 'video' : 'image',
            'url' => $data['url'],
            'filename' => $filename,
        ];
    } else {
        return false;
    }
    
    // متادیتا
    if (!empty($data['filename'])) {
        $result['metadata']['filename'] = $data['filename'];
    }
    if (!empty($data['service'])) {
        $result['metadata']['service'] = $data['service'];
    }
    
    return $result;
}

function sendSuccess(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
