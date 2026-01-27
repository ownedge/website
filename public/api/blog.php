<?php
header('Content-Type: application/json');

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$action = $_GET['action'] ?? 'list';
$blogDir = __DIR__ . '/../blog';
$statsFile = $blogDir . '/stats.json';

// Initialize stats file if needed
if (!file_exists($statsFile)) {
    file_put_contents($statsFile, json_encode([]));
}

// EMAIL NOTIFICATION HELPER
function send_notification($type, $post_id) {
    // --- METADATA CAPTURE (Ported from chat.php) ---
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    // User Agent Parsing (Basic OS/Browser)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $os = "Unknown OS";
    if (preg_match('/windows|win32/i', $ua)) $os = "Windows";
    else if (preg_match('/macintosh|mac os x/i', $ua)) $os = "macOS";
    else if (preg_match('/linux/i', $ua)) $os = "Linux";
    else if (preg_match('/iphone|ipad|ipod/i', $ua)) $os = "iOS";
    else if (preg_match('/android/i', $ua)) $os = "Android";

    $browser = "Unknown Browser";
    if (preg_match('/firefox/i', $ua)) $browser = "Firefox";
    else if (preg_match('/chrome/i', $ua)) $browser = "Chrome";
    else if (preg_match('/safari/i', $ua)) $browser = "Safari";
    else if (preg_match('/edge/i', $ua)) $browser = "Edge";
    else if (preg_match('/opera|opr/i', $ua)) $browser = "Opera";

    // Geolocation Lookup (ip-api.com)
    $location = "Unknown Location";
    $geo_json = @file_get_contents("http://ip-api.com/json/$ip?fields=status,country,city,isp");
    if ($geo_json) {
        $geo_data = json_decode($geo_json, true);
        if ($geo_data && $geo_data['status'] === 'success') {
            $location = "{$geo_data['city']}, {$geo_data['country']} (ISP: {$geo_data['isp']})";
        }
    }

    $admin_email = "hello@ownedge.com"; 
    $subject = "OWNEDGE: Intelligence Briefing - $type";
    $body = "INTELLIGENCE REPORT\n";
    $body .= "====================\n";
    $body .= "ACTION      : $type\n";
    $body .= "TARGET ID   : $post_id\n";
    $body .= "TIMESTAMP   : " . date('Y-m-d H:i:s') . "\n";
    $body .= "IP ADDRESS  : $ip\n";
    $body .= "LOCATION    : $location\n";
    $body .= "OPERATING SYS: $os\n";
    $body .= "BROWSER     : $browser\n\n";
    $body .= "RAW UA      : $ua\n";
    $body .= "====================";
    
    $headers = "From: blog-monitor@ownedge.com";
    @mail($admin_email, $subject, $body, $headers);
}

// Helper for atomic updates
function updateStat($id, $field) {
    global $statsFile;
    $fp = fopen($statsFile, 'c+'); // Open for reading and writing
    
    if (flock($fp, LOCK_EX)) { // Acquire exclusive lock
        $content = '';
        while (!feof($fp)) {
            $content .= fread($fp, 8192);
        }
        $stats = json_decode($content, true) ?: [];
        
        if (!isset($stats[$id])) {
            $stats[$id] = ['views' => 0, 'kudos' => 0];
        }
        
        $stats[$id][$field]++;
        
        ftruncate($fp, 0); // Clear file
        rewind($fp);
        fwrite($fp, json_encode($stats, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN); // Release lock
        fclose($fp);
        
        return $stats[$id];
    } else {
        fclose($fp);
        return false; // Failed to lock
    }
}

// Helper for safe reading
function getStatsSafe() {
    global $statsFile;
    $fp = fopen($statsFile, 'r');
    if (flock($fp, LOCK_SH)) { // Shared lock
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_decode($content, true) ?: [];
    }
    fclose($fp);
    return [];
}

if ($action === 'list') {
    $files = glob($blogDir . '/*.html');
    $posts = [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);
        
        if (preg_match('/<!--\s*::metadata::(.*?)::\/metadata::\s*-->/s', $content, $matches)) {
            $metaBlock = $matches[1];
            $lines = explode("\n", $metaBlock);
            $meta = ['file' => $filename];
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    [$key, $value] = explode(':', $line, 2);
                    $meta[trim($key)] = trim($value);
                }
            }
            
            if (isset($meta['id']) && isset($meta['title'])) {
                $posts[] = $meta;
            }
        }
    }
    
    usort($posts, function($a, $b) {
        return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
    });

    echo json_encode($posts);
    exit;
}

if ($action === 'stats') {
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['error' => 'Missing ID']); exit; }
    
    $stats = getStatsSafe();
    echo json_encode($stats[$id] ?? ['views' => 0, 'kudos' => 0]);
    exit;
}

if ($action === 'view') {
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['error' => 'Missing ID']); exit; }
    
    $result = updateStat($id, 'views');
    if ($result) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lock failed']);
    }
    exit;
}

if ($action === 'kudo') {
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['error' => 'Missing ID']); exit; }
    
    $result = updateStat($id, 'kudos');
    if ($result) {
        send_notification("KUDOS RECEIVED", $id);
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lock failed']);
    }
    exit;
}

if ($action === 'share') {
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['error' => 'Missing ID']); exit; }
    
    // We don't track share count in stats.json currently, but we can if expected.
    // user instruction: "send email report when someone clicks kudos or share"
    // doesn't explicitly ask for share count tracking, just email.
    
    send_notification("POST SHARED", $id);
    echo json_encode(["status" => "ok"]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
