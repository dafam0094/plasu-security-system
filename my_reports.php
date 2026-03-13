<?php
include 'includes/config.php';
include 'includes/auth.php';

$user_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get user's reports with pagination
$reports = $conn->query("
    SELECT * FROM reports 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT $offset, $limit
");

// Get total count for pagination
$total = $conn->query("SELECT COUNT(*) as count FROM reports WHERE user_id = $user_id")->fetch_assoc()['count'];
$total_pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - PLASU Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .report-card {
            transition: transform 0.2s;
            margin-bottom: 15px;
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-shaded"></i> PLASU Security
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
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
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> All My Reports</h5>
            </div>
            <div class="card-body">
                <?php if ($reports->num_rows > 0): ?>
                    <div class="row">
                        <?php while($row = $reports->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="card-title">Report #<?= $row['id'] ?></h6>
                                            <span class="badge bg-<?= 
                                                $row['status'] == 'solved' ? 'success' : 
                                                ($row['status'] == 'processing' ? 'info' : 'warning') 
                                            ?>"><?= ucfirst($row['status']) ?></span>
                                        </div>
                                        
                                        <p class="card-text">
                                            <?= nl2br(htmlspecialchars(substr($row['description'], 0, 150))) ?>
                                            <?php if (strlen($row['description']) > 150): ?>...<?php endif; ?>
                                        </p>
                                        
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['address']) ?>
                                            </small>
                                        </p>
                                        
                                        <?php if (isset($row['emergency_level'])): ?>
                                            <span class="badge bg-<?= 
                                                $row['emergency_level'] == 'emergency' ? 'danger' : 
                                                ($row['emergency_level'] == 'urgent' ? 'warning' : 'success') 
                                            ?>"><?= strtoupper($row['emergency_level']) ?></span>
                                        <?php endif; ?>
                                        
                                        <p class="card-text mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> <?= date('F d, Y H:i', strtotime($row['created_at'])) ?>
                                            </small>
                                        </p>
                                        
                                        <?php if ($row['image']): ?>
                                            <img src="<?= $row['image'] ?>" class="img-fluid rounded mt-2" style="max-height: 100px;" alt="Report image">
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?= $row['id'] ?>)">
                                                <i class="bi bi-eye"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page-1 ?>">Previous</a>
                                </li>
                                
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page+1 ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                        <h4>No reports yet</h4>
                        <p class="text-muted">You haven't submitted any security reports.</p>
                        <a href="report_problem.php" class="btn btn-primary">Create Your First Report</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Report Details Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        function viewReport(id) {
            fetch('get_report_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('reportModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('reportModal')).show();
                });
        }
    </script>
</body>
</html>