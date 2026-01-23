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
            'content' => "The modern web has become flattened. Templates, frameworks, and design systems have streamlined production but eroded character.\n\nOwnedge is an experiment in reclaiming the digital texture. We believe interfaces should feel like *places*, not just documents. They should have atmosphere, sound, and friction.\n\nEfficiency is for machines. Experience is for humans.",
            'views' => 1240,
            'kudos' => 42
        ],
        [
            'id' => 'terminal-aesthetics',
            'title' => 'TERMINAL AESTHETICS',
            'date' => '2025-06-20T09:30:00Z',
            'summary' => 'Exploring the enduring appeal of the command line.',
            'content' => "Green phosphor. Blinking cursors. The silence of the void.\n\nThere is a specific comfort in the terminal. It promises direct control. It hides nothing, yet reveals only what you ask for.\n\nIn building this site, we wanted to capture that feeling of being a 'user' in the TRON sense—someone with agency, operating a machine, rather than a consumer scrolling a feed.",
            'views' => 890,
            'kudos' => 28
        ],
        [
            'id' => 'signal-noise',
            'title' => 'SIGNAL / NOISE',
            'date' => '2025-08-15T18:45:00Z',
            'summary' => 'Filtering reality through digital artifacts.',
            'content' => "We added glitch effects not just for style, but as a reminder of the medium's fragility.\n\nA pure signal is an illusion. Every transmission degrades. Each packet loss tells a story of distance and latency.\n\nEmbrace the noise.",
            'views' => 450,
            'kudos' => 15
        ]
        [
            'id' => 'hello-world',
            'title' => 'HELLO WORLD',
            'date' => '2025-09-01T12:00:00Z',
            'summary' => 'Just a test post to verify system integrity.',
            'content' => "This is a dummy entry.\n\nTesting the blog system capabilities including:\n- Line breaks\n- Content rendering\n- Stats tracking\n\nEnd of transmission.",
            'views' => 1,
            'kudos' => 0
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
                
                if ($save_needed) file_put_contents($data_file, json_encode($posts), LOCK_EX);
                echo json_encode($p);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(["error" => "Post not found"]);
    } else {
        // Return List (Summary only usually, but full is fine for small blog)
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
