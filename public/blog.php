<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Ownedge Blog Backend
 */

$data_file = 'blog-posts.json';

// CORS implementation
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ensure data file exists and seed if empty
if (!file_exists($data_file)) {
    $seed = [
        [
            'id' => 'manifesto',
            'title' => 'THE INDEPENDENT WEB',
            'date' => '2025-05-12T14:00:00Z',
            'summary' => 'Why we build strange things in a standardized world.',
            'views' => 1240,
            'kudos' => 42
        ],
        [
            'id' => 'terminal-aesthetics',
            'title' => 'TERMINAL AESTHETICS',
            'date' => '2025-06-20T09:30:00Z',
            'summary' => 'Exploring the enduring appeal of the command line.',
            'views' => 890,
            'kudos' => 28
        ]
    ];
    file_put_contents($data_file, json_encode($seed));
}

header('Content-Type: application/json');

$posts = json_decode(file_get_contents($data_file), true) ?: [];
$save_needed = false;

// --- ROUTING ---
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($id) {
        // Return Single Post + Increment Views
        foreach ($posts as &$p) {
            if ($p['id'] === $id) {
                if (!isset($p['views'])) $p['views'] = 0;
                $p['views']++;
                $save_needed = true;
                
                // Load Content from HTML file
                $htmlFile = __DIR__ . "/blog/{$id}.html";
                if (file_exists($htmlFile)) {
                    $p['content'] = file_get_contents($htmlFile);
                } else {
                    $p['content'] = "<p>DATA CORRUPTED. CONTENT NOT FOUND ON DISK.</p>";
                }
                
                if ($save_needed) file_put_contents($data_file, json_encode($posts), LOCK_EX);
                echo json_encode($p);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(["error" => "Post not found"]);
    } else {
        // Return List (Summary only)
        // Sort by date desc
        usort($posts, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        echo json_encode($posts);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if ($id && isset($data['action']) && $data['action'] === 'kudos') {
        foreach ($posts as &$p) {
            if ($p['id'] === $id) {
                if (!isset($p['kudos'])) $p['kudos'] = 0;
                $p['kudos']++;
                $save_needed = true;
                
                if ($save_needed) file_put_contents($data_file, json_encode($posts), LOCK_EX);
                echo json_encode(["status" => "ok", "kudos" => $p['kudos']]);
                exit;
            }
        }
    }
}

http_response_code(404);
echo json_encode(["error" => "Action not found"]);
