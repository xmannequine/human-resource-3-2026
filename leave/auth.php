<?php
header('Content-Type: application/json');

$headers = getallheaders();
$token = $headers['X-INTERNAL-TOKEN'] ?? '';

if ($token !== 'ESS_TO_HR_2026') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
