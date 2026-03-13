<?php
include 'includes/config.php';
include 'includes/auth.php';

if ($user['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Get all reports with user info
$reports = $conn->query("
    SELECT r.*, u.fullname, u.email 
    FROM reports r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC
");

// Get pending reports count
$pending_count = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PLASU Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-shaded"></i> PLASU Security Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bi bi-person-plus"></i> Add User
                        </a>
                    </li>
                        <li class="nav-item">
                <a class="nav-link" href="analytics.php">
                    <i class="bi bi-graph-up"></i> Analytics
                </a>
            </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="#" id="notificationBell">
                            <i class="bi bi-bell"></i>
                            <?php if ($pending_count > 0): ?>
                            <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $pending_count ?>
                            </span>
                            <?php endif; ?>
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
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <i class="bi bi-people float-end display-6"></i>
                        <h5 class="card-title">Total Users</h5>
                        <h2><?= $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-warning text-dark mb-4">
                    <div class="card-body">
                        <i class="bi bi-exclamation-triangle float-end display-6"></i>
                        <h5 class="card-title">Pending Reports</h5>
                        <h2><?= $pending_count ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-info text-white mb-4">
                    <div class="card-body">
                        <i class="bi bi-gear float-end display-6"></i>
                        <h5 class="card-title">Processing</h5>
                        <h2><?= $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'processing'")->fetch_assoc()['count'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-success text-white mb-4">
                    <div class="card-body">
                        <i class="bi bi-check-circle float-end display-6"></i>
                        <h5 class="card-title">Solved</h5>
                        <h2><?= $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'solved'")->fetch_assoc()['count'] ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> All Reports</h5>
                <span class="badge bg-primary"><?= $reports->num_rows ?> Total</span>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="reportsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Description</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $reports->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $row['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['fullname']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'solved' => 'success'
                                    ];
                                    $color = $status_colors[$row['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="update_status.php?id=<?= $row['id'] ?>&status=pending" class="btn btn-warning" title="Mark Pending">
                                            <i class="bi bi-hourglass-split"></i>
                                        </a>
                                        <a href="update_status.php?id=<?= $row['id'] ?>&status=processing" class="btn btn-info" title="Mark Processing">
                                            <i class="bi bi-gear"></i>
                                        </a>
                                        <a href="update_status.php?id=<?= $row['id'] ?>&status=solved" class="btn btn-success" title="Mark Solved">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                        <button class="btn btn-outline-primary" onclick="viewReport(<?= $row['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
                
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/notifications.js"></script>
    
    <script>
        function viewReport(id) {
            alert('View report #' + id + ' - Feature coming soon!');
        }
        
        // Auto refresh table every 30 seconds (optional)
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>