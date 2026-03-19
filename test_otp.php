<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'yourgmail@gmail.com';       // <-- Replace with your Gmail
    $mail->Password = 'YOUR_APP_PASSWORD';         // <-- Replace with 16-character App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('yourgmail@gmail.com', 'HR3 System');
    $mail->addAddress('yourgmail@gmail.com');      // <-- Send to yourself for testing

    $mail->isHTML(true);
    $mail->Subject = 'Test OTP';
    $mail->Body    = '<h2>HR3 OTP Test</h2><p>Your OTP is: <b>123456</b></p>';

    $mail->send();
    echo "✅ OTP Email sent successfully!";
} catch (Exception $e) {
    echo "❌ Email failed: " . $mail->ErrorInfo;
}
