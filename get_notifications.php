<?php
include 'includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get unread notifications
$query = "SELECT n.*, r.description, r.address, r.status 
          FROM notifications n 
          JOIN reports r ON n.report_id = r.id 
          WHERE n.user_id = $user_id AND n.is_read = 0 
          ORDER BY n.created_at DESC 
          LIMIT 10";

$result = $conn->query($query);
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    'count' => count($notifications),
    'notifications' => $notifications
]);
?>