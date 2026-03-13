<?php
include 'includes/config.php';
include 'includes/auth.php';

$user_id = $_SESSION['user_id'];

// Get unread count
$count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

header('Content-Type: application/json');
echo json_encode(['count' => (int)$count]);
?>