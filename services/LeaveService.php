<?php
class LeaveService {
    private $conn;

    public function __construct($pdo) {
        $this->conn = $pdo;
    }

    // -------------------
    // List all leave requests
    // -------------------
    public function getLeaves() {
        $sql = "SELECT lr.id, lr.employee_id, e.firstname, e.lastname, lr.leave_type, lr.leave_date, 
                       lr.reason, lr.status, lr.created_at, lr.validated_by, lr.validated_at, lr.reject_remarks
                FROM leave_requests lr
                LEFT JOIN employee e ON lr.employee_id = e.id
                WHERE lr.deleted_at IS NULL
                ORDER BY lr.created_at DESC
                LIMIT 50";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------
    // Get leave credits for a specific employee
    // -------------------
    public function getLeaveCredits($employee_id) {
        $stmt = $this->conn->prepare("SELECT leave_type, total_credits, used_credits FROM leave_credits WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------
    // Submit a new leave request
    // -------------------
    public function submitLeave($employee_id, $leave_type, $leave_date, $reason) {
        $stmt = $this->conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, leave_date, reason, status, created_at) 
                                      VALUES (?, ?, ?, ?, 'pending', NOW())");
        if ($stmt->execute([$employee_id, $leave_type, $leave_date, $reason])) {
            return ['status'=>'success','message'=>'Leave request submitted'];
        } else {
            return ['status'=>'error','message'=>'Failed to submit leave request'];
        }
    }

    // -------------------
    // Approve or reject a leave request
    // -------------------
    public function validateLeave($request_id, $action, $validator='Admin', $remarks=null) {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        $sql = "UPDATE leave_requests SET status = :status, validated_by = :validator, validated_at = NOW()";
        if ($remarks !== null) {
            $sql .= ", reject_remarks = :remarks";
        }
        $sql .= " WHERE id = :id";

        $params = ['status'=>$new_status,'validator'=>$validator,'id'=>$request_id];
        if ($remarks !== null) $params['remarks'] = $remarks;

        $stmt = $this->conn->prepare($sql);
        if ($stmt->execute($params)) {

            // If approved, update leave credits
            if ($new_status === 'approved') {
                $stmt2 = $this->conn->prepare("SELECT leave_type, used_credits, total_credits FROM leave_credits WHERE employee_id = (SELECT employee_id FROM leave_requests WHERE id=?) AND leave_type = (SELECT leave_type FROM leave_requests WHERE id=?)");
                $stmt2->execute([$request_id,$request_id]);
                $credit = $stmt2->fetch(PDO::FETCH_ASSOC);

                $stmt3 = null;
                if ($credit) {
                    $stmt3 = $this->conn->prepare("UPDATE leave_credits SET used_credits = used_credits + 1 WHERE employee_id = (SELECT employee_id FROM leave_requests WHERE id=?) AND leave_type = ?");
                    $stmt3->execute([$request_id,$credit['leave_type']]);
                } else {
                    $stmt3 = $this->conn->prepare("INSERT INTO leave_credits (employee_id, leave_type, total_credits, used_credits) VALUES ((SELECT employee_id FROM leave_requests WHERE id=?), (SELECT leave_type FROM leave_requests WHERE id=?), 0, 1)");
                    $stmt3->execute([$request_id, $request_id]);
                }
            }

            return ['status'=>'success','message'=>"Leave $new_status"];
        } else {
            return ['status'=>'error','message'=>"Failed to $action leave"];
        }
    }
}
