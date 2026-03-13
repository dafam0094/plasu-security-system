<?php
include 'includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['notification_id'])) {
    exit();
}

$notification_id = $_POST['notification_id'];
$user_id = $_SESSION['user_id'];

$conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notification_id AND user_id = $user_id");
?>