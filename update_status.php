<?php
include 'includes/config.php';
include 'includes/auth.php';

if ($user['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$id = (int)$_GET['id'];
$status = $conn->real_escape_string($_GET['status']);

// Get report details first
$report = $conn->query("SELECT * FROM reports WHERE id = $id")->fetch_assoc();

if ($report) {
    // Update status
    $conn->query("UPDATE reports SET status = '$status' WHERE id = $id");
    
    // Create notification for user
    $message = "Your report #$id status has been updated to: " . ucfirst($status);
    $conn->query("INSERT INTO notifications (user_id, report_id, message) VALUES ({$report['user_id']}, $id, '$message')");
    
    // If solved, add completion message
    if ($status == 'solved') {
        $message = "Report #$id has been resolved. Thank you for your patience.";
        $conn->query("INSERT INTO notifications (user_id, report_id, message) VALUES ({$report['user_id']}, $id, '$message')");
    }
}

header("Location: admin_dashboard.php");
?>