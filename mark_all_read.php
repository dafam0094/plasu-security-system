<?php
include 'includes/config.php';
include 'includes/auth.php';

$user_id = $_SESSION['user_id'];

// Mark all unread notifications as read
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>