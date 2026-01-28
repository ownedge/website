<?php
/**
 * Intelligence Report Sender
 * sends email to pedro@ownedge.com
 */

// CORS implementation
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing message']);
        exit;
    }

    $to = 'pedro@ownedge.com';
    $subject = isset($data['subject']) && $data['subject'] ? $data['subject'] : 'INTELLIGENCE REPORT';
    $name = isset($data['name']) && $data['name'] ? $data['name'] : 'ANONYMOUS';
    
    // Construct email body
    $body = $data['message'];
    $body .= "\n\n--\nSENT BY: " . $name;
    
    // Optional: Add sender IP info
    $ip = $_SERVER['REMOTE_ADDR'];
    $body .= "\nIP: " . $ip;

    $headers = [
        'From' => 'no-reply@ownedge.com',
        'Reply-To' => 'no-reply@ownedge.com',
        'X-Mailer' => 'PHP/' . phpversion()
    ];

    $success = mail($to, $subject, $body, $headers);

    if ($success) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Transmission complete']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Transmission failed']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
