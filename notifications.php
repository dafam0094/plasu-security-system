<?php
include 'includes/config.php';
include 'includes/auth.php';

$user_id = $_SESSION['user_id'];

// Get all notifications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$notifications = $conn->query("
    SELECT n.*, r.description as report_description, r.status as report_status 
    FROM notifications n 
    LEFT JOIN reports r ON n.report_id = r.id 
    WHERE n.user_id = $user_id 
    ORDER BY n.created_at DESC 
    LIMIT $offset, $limit
");

$total = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id")->fetch_assoc()['count'];
$total_pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - PLASU Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-shaded"></i> PLASU Security
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bell"></i> All Notifications</h5>
                <span class="badge bg-light text-dark">Total: <?= $total ?></span>
            </div>
            <div class="card-body">
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while($notif = $notifications->fetch_assoc()): ?>
                            <div class="list-group-item list-group-item-action <?= $notif['is_read'] == 0 ? 'list-group-item-primary' : '' ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($notif['message']) ?></h6>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($notif['created_at'])) ?></small>
                                </div>
                                <?php if (isset($notif['report_status'])): ?>
                                    <p class="mb-1">
                                        <small>Report Status: </small>
                                        <span class="badge bg-<?= 
                                            $notif['report_status'] == 'solved' ? 'success' : 
                                            ($notif['report_status'] == 'processing' ? 'info' : 'warning') 
                                        ?>">
                                            <?= ucfirst($notif['report_status']) ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                                <?php if ($notif['is_read'] == 0): ?>
                                    <small class="text-primary">New</small>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash display-1 text-muted mb-3"></i>
                        <h4>No notifications yet</h4>
                        <p class="text-muted">When you receive notifications, they'll appear here.</p>
                        <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>