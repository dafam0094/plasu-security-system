<?php
include 'includes/config.php';
include 'includes/auth.php';

$reports = $conn->query("
    SELECT r.*, u.fullname as reporter_name 
    FROM reports r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
    ORDER BY r.created_at DESC
");

$report_list = [];
while($report = $reports->fetch_assoc()) {
    $report_list[] = [
        'id' => $report['id'],
        'description' => substr($report['description'], 0, 100),
        'address' => $report['address'],
        'latitude' => $report['latitude'],
        'longitude' => $report['longitude'],
        'status' => $report['status'],
        'emergency_level' => $report['emergency_level'],
        'reporter_name' => $report['reporter_name']
    ];
}

header('Content-Type: application/json');
echo json_encode($report_list);
?>