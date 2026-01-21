<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Robust PHP Chat Backend
 */

$log_file = 'chat-log.json';
$users_file = 'chat-users.json';
$topic_file = 'chat-topic.json';

// CORS Implementation
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Helpers
function fetch_data($file) {
    if (!file_exists($file)) return [];
    $content = @file_get_contents($file);
    if ($content === false) return [];
    return json_decode($content, true) ?: [];
}

function save_data($file, $data) {
    return file_put_contents($file, json_encode($data), LOCK_EX);
}

// Ensure files exist
if (!file_exists($log_file)) save_data($log_file, []);
if (!file_exists($users_file)) save_data($users_file, []);
if (!file_exists($topic_file)) save_data($topic_file, ["topic" => "OWNEDGE - EST 2011", "author" => "Admin", "modified" => date('Y-m-d H:i:s')]);

// --- PRE-PROCESS: Atomic Cleanup (Users & Messages) ---
$lock_file = '.cleanup.lock';
$lock_fp = fopen($lock_file, 'c+');

if ($lock_fp && flock($lock_fp, LOCK_EX | LOCK_NB)) { // Non-blocking lock
    $users = fetch_data($users_file);
    $messages = fetch_data($log_file);
    $now_ts = time();
    $now_micro = microtime(true);
    $changed_users = false;
    $changed_msgs = false;

    // 1. Prune Users (Timeout)
    foreach ($users as $nick => $lastSeen) {
        if ($now_ts - $lastSeen > 45) { // 45 second timeout
            $messages[] = [
                'id' => sprintf("%.4f", microtime(true)) . rand(100, 999),
                'type' => 'system',
                'text' => "*** $nick has left (timeout)",
                'timestamp' => date('c')
            ];
            unset($users[$nick]);
            $changed_users = true;
            $changed_msgs = true;
        }
    }

    // 2. Prune Messages (Transient Relay Logic)
    // Keep only messages from the last 30 seconds to serve as a relay buffer
    $initial_msg_count = count($messages);
    $messages = array_values(array_filter($messages, function($m) use ($now_micro) {
        return ($now_micro - (float)$m['id']) < 30; 
    }));
    if (count($messages) !== $initial_msg_count) {
        $changed_msgs = true;
    }

    if ($changed_users) save_data($users_file, $users);
    if ($changed_msgs) save_data($log_file, $messages);
    
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
} elseif ($lock_fp) {
    fclose($lock_fp);
}

// Refresh $users for the rest of the request
$users = fetch_data($users_file);

// --- ROUTING ---
$action = isset($_GET['action']) ? $_GET['action'] : 'messages';
if ($action === 'chat.php') $action = 'messages';

header('Content-Type: application/json');

if ($action === 'messages') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $messages = fetch_data($log_file);
        $since = isset($_GET['since']) ? (float)$_GET['since'] : 0;
        
        if ($since > 0) {
            $filtered = array_values(array_filter($messages, function($m) use ($since) {
                return (float)$m['id'] > $since;
            }));
            echo json_encode($filtered);
        } else {
            echo json_encode($messages);
        }
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = file_get_contents('php://input');
        $newMessage = json_decode($json, true);
        if ($newMessage) {
            $messages = fetch_data($log_file);
            // Ensure ID is a high-precision float string for comparison
            $newMessage['id'] = sprintf("%.4f", microtime(true)) . rand(100, 999);
            if (!isset($newMessage['timestamp'])) $newMessage['timestamp'] = date('c');
            $messages[] = $newMessage;
            save_data($log_file, $messages);
            
            // Update presence on message
            if (isset($newMessage['user'])) {
                $users[$newMessage['user']] = time();
                save_data($users_file, $users);
            }
            
            http_response_code(201);
            echo json_encode($newMessage);
            exit;
        }
    }
}

if ($action === 'topic') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(fetch_data($topic_file));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (isset($data['topic'])) {
            $user = isset($data['user']) ? $data['user'] : 'Admin';
            $newTopic = [
                "topic" => $data['topic'],
                "author" => $user,
                "modified" => date('Y.m.d H:i:s')
            ];
            save_data($topic_file, $newTopic);
            
            // Broadcast change to chat log
            $messages = fetch_data($log_file);
            $user = isset($data['user']) ? $data['user'] : 'Admin';
            $messages[] = [
                'id' => sprintf("%.4f", microtime(true)) . rand(100, 999),
                'type' => 'system',
                'text' => "*** $user changed the topic to: " . $data['topic'],
                'timestamp' => date('c')
            ];
            save_data($log_file, $messages);
            
            echo json_encode($newTopic);
            exit;
        }
    }
}

if ($action === 'presence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (isset($data['nickname'])) {
        $nick = $data['nickname'];
        
        // Log Join Message if user is new to the active list
        if (!isset($users[$nick])) {
            $messages = fetch_data($log_file);
            $messages[] = [
                'id' => sprintf("%.4f", microtime(true)) . rand(100, 999),
                'type' => 'system',
                'text' => "*** $nick has joined the channel",
                'timestamp' => date('c')
            ];
            save_data($log_file, $messages);

            // --- ENHANCED METADATA CAPTURE ---
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

            // NEW: Enhanced Email Notification
            $admin_email = "hello@ownedge.com"; 
            $subject = "OWNEDGE: Intelligence Briefing - Join Detected";
            $body = "INTELLIGENCE REPORT\n";
            $body .= "====================\n";
            $body .= "NODE ID     : $nick\n";
            $body .= "TIMESTAMP   : " . date('Y-m-d H:i:s') . "\n";
            $body .= "IP ADDRESS  : $ip\n";
            $body .= "LOCATION    : $location\n";
            $body .= "OPERATING SYS: $os\n";
            $body .= "BROWSER     : $browser\n\n";
            $body .= "RAW UA      : $ua\n";
            $body .= "====================";
            
            $headers = "From: node-monitor@ownedge.com";
            @mail($admin_email, $subject, $body, $headers);
        }

        $users[$nick] = time();
        save_data($users_file, $users);
        echo json_encode(["status" => "ok"]);
        exit;
    }
}

if ($action === 'leave' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (isset($data['nickname'])) {
        $nick = $data['nickname'];
        unset($users[$nick]);
        save_data($users_file, $users);
        
        $messages = fetch_data($log_file);
        $messages[] = [
            'id' => sprintf("%.4f", microtime(true)) . rand(100, 999),
            'type' => 'system',
            'text' => "*** $nick has left (disconnected)",
            'timestamp' => date('c')
        ];
        save_data($log_file, $messages);
        
        echo json_encode(["status" => "ok"]);
        exit;
    }
}

if ($action === 'users') {
    echo json_encode(array_values(array_keys($users)));
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Action $action Not Found"]);
