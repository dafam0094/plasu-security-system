<?php
include 'includes/config.php';
include 'includes/auth.php';

if ($user['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Get statistics
$total_reports = $conn->query("SELECT COUNT(*) as count FROM reports")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// Reports by status
$status_stats = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM reports 
    GROUP BY status
");

$status_data = [];
while ($row = $status_stats->fetch_assoc()) {
    $status_data[$row['status']] = $row['count'];
}

// Reports by emergency level
$emergency_stats = $conn->query("
    SELECT emergency_level, COUNT(*) as count 
    FROM reports 
    WHERE emergency_level IS NOT NULL
    GROUP BY emergency_level
");

// Monthly trend
$monthly_trend = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM reports
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
");

// Top reporting locations
$top_locations = $conn->query("
    SELECT address, COUNT(*) as count
    FROM reports
    GROUP BY address
    ORDER BY count DESC
    LIMIT 5
");

// Average response time (simulated)
$avg_response = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, r.created_at, u.updated_at)) as avg_hours
    FROM reports r
    JOIN report_updates u ON r.id = u.report_id
    WHERE u.new_status = 'solved'
")->fetch_assoc()['avg_hours'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - PLASU Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-shaded"></i> PLASU Security Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="analytics.php">
                            <i class="bi bi-graph-up"></i> Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4 px-4">
        <h2 class="mb-4"><i class="bi bi-graph-up"></i> Analytics Dashboard</h2>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Total Reports</h6>
                                <h2 class="mb-0"><?= $total_reports ?></h2>
                            </div>
                            <i class="bi bi-exclamation-triangle display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Total Users</h6>
                                <h2 class="mb-0"><?= $total_users ?></h2>
                            </div>
                            <i class="bi bi-people display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Pending Reports</h6>
                                <h2 class="mb-0"><?= $status_data['pending'] ?? 0 ?></h2>
                            </div>
                            <i class="bi bi-hourglass-split display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Avg Response</h6>
                                <h2 class="mb-0"><?= round($avg_response, 1) ?> hrs</h2>
                            </div>
                            <i class="bi bi-clock-history display-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Reports by Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Reports by Emergency Level</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="emergencyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trend and Top Locations -->
        <div class="row mb-4">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Monthly Report Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Top Reporting Locations</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php while($location = $top_locations->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars(substr($location['address'], 0, 30)) ?>...
                                <span class="badge bg-primary rounded-pill"><?= $location['count'] ?></span>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Report ID</th>
                                <th>Action</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $activities = $conn->query("
                                SELECT u.*, r.id as report_id, r.description, us.fullname
                                FROM report_updates u
                                JOIN reports r ON u.report_id = r.id
                                JOIN users us ON u.updated_by = us.id
                                ORDER BY u.updated_at DESC
                                LIMIT 10
                            ");
                            while($activity = $activities->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= date('M d, H:i', strtotime($activity['updated_at'])) ?></td>
                                <td>#<?= $activity['report_id'] ?></td>
                                <td>
                                    Status changed from 
                                    <span class="badge bg-secondary"><?= $activity['old_status'] ?></span>
                                    to
                                    <span class="badge bg-<?= 
                                        $activity['new_status'] == 'solved' ? 'success' : 
                                        ($activity['new_status'] == 'processing' ? 'info' : 'warning') 
                                    ?>"><?= $activity['new_status'] ?></span>
                                </td>
                                <td><?= htmlspecialchars($activity['fullname']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Processing', 'Solved'],
                datasets: [{
                    data: [
                        <?= $status_data['pending'] ?? 0 ?>,
                        <?= $status_data['processing'] ?? 0 ?>,
                        <?= $status_data['solved'] ?? 0 ?>
                    ],
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Emergency Level Chart
        <?php
        $emergency_labels = [];
        $emergency_counts = [];
        $emergency_stats->data_seek(0);
        while($emergency = $emergency_stats->fetch_assoc()):
            $emergency_labels[] = ucfirst($emergency['emergency_level']);
            $emergency_counts[] = $emergency['count'];
        endwhile;
        ?>
        
        new Chart(document.getElementById('emergencyChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($emergency_labels) ?>,
                datasets: [{
                    data: <?= json_encode($emergency_counts) ?>,
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Trend Chart
        <?php
        $months = [];
        $month_counts = [];
        $monthly_trend->data_seek(0);
        while($trend = $monthly_trend->fetch_assoc()):
            $months[] = $trend['month'];
            $month_counts[] = $trend['count'];
        endwhile;
        ?>
        
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse($months)) ?>,
                datasets: [{
                    label: 'Reports',
                    data: <?= json_encode(array_reverse($month_counts)) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>