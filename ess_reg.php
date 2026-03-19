<?php
session_start();
require 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';

$validJobTitles = ['Manager', 'Warehouse Staff', 'Delivery Driver', 'Stock Controller', 'Supplier Helper'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname   = trim($_POST['firstname'] ?? '');
    $lastname    = trim($_POST['lastname'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phonenumber = trim($_POST['phonenumber'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $jobTitle    = $_POST['job_title'] ?? '';
    $terms       = isset($_POST['terms']);

    if (!$firstname || !$lastname || !$username || !$email || !$password || !$confirmPassword || !$jobTitle) {
        $message = "Please fill in all fields and select a job title.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
    } elseif (!in_array($jobTitle, $validJobTitles)) {
        $message = "Invalid job title selected.";
    } elseif (!$terms) {
        $message = "You must agree to the Terms of Service.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id FROM employee WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetch()) {
                $message = "Username or Email already exists.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO employee (firstname, lastname, username, email, phonenumber, password, job_title)
                    VALUES (:firstname, :lastname, :username, :email, :phonenumber, :password, :job_title)
                ");
                $stmt->execute([
                    'firstname'   => $firstname,
                    'lastname'    => $lastname,
                    'username'    => $username,
                    'email'       => $email,
                    'phonenumber' => $phonenumber,
                    'password'    => password_hash($password, PASSWORD_DEFAULT),
                    'job_title'   => $jobTitle
                ]);

                $employee_id = $conn->lastInsertId();
                $leaves = ['Sick Leave' => 10, 'Vacation Leave' => 10];
                $stmt = $conn->prepare("INSERT INTO leave_credits (employee_id, leave_type, total_credits, used_credits) VALUES (:employee_id, :leave_type, :total_credits, 0)");
                foreach ($leaves as $type => $credits) {
                    $stmt->execute([
                        'employee_id'   => $employee_id,
                        'leave_type'    => $type,
                        'total_credits' => $credits
                    ]);
                }

                $message = "✅ Registration successful! You can now <a href='ess_test_login.php'>login</a>.";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Registration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #d7ecff, #b8dcf4);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .registration-container {
            background: #ffffff;
            padding: 35px 40px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 480px;
            animation: fadeIn 0.8s ease;
        }

        h2 {
            text-align: center;
            color: #2c5978;
            margin-bottom: 25px;
            letter-spacing: 1px;
        }

        label {
            font-weight: 600;
            color: #2b4e6f;
            margin-top: 12px;
            display: block;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #a8cbe8;
            border-radius: 8px;
            font-size: 14px;
            background-color: #f4faff;
            transition: 0.3s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #66a3d2;
            box-shadow: 0 0 5px rgba(102, 163, 210, 0.5);
        }

        button {
            width: 100%;
            margin-top: 18px;
            padding: 12px;
            background: #66b3ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #559de6;
        }

        .message {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
        }

        .error {
            background: #ffe5e5;
            color: #b30000;
        }

        .success {
            background: #e3f8e3;
            color: #2d7a2d;
        }

        .terms {
            font-size: 13px;
            margin-top: 10px;
            color: #345d7e;
        }

        .terms a {
            color: #2d7bba;
            text-decoration: none;
            cursor: pointer;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        /* === Modal Popup === */
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh; /* 👈 keeps it inside viewport */
            overflow-y: auto; /* 👈 enables scroll */
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: fadeIn 0.4s ease;
        }

        /* === Custom Scrollbar === */
        .modal-content::-webkit-scrollbar {
            width: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #9ec8f2;
            border-radius: 8px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #78b3ec;
        }

        .modal h3, h1, h2 {
            color: #2c5978;
        }

        .close-btn {
            float: right;
            color: #888;
            font-size: 20px;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #000;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <h2>Employee Registration</h2>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'successful') !== false ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="firstname">First Name:</label>
            <input type="text" id="firstname" name="firstname" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>

            <label for="lastname">Last Name:</label>
            <input type="text" id="lastname" name="lastname" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>

            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <label for="phonenumber">Phone Number:</label>
            <input type="text" id="phonenumber" name="phonenumber" value="<?= htmlspecialchars($_POST['phonenumber'] ?? '') ?>">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <label for="job_title">Job Title:</label>
            <select id="job_title" name="job_title" required>
                <option value="">-- Select Job Title --</option>
                <?php foreach ($validJobTitles as $r): ?>
                    <option value="<?= $r ?>" <?= (isset($_POST['job_title']) && $_POST['job_title']==$r) ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
            </select>

            <div class="terms">
                <label>
                    <input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
                    I agree to the <a id="termsLink">Terms of Service</a>.
                </label>
            </div>

            <button type="submit">Register</button>
        </form>
    </div>

    <!-- ===== Terms Modal ===== -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeModal">&times;</span>
            <!-- existing long Terms of Service here -->
            <h1>Terms of Service</h1>
    <p class="effective-date">Effective Date: [July, 2025] | Last Updated: [April, 2025]</p>

    <h2>1. Introduction</h2>
    <p>This Data Privacy and Policy outlines how <strong>iMarket</strong> collects, uses, stores, and protects personal data of its users. We are committed to safeguarding the privacy of employees, job applicants, and system users in compliance with the Philippine Data Privacy Act of 2012 (RA 10173) and other applicable regulations.</p>

    <h2>2. Data We Collect</h2>
    <ul>
        <li><strong>Personal Identification:</strong> Full name, date of birth, gender, address, contact details.</li>
        <li><strong>Employment Data:</strong> Job title, employment status, work schedule, performance records.</li>
        <li><strong>Payroll and Benefits:</strong> Bank account details, salary information, tax records, benefits enrollment.</li>
        <li><strong>Attendance and Scheduling:</strong> Shift assignments, time logs, leave records.</li>
        <li><strong>System Access Information:</strong> Login credentials, activity logs, IP address.</li>
    </ul>

    <h2>3. Purpose of Data Collection</h2>
    <ul>
        <li>Managing employee records and HR operations.</li>
        <li>Processing payroll, benefits, and tax compliance.</li>
        <li>Workforce scheduling and performance tracking.</li>
        <li>Ensuring secure system access and activity monitoring.</li>
        <li>Complying with legal and regulatory requirements.</li>
    </ul>

    <h2>4. Data Usage and Sharing</h2>
    <ul>
        <li>Data will only be used for legitimate HR and operational purposes.</li>
        <li>Personal information will not be sold, rented, or shared with unauthorized third parties.</li>
        <li>Data may be shared with government agencies (e.g., BIR, SSS, PhilHealth, Pag-IBIG) as required by law.</li>
        <li>Third-party service providers (e.g., payroll processors, IT security providers) are bound by confidentiality agreements.</li>
    </ul>

    <h2>5. Data Storage and Security</h2>
    <ul>
        <li>All data is stored in secure, encrypted databases.</li>
        <li>Access is restricted to authorized HR personnel and system administrators.</li>
        <li>Security measures include firewalls, SSL encryption, multi-factor authentication, and regular system audits.</li>
    </ul>

    <h2>6. User Rights</h2>
    <ul>
        <li>Access their personal data.</li>
        <li>Request corrections to inaccurate or outdated information.</li>
        <li>Withdraw consent for data processing (where applicable).</li>
        <li>Request deletion of personal data (subject to legal retention requirements).</li>
    </ul>

    <h2>7. Data Retention</h2>
    <ul>
        <li>Employee records are retained for five (5) years after separation, unless required longer by law.</li>
        <li>System logs and backups are stored securely for operational and security purposes.</li>
    </ul>

    <h2>8. Policy Updates</h2>
    <p>We may update this policy from time to time to comply with new laws or system changes. Users will be notified of any significant updates.</p>

    <h2>9. Contact Information</h2>
    <p>For questions or concerns about this policy, please contact:</p>
    <p><strong>Data Protection Officer (DPO)</strong><br>
    Email: <a href="mailto:info@imarket.com">jessieloucarnesil13@gmail.com</a><br>
    <a href="mailto:info@imarket.com">erickgalo02@gmail.com.com</a><br>
    Phone: [09070071901] [09938573763]</p>
    
            <!-- etc... -->
        </div>
    </div>

    <script>
        const modal = document.getElementById("termsModal");
        const openBtn = document.getElementById("termsLink");
        const closeBtn = document.getElementById("closeModal");

        openBtn.addEventListener("click", () => modal.style.display = "flex");
        closeBtn.addEventListener("click", () => modal.style.display = "none");
        window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; };
    </script>
</body>
</html>
