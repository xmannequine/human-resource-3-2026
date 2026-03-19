<?php
// reverse_geocode.php
session_start();
require_once('config.php'); // optional if you want session/database access

header('Content-Type: application/json');

// SESSION CHECK: only logged-in employees can access
if (!isset($_SESSION['bundy_employee_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';

if (!$lat || !$lon) {
    echo json_encode(['error' => 'Missing coordinates']);
    exit;
}

// Build the Nominatim API URL
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($lat) . "&lon=" . urlencode($lon);

// Use stream context with timeout and custom user-agent
$opts = [
    'http' => [
        'header' => "User-Agent: MyAttendanceApp/1.0\r\n",
        'timeout' => 5 // 5 seconds timeout
    ]
];
$context = stream_context_create($opts);

// Try to get the response
$response = @file_get_contents($url, false, $context);

if (!$response) {
    echo json_encode(['error' => 'Failed to fetch address']);
    exit;
}

// Decode JSON
$data = json_decode($response, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid response from geocode service']);
    exit;
}

// Return only relevant address parts
$address = $data['address'] ?? [];
echo json_encode([
    'address' => [
        'road' => $address['road'] ?? '',
        'suburb' => $address['suburb'] ?? $address['village'] ?? $address['hamlet'] ?? '',
        'city' => $address['city'] ?? $address['town'] ?? $address['municipality'] ?? ''
    ]
]);
