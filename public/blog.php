<?php
header('Content-Type: application/json');

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$action = $_GET['action'] ?? 'list';
$blogDir = __DIR__ . '/blog';
$statsFile = $blogDir . '/stats.json';

// Initialize stats file if needed
if (!file_exists($statsFile)) {
    file_put_contents($statsFile, json_encode([]));
}

function getStats() {
    global $statsFile;
    if (file_exists($statsFile)) {
        return json_decode(file_get_contents($statsFile), true) ?: [];
    }
    return [];
}

function saveStats($stats) {
    global $statsFile;
    // Simple locking mechanism
    $fp = fopen($statsFile, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($stats, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

if ($action === 'list') {
    $files = glob($blogDir . '/*.html');
    $posts = [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);
        
        // Parse metadata block
        // Format:
        // <!--
        // ::metadata::
        // key: value
        // ::/metadata::
        // -->
        
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
            
            // Only add if we have at least an ID and Title
            if (isset($meta['id']) && isset($meta['title'])) {
                $posts[] = $meta;
            }
        }
    }
    
    // Sort by date descending
    usort($posts, function($a, $b) {
        return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
    });

    echo json_encode($posts);
    exit;
}

if ($action === 'stats') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['error' => 'Missing ID']);
        exit;
    }
    
    $stats = getStats();
    $postStats = $stats[$id] ?? ['views' => 0, 'kudos' => 0];
    
    echo json_encode($postStats);
    exit;
}

if ($action === 'view') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['error' => 'Missing ID']);
        exit;
    }
    
    $stats = getStats();
    if (!isset($stats[$id])) {
        $stats[$id] = ['views' => 0, 'kudos' => 0];
    }
    
    $stats[$id]['views']++;
    saveStats($stats);
    
    echo json_encode($stats[$id]);
    exit;
}

if ($action === 'kudo') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['error' => 'Missing ID']);
        exit;
    }
    
    $stats = getStats();
    if (!isset($stats[$id])) {
        $stats[$id] = ['views' => 0, 'kudos' => 0];
    }
    
    $stats[$id]['kudos']++;
    saveStats($stats);
    
    echo json_encode($stats[$id]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
