<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function sendEmail($to, $subject, $body, $isHTML = true) {
    global $conn;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dafamabelnansak@gmail.com'; // Your email
        $mail->Password   = 'Dafam0094@'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@plasu-security.edu.ng', 'PLASU Security System');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        
        // Log success
        $conn->query("INSERT INTO email_logs (recipient_email, subject, message, status) 
                      VALUES ('$to', '$subject', '$body', 'sent')");
        return true;
        
    } catch (Exception $e) {
        // Log failure
        $conn->query("INSERT INTO email_logs (recipient_email, subject, message, status) 
                      VALUES ('$to', '$subject', '$body', 'failed')");
        return false;
    }
}

// Email templates
function getReportStatusEmail($report_id, $status, $user_name) {
    $status_colors = [
        'pending' => '#ffc107',
        'processing' => '#17a2b8',
        'solved' => '#28a745'
    ];
    
    $color = $status_colors[$status] ?? '#6c757d';
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #333;'>PLASU Security Alert System</h2>
            <p>Dear $user_name,</p>
            <p>Your report #$report_id status has been updated to:</p>
            <div style='background-color: $color; color: white; padding: 10px; border-radius: 5px; text-align: center; font-size: 18px;'>
                " . strtoupper($status) . "
            </div>
            <p>You can track your report status in your dashboard.</p>
            <p>Thank you for helping keep PLASU safe.</p>
            <hr>
            <small style='color: #777;'>This is an automated message. Please do not reply.</small>
        </div>
    </body>
    </html>
    ";
}