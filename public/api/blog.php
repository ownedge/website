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
// Initialize stats file if needed
if (!file_exists($statsFile)) {
    file_put_contents($statsFile, json_encode([]));
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
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lock failed']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
