<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Ownedge Guestbook Backend
 */

$data_file = 'guestbook-entries.json';

// CORS implementation
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ensure data file exists
if (!file_exists($data_file)) {
    file_put_contents($data_file, json_encode([]));
}

header('Content-Type: application/json');

// --- ROUTING ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $content = @file_get_contents($data_file);
    echo $content ?: '[]';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $newEntry = json_decode($json, true);
    
    if ($newEntry && isset($newEntry['message'])) {
        $entries = json_decode(file_get_contents($data_file), true) ?: [];
        
        $entry = [
            'id' => microtime(true) . rand(100, 999),
            'name' => isset($newEntry['name']) ? $newEntry['name'] : 'ANONYMOUS',
            'message' => $newEntry['message'],
            'rating' => isset($newEntry['rating']) ? (int)$newEntry['rating'] : 5,
            'timestamp' => date('c')
        ];
        
        $entries[] = $entry;
        file_put_contents($data_file, json_encode($entries), LOCK_EX);
        
        http_response_code(201);
        echo json_encode($entry);
        exit;
    }
}

http_response_code(404);
echo json_encode(["error" => "Resource Not Found"]);
