<?php
session_start();
require_once('config2.php');
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

if (empty($_SESSION['bundy_employee_id'])) {
    echo json_encode(['success'=>false,'message'=>'Session expired']);
    exit;
}

$employeeId = $_SESSION['bundy_employee_id'];
$action     = $_POST['action'] ?? '';

$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$today = date('Y-m-d');

/* -------------------------
   REVERSE GEOCODE PHP
------------------------- */
function reverseGeocode($lat, $lng) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['User-Agent: HR3-Bundy-System/1.0'],
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (!empty($data['address'])) {
        return [
            'street'   => $data['address']['road'] ?? '',
            'barangay' => $data['address']['suburb'] ?? $data['address']['village'] ?? $data['address']['hamlet'] ?? '',
            'city'     => $data['address']['city'] ?? $data['address']['town'] ?? $data['address']['municipality'] ?? $data['address']['county'] ?? ''
        ];
    }
    return ['street'=>'','barangay'=>'','city'=>''];
}

$address = ($lat && $lng) ? reverseGeocode($lat, $lng) : ['street'=>'','barangay'=>'','city'=>''];
$street   = $address['street'];
$barangay = $address['barangay'];
$city     = $address['city'];

/* -------------------------
   GET LATEST ATTENDANCE
------------------------- */
$stmt = $conn->prepare("
    SELECT * FROM attendance
    WHERE employee_id=? AND date=?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$employeeId,$today]);
$last = $stmt->fetch(PDO::FETCH_ASSOC);

/* -------------------------
   HANDLE TIME IN
------------------------- */
if ($action === 'time_in') {
    if ($last && $last['time_in'] && !$last['time_out']) {
        echo json_encode(['success'=>false,'message'=>'Already timed in']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO attendance
        (employee_id, date, time_in, street_in, barangay_in, city_in)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->execute([$employeeId, $today, $street, $barangay, $city]);
    echo json_encode(['success'=>true]);
    exit;
}

/* -------------------------
   HANDLE TIME OUT
------------------------- */
if ($action === 'time_out') {
    if (!$last || !$last['time_in'] || $last['time_out']) {
        echo json_encode(['success'=>false,'message'=>'No active time-in']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE attendance SET
            time_out = NOW(),
            street_out = ?,
            barangay_out = ?,
            city_out = ?
        WHERE id = ?
    ");
    $stmt->execute([$street, $barangay, $city, $last['id']]);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Invalid action']);
