<?php
/**
 * Instagram Downloader API
 * A free web service API for downloading Instagram posts and reels
 * 
 * @author API Developer
 * @version 2.0
 * @license MIT
 */

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for API response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Instagram Downloader Class
 */
class InstagramDownloader {
    
    private $userAgent;
    private $cookies;
    private $tempDir;
    
    public function __construct() {
        // Random user agent to avoid detection
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1'
        ];
        $this->userAgent = $this->userAgents[array_rand($this->userAgents)];
        $this->cookies = tempnam(sys_get_temp_dir(), 'ig_cookies_');
        $this->tempDir = sys_get_temp_dir();
    }
    
    /**
     * Clean up temporary files
     */
    public function __destruct() {
        if (file_exists($this->cookies)) {
            @unlink($this->cookies);
        }
    }
    
    /**
     * Make HTTP request with cURL
     */
    private function makeRequest($url, $headers = [], $postData = null) {
        $ch = curl_init();
        
        $defaultHeaders = [
            'User-Agent: ' . $this->userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
        ];
        
        if (!empty($headers)) {
            $defaultHeaders = array_merge($defaultHeaders, $headers);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $defaultHeaders,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
        ]);
        
        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        return ['success' => true, 'data' => $response, 'http_code' => $httpCode];
    }
    
    /**
     * Extract shortcode from Instagram URL
     */
    private function extractShortcode($url) {
        $patterns = [
            '/(?:instagram\.com\/reel\/|instagr\.am\/reel\/)([a-zA-Z0-9_-]+)/',
            '/(?:instagram\.com\/p\/|instagr\.am\/p\/)([a-zA-Z0-9_-]+)/',
            '/(?:instagram\.com\/tv\/|instagr\.am\/tv\/)([a-zA-Z0-9_-]+)/',
            '/([a-zA-Z0-9_-]+)(?:\/|$)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Get media data from Instagram GraphQL
     */
    private function getMediaData($shortcode) {
        $url = "https://www.instagram.com/reel/{$shortcode}/?__d=1";
        
        $result = $this->makeRequest($url);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to fetch Instagram page'];
        }
        
        $html = $result['data'];
        
        // Try multiple patterns to extract JSON data
        $patterns = [
            '/"media":\s*({.+?})\s*,"/',
            '/"items":\s*\[(.+?)\]\s*[,\]]/',
            '/<script[^>]*type="text\/javascript"[^>]*>\s*window\._sharedData\s*=\s*({.+?});\s*<\/script>/s',
            '/<script[^>]*>\s*var\s+__additionalData\s*=\s*({.+?});\s*<\/script>/s',
        ];
        
        // Look for the main JSON structure
        if (preg_match('/window\._sharedData\s*=\s*({.+?});\s*<\/script>/', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if ($jsonData && isset($jsonData['graphql'])) {
                return ['success' => true, 'data' => $jsonData['graphql']];
            }
        }
        
        // Alternative: look for structured data - improved pattern
        if (preg_match('/"graphql":\s*({.+?})\s*\}\s*,\s*"toast_content_type"/s', $html, $matches)) {
            $jsonString = '{"graphql":' . $matches[1] . '}}';
            $jsonData = json_decode($jsonString, true);
            if ($jsonData && isset($jsonData['graphql'])) {
                return ['success' => true, 'data' => $jsonData['graphql']];
            }
        }
        
        // Try to find shortcode_media directly
        if (preg_match('/"shortcode_media":\s*({.+?})\s*\}\s*,\s*"location"/s', $html, $matches)) {
            $mediaData = json_decode('{"shortcode_media":' . $matches[1] . '}}', true);
            if ($mediaData && isset($mediaData['shortcode_media'])) {
                return ['success' => true, 'data' => ['shortcode_media' => $mediaData['shortcode_media']]];
            }
        }
        
        // Extract using regex for specific fields
        $mediaInfo = $this->extractMediaInfoFromHTML($html);
        if ($mediaInfo) {
            return ['success' => true, 'data' => $mediaInfo];
        }
        
        return ['success' => false, 'error' => 'Could not parse media data'];
    }
    
    /**
     * Extract media information from HTML
     */
    private function extractMediaInfoFromHTML($html) {
        $mediaInfo = [];
        
        // Extract video URL - improved pattern to handle escaped characters
        if (preg_match('/"video_url":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches)) {
            $mediaInfo['video_url'] = str_replace('\u002F', '/', $matches[1]);
        }
        
        // Extract display image URL - improved pattern
        if (preg_match('/"display_url":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches)) {
            $mediaInfo['display_url'] = str_replace('\u002F', '/', $matches[1]);
        }
        
        // Extract caption - improved to handle escaped characters
        if (preg_match('/"caption":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches)) {
            $mediaInfo['caption'] = str_replace(['\n', '\u002F', '\"'], ["\n", '/', '"'], $matches[1]);
        }
        
        // Extract username
        if (preg_match('/"username":"([^"]+)"/', $html, $matches)) {
            $mediaInfo['username'] = $matches[1];
        }
        
        // Extract likes count
        if (preg_match('/"edge_media_preview_like":\s*{\s*"count":\s*(\d+)/', $html, $matches)) {
            $mediaInfo['likes'] = (int)$matches[1];
        }
        
        // Extract comments count
        if (preg_match('/"edge_media_to_comment":\s*{\s*"count":\s*(\d+)/', $html, $matches)) {
            $mediaInfo['comments'] = (int)$matches[1];
        }
        
        // Extract is_video flag
        if (preg_match('/"is_video":\s*(true|false)/', $html, $matches)) {
            $mediaInfo['is_video'] = ($matches[1] === 'true');
        }
        
        // Extract thumbnail URL - improved pattern
        if (preg_match('/"thumbnail_src":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches)) {
            $mediaInfo['thumbnail_src'] = str_replace('\u002F', '/', $matches[1]);
        }
        
        // Extract multiple images/videos for carousel - improved pattern
        if (preg_match_all('/"display_url":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches)) {
            $mediaInfo['carousel_media'] = array_map(function($url) {
                return str_replace('\u002F', '/', $url);
            }, $matches[1]);
        }
        
        if (!empty($mediaInfo)) {
            return ['shortcode_media' => $mediaInfo];
        }
        
        return null;
    }
    
    /**
     * Process and format media data
     */
    private function processMediaData($data) {
        if (!isset($data['shortcode_media']) && !isset($data['xdt_shortcode_media'])) {
            return ['success' => false, 'error' => 'Invalid media data structure'];
        }
        
        $media = $data['shortcode_media'] ?? $data['xdt_shortcode_media'];
        
        $result = [
            'success' => true,
            'data' => [
                'type' => $media['is_video'] ? 'video' : 'image',
                'shortcode' => $media['shortcode'] ?? '',
                'username' => $media['owner']['username'] ?? '',
                'full_name' => $media['owner']['full_name'] ?? '',
                'profile_pic_url' => $media['owner']['profile_pic_url'] ?? '',
                'caption' => $media['edge_media_to_caption']['edges'][0]['node']['text'] ?? 
                           $media['caption'] ?? '',
                'likes' => $media['edge_media_preview_like']['count'] ?? 
                          $media['likes'] ?? 0,
                'comments' => $media['edge_media_to_comment']['count'] ?? 
                             $media['comments'] ?? 0,
                'timestamp' => $media['taken_at_timestamp'] ?? time(),
                'is_video' => $media['is_video'] ?? false,
            ]
        ];
        
        // Handle video/reel
        if ($media['is_video']) {
            $result['data']['video_url'] = $media['video_url'] ?? '';
            $result['data']['video_view_count'] = $media['video_view_count'] ?? 0;
            $result['data']['video_duration'] = $media['video_duration'] ?? 0;
            $result['data']['thumbnail_url'] = $media['thumbnail_src'] ?? 
                                              $media['display_url'] ?? '';
            
            // Fallback: try to get video URL from alternative sources
            if (empty($result['data']['video_url']) && !empty($media['display_url'])) {
                $result['data']['video_url'] = $media['display_url'];
            }
        } 
        // Handle image
        else {
            $result['data']['image_url'] = $media['display_url'] ?? '';
            $result['data']['thumbnail_url'] = $media['thumbnail_src'] ?? 
                                             $media['display_url'] ?? '';
        }
        
        // Handle carousel (multiple images/videos)
        if (isset($media['edge_sidecar_to_children'])) {
            $carouselItems = [];
            foreach ($media['edge_sidecar_to_children']['edges'] as $edge) {
                $node = $edge['node'];
                $item = [
                    'type' => $node['is_video'] ? 'video' : 'image',
                    'display_url' => $node['display_url'] ?? '',
                    'is_video' => $node['is_video'] ?? false,
                ];
                
                if ($node['is_video']) {
                    $item['video_url'] = $node['video_url'] ?? '';
                }
                
                $carouselItems[] = $item;
            }
            
            $result['data']['carousel'] = $carouselItems;
            $result['data']['type'] = 'carousel';
        }
        
        return $result;
    }
    
    /**
     * Main method to download media
     */
    public function download($url) {
        // Validate URL
        if (empty($url)) {
            return ['success' => false, 'error' => 'Please provide an Instagram URL'];
        }
        
        // Check if it's a valid Instagram URL
        if (!preg_match('/instagram\.com/', $url)) {
            return ['success' => false, 'error' => 'Invalid Instagram URL'];
        }
        
        // Extract shortcode
        $shortcode = $this->extractShortcode($url);
        
        if (!$shortcode) {
            return ['success' => false, 'error' => 'Could not extract media ID from URL'];
        }
        
        // Get media data
        $mediaData = $this->getMediaData($shortcode);
        
        if (!$mediaData['success']) {
            return $mediaData;
        }
        
        // Process and return formatted data
        return $this->processMediaData($mediaData['data']);
    }
}

// API Endpoint Handler
function handleAPIRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Only accept GET and POST requests
    if (!in_array($method, ['GET', 'POST'])) {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed. Use GET or POST.',
            'code' => 405
        ]);
        return;
    }
    
    // Get URL from request
    $url = '';
    
    if ($method === 'GET') {
        $url = $_GET['url'] ?? $_GET['link'] ?? $_GET['post'] ?? '';
    } else if ($method === 'POST') {
        // Handle JSON input
        $input = file_get_contents('php://input');
        $jsonData = json_decode($input, true);
        
        if ($jsonData && isset($jsonData['url'])) {
            $url = $jsonData['url'];
        } else {
            // Handle form input
            $url = $_POST['url'] ?? $_POST['link'] ?? $_POST['post'] ?? '';
        }
    }
    
    // Validate URL parameter
    if (empty($url)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameter: url',
            'message' => 'Please provide an Instagram post or reel URL',
            'example' => 'https://www.instagram.com/p/ABC123xyz/ or https://www.instagram.com/reel/ABC123xyz/',
            'code' => 400
        ]);
        return;
    }
    
    // Create downloader instance and process request
    $downloader = new InstagramDownloader();
    $result = $downloader->download($url);
    
    // Add API metadata
    $response = array_merge($result, [
        'api_version' => '2.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'request_url' => $url
    ]);
    
    // Set appropriate HTTP status code
    if (!$result['success']) {
        http_response_code(400);
    } else {
        http_response_code(200);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// Execute API handler
handleAPIRequest();
