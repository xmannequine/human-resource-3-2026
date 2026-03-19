<?php
header('Content-Type: application/json');
session_start();

// -------------------
// API Key (security)
// -------------------
$API_KEY = 'ESS_SECRET_KEY_123';

// -------------------
// GET API key
// -------------------
// 1️⃣ Check header (future ESS integration)
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

// 2️⃣ Fallback to query string (localhost testing)
if (empty($providedKey) && isset($_GET['api_key'])) {
    $providedKey = $_GET['api_key'];
}

// 3️⃣ Final fallback for localhost (Apache sometimes strips headers)
if (empty($providedKey) && isset($_SERVER['REDIRECT_HTTP_X_API_KEY'])) {
    $providedKey = $_SERVER['REDIRECT_HTTP_X_API_KEY'];
}

// Validate key
if ($providedKey !== $API_KEY) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized request'
    ]);
    exit;
}

// -------------------
// Database connection
// -------------------
require '../config.php'; // adjust path if api folder is inside hr3/

// -------------------
// Include Leave Service
// -------------------
require '../services/LeaveService.php';
$service = new LeaveService($conn);

// -------------------
// Determine action
// -------------------
$action = $_GET['action'] ?? 'list';

switch ($action) {

    // List leave requests
    case 'list':
        $leaves = $service->getLeaves();
        echo json_encode([
            'status' => 'success',
            'data' => $leaves
        ]);
        break;

    // View leave credits
    case 'credits':
        $employee_id = intval($_GET['employee_id'] ?? 0);
        if (!$employee_id) {
            echo json_encode(['status'=>'error','message'=>'Employee ID required']);
            exit;
        }
        $credits = $service->getLeaveCredits($employee_id);
        echo json_encode(['status'=>'success','data'=>$credits]);
        break;

    // Submit a new leave request
    case 'request':
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $leave_type  = $_POST['leave_type'] ?? '';
        $leave_date  = $_POST['leave_date'] ?? '';
        $reason      = $_POST['reason'] ?? '';

        if (!$employee_id || !$leave_type || !$leave_date) {
            echo json_encode(['status'=>'error','message'=>'Missing required fields']);
            exit;
        }

        $result = $service->submitLeave($employee_id, $leave_type, $leave_date, $reason);
        echo json_encode($result);
        break;

    // Approve or reject leave
    case 'validate':
        $request_id = intval($_POST['request_id'] ?? 0);
        $actionType = $_POST['action'] ?? ''; // approve or reject
        $remarks    = $_POST['remarks'] ?? null;

        if (!$request_id || !in_array($actionType, ['approve','reject'])) {
            echo json_encode(['status'=>'error','message'=>'Invalid request']);
            exit;
        }

        $validator = $_POST['validator'] ?? 'Admin';
        $result = $service->validateLeave($request_id, $actionType, $validator, $remarks);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['status'=>'error','message'=>'Invalid action']);
        break;
}
