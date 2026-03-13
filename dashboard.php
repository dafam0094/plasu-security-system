<?php
include 'includes/config.php';
include 'includes/auth.php'; // Check login

$user_id = $_SESSION['user_id'];

// Get user's own reports
$my_reports = $conn->query("SELECT * FROM reports WHERE user_id = $user_id ORDER BY created_at DESC");

// Get ALL reports from other users (community feed) - excluding own reports
$community_reports = $conn->query("
    SELECT r.*, u.fullname as reporter_name 
    FROM reports r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.user_id != $user_id 
    ORDER BY r.created_at DESC 
    LIMIT 20
");

// Get unread notification count
$notif_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

// Get recent notifications (last 10)
$notifications = $conn->query("
    SELECT n.*, r.description as report_description, r.status as report_status 
    FROM notifications n 
    LEFT JOIN reports r ON n.report_id = r.id 
    WHERE n.user_id = $user_id 
    ORDER BY n.created_at DESC 
    LIMIT 10
");

// Get total active reports count
$active_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status != 'solved'")->fetch_assoc()['count'];

// Get reports near user (based on location - simplified)
$nearby_reports = $conn->query("
    SELECT r.*, u.fullname as reporter_name 
    FROM reports r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.user_id != $user_id AND r.status != 'solved'
    ORDER BY r.created_at DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Dashboard - PLASU Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .notification-dropdown {
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            padding: 0;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
            cursor: pointer;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            background-color: #e8f4fd;
        }
        .notification-item.unread:hover {
            background-color: #d1e9fa;
        }
        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .notification-message {
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        .notification-status {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            display: inline-block;
        }
        .notification-header {
            background: #343a40;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .mark-all-read {
            font-size: 0.8rem;
            color: #0d6efd;
            text-decoration: none;
            cursor: pointer;
        }
        .mark-all-read:hover {
            text-decoration: underline;
        }
        .empty-notifications {
            padding: 30px;
            text-align: center;
            color: #6c757d;
        }
        #notificationBell {
            position: relative;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: -5px;
            font-size: 0.6rem;
            padding: 2px 5px;
        }
        
        /* Community Feed Styles */
        .community-alert {
            border-left: 4px solid;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        .community-alert:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .alert-emergency {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .alert-urgent {
            border-left-color: #ffc107;
            background-color: #fff9e6;
        }
        .alert-normal {
            border-left-color: #28a745;
            background-color: #f0fff4;
        }
        .emergency-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .badge-emergency { background: #dc3545; color: white; animation: pulse 2s infinite; }
        .badge-urgent { background: #ffc107; color: black; }
        .badge-normal { background: #28a745; color: white; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .reporter-name {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .location-link {
            color: #0d6efd;
            text-decoration: none;
            cursor: pointer;
        }
        .location-link:hover {
            text-decoration: underline;
        }
        #communityMap {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report_problem.php">
                            <i class="bi bi-exclamation-triangle"></i> Report
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php if ($notif_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?= $notif_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                            <div class="notification-header d-flex justify-content-between align-items-center">
                                <span>Notifications</span>
                                <?php if ($notif_count > 0): ?>
                                    <a class="mark-all-read" onclick="markAllAsRead()">Mark all as read</a>
                                <?php endif; ?>
                            </div>
                            
                            <div id="notificationList">
                                <?php if ($notifications->num_rows > 0): ?>
                                    <?php while($notif = $notifications->fetch_assoc()): ?>
                                        <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" 
                                             onclick="markAsRead(<?= $notif['id'] ?>)"
                                             data-notification-id="<?= $notif['id'] ?>">
                                            <div class="d-flex justify-content-between">
                                                <span class="notification-message">
                                                    <?= htmlspecialchars($notif['message']) ?>
                                                </span>
                                                <?php if (isset($notif['report_status'])): ?>
                                                    <span class="notification-status badge bg-<?= 
                                                        $notif['report_status'] == 'solved' ? 'success' : 
                                                        ($notif['report_status'] == 'processing' ? 'info' : 'warning') 
                                                    ?>">
                                                        <?= ucfirst($notif['report_status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <small class="notification-time">
                                                    <i class="bi bi-clock"></i> 
                                                    <?= date('M d, H:i', strtotime($notif['created_at'])) ?>
                                                </small>
                                                <?php if ($notif['is_read'] == 0): ?>
                                                    <span class="badge bg-primary" style="font-size: 0.6rem;">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-notifications">
                                        <i class="bi bi-bell-slash display-6 d-block mb-2"></i>
                                        <p>No notifications yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-center p-2 border-top">
                                <a href="notifications.php" class="text-decoration-none small">
                                    View all notifications
                                </a>
                            </div>
                        </div>
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
        <!-- Welcome Card with Stats -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4>Welcome back, <?= htmlspecialchars($user['fullname']) ?>!</h4>
                                <p class="mb-0">Stay informed about security incidents around campus.</p>
                            </div>
                            <div class="col-md-6">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h3><?= $active_reports ?></h3>
                                        <small>Active Incidents</small>
                                    </div>
                                    <div class="col-4">
                                        <h3><?= $my_reports->num_rows ?></h3>
                                        <small>Your Reports</small>
                                    </div>
                                    <div class="col-4">
                                        <h3><?= $community_reports->num_rows ?></h3>
                                        <small>Community Alerts</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-plus-circle text-success display-4"></i>
                        <h5 class="mt-3">New Report</h5>
                        <a href="report_problem.php" class="btn btn-success btn-sm">
                            <i class="bi bi-plus"></i> Create Report
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-map text-primary display-4"></i>
                        <h5 class="mt-3">Live Map</h5>
                        <button class="btn btn-primary btn-sm" onclick="showMap()">
                            <i class="bi bi-geo-alt"></i> View Map
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-bell text-warning display-4"></i>
                        <h5 class="mt-3">Alerts</h5>
                        <span class="badge bg-danger"><?= $notif_count ?> New</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-people text-info display-4"></i>
                        <h5 class="mt-3">Community</h5>
                        <span class="badge bg-info"><?= $community_reports->num_rows ?> Active</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Section (Hidden by default) -->
        <div id="mapSection" class="row mb-4" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-map"></i> Live Incident Map</h5>
                        <button class="btn btn-sm btn-light" onclick="hideMap()">Hide Map</button>
                    </div>
                    <div class="card-body">
                        <div id="communityMap"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two-Column Layout -->
        <div class="row">
            <!-- Left Column - Community Feed -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Community Security Alerts</h5>
                        <span class="badge bg-danger">LIVE</span>
                    </div>
                    <div class="card-body">
                        <?php if ($community_reports->num_rows > 0): ?>
                            <div class="row">
                                <?php while($report = $community_reports->fetch_assoc()): 
                                    $emergency_class = '';
                                    $badge_class = 'badge-normal';
                                    
                                    if (isset($report['emergency_level'])) {
                                        if ($report['emergency_level'] == 'emergency') {
                                            $emergency_class = 'alert-emergency';
                                            $badge_class = 'badge-emergency';
                                        } elseif ($report['emergency_level'] == 'urgent') {
                                            $emergency_class = 'alert-urgent';
                                            $badge_class = 'badge-urgent';
                                        } else {
                                            $emergency_class = 'alert-normal';
                                            $badge_class = 'badge-normal';
                                        }
                                    }
                                ?>
                                <div class="col-md-6">
                                    <div class="card community-alert <?= $emergency_class ?> mb-3">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="card-title">
                                                    <i class="bi bi-shield-exclamation"></i>
                                                    Incident Report
                                                </h6>
                                                <?php if (isset($report['emergency_level'])): ?>
                                                    <span class="emergency-badge <?= $badge_class ?>">
                                                        <?= strtoupper($report['emergency_level']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="card-text mt-2">
                                                <?= htmlspecialchars(substr($report['description'], 0, 100)) ?>...
                                            </p>
                                            
                                            <div class="reporter-name mb-2">
                                                <i class="bi bi-person"></i> Reported by: <?= htmlspecialchars($report['reporter_name']) ?>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-geo-alt"></i>
                                                    <a href="#" class="location-link" onclick="showOnMap(<?= $report['latitude'] ?>, <?= $report['longitude'] ?>, '<?= htmlspecialchars($report['address']) ?>')">
                                                        View Location
                                                    </a>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('M d, H:i', strtotime($report['created_at'])) ?>
                                                </small>
                                            </div>
                                            
                                            <?php if ($report['image']): ?>
                                                <div class="mt-2">
                                                    <img src="<?= $report['image'] ?>" class="img-fluid rounded" style="max-height: 100px;" alt="Report image">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <span class="badge bg-<?= 
                                                    $report['status'] == 'solved' ? 'success' : 
                                                    ($report['status'] == 'processing' ? 'info' : 'warning') 
                                                ?>">
                                                    <?= ucfirst($report['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-shield-check display-1 text-muted mb-3"></i>
                                <h5>No active community alerts</h5>
                                <p class="text-muted">The campus is currently quiet. Stay alert!</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($community_reports->num_rows >= 20): ?>
                            <div class="text-center mt-3">
                                <a href="community_feed.php" class="btn btn-outline-primary">View All Alerts</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Your Reports & Nearby -->
            <div class="col-md-4">
                <!-- Nearby Alerts -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-geo-alt-fill"></i> Near You</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($nearby_reports->num_rows > 0): ?>
                            <?php while($nearby = $nearby_reports->fetch_assoc()): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <p class="mb-1">
                                        <strong><?= htmlspecialchars(substr($nearby['description'], 0, 50)) ?>...</strong>
                                    </p>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars(substr($nearby['address'], 0, 30)) ?>...
                                    </small>
                                    <div class="mt-1">
                                        <span class="badge bg-<?= 
                                            $nearby['status'] == 'solved' ? 'success' : 
                                            ($nearby['status'] == 'processing' ? 'info' : 'warning') 
                                        ?>"><?= ucfirst($nearby['status'])?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">No incidents reported near you.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Your Reports -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Your Reports</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($my_reports->num_rows > 0): ?>
                            <?php $my_reports->data_seek(0); while($row = $my_reports->fetch_assoc()): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <p class="mb-1">
                                        <strong><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</strong>
                                    </p>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars(substr($row['address'], 0, 30)) ?>...
                                    </small>
                                    <div class="mt-1">
                                        <span class="badge bg-<?= 
                                            $row['status'] == 'solved' ? 'success' : 
                                            ($row['status'] == 'processing' ? 'info' : 'warning') 
                                        ?>"><?= ucfirst($row['status'])?></span>
                                        <small class="text-muted float-end"><?= date('M d', strtotime($row['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <div class="text-center mt-2">
                                <a href="my_reports.php" class="btn btn-sm btn-outline-info">View All</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">You haven't reported any incidents yet.</p>
                            <a href="report_problem.php" class="btn btn-success btn-sm">Report Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div id="toast-container"></div>

    <!-- Report Details Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Incident Details</h5>
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
    <script src="assets/js/notifications.js"></script>
    
    <script>
        let map;
        let markers = [];
        
        function viewReport(id) {
            // Fetch report details via AJAX
            fetch('get_report_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('reportModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('reportModal')).show();
                });
        }
        
        function showMap() {
            document.getElementById('mapSection').style.display = 'block';
            if (!map) {
                initMap();
            }
        }
        
        function hideMap() {
            document.getElementById('mapSection').style.display = 'none';
        }
        
        function initMap() {
            // Default to PLASU campus
            map = L.map('communityMap').setView([9.2185, 9.5175], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Load all reports on map
            loadReportsOnMap();
        }
        
        function loadReportsOnMap() {
            fetch('get_all_reports.php')
                .then(response => response.json())
                .then(reports => {
                    reports.forEach(report => {
                        if (report.latitude && report.longitude) {
                            let marker = L.marker([report.latitude, report.longitude]).addTo(map);
                            
                            let popupContent = `
                                <b>${report.description}</b><br>
                                <small>${report.address}</small><br>
                                <span class="badge bg-${report.status == 'solved' ? 'success' : (report.status == 'processing' ? 'info' : 'warning')}">
                                    ${report.status}
                                </span>
                                <br>
                                <small>Reported by: ${report.reporter_name}</small>
                            `;
                            
                            marker.bindPopup(popupContent);
                            markers.push(marker);
                        }
                    });
                });
        }
        
        function showOnMap(lat, lng, address) {
            showMap();
            if (!map) {
                initMap();
            }
            map.setView([lat, lng], 18);
            
            // Highlight marker
            markers.forEach(marker => {
                let latlng = marker.getLatLng();
                if (Math.abs(latlng.lat - lat) < 0.0001 && Math.abs(latlng.lng - lng) < 0.0001) {
                    marker.openPopup();
                }
            });
        }
        
        // Mark single notification as read
        function markAsRead(notificationId) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => {
                let notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('unread');
                    let newBadge = notificationItem.querySelector('.badge.bg-primary');
                    if (newBadge) {
                        newBadge.remove();
                    }
                }
                updateNotificationCount();
            });
        }
        
        // Mark all notifications as read
        function markAllAsRead() {
            fetch('mark_all_read.php', { method: 'POST' })
            .then(response => {
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('unread');
                    let newBadge = item.querySelector('.badge.bg-primary');
                    if (newBadge) newBadge.remove();
                });
                updateNotificationCount();
            });
        }
        
        // Update notification count
        function updateNotificationCount() {
            fetch('get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                let badge = document.querySelector('.notification-badge');
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        let bell = document.querySelector('#notificationDropdown');
                        if (bell) {
                            let newBadge = document.createElement('span');
                            newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge';
                            newBadge.textContent = data.count;
                            bell.appendChild(newBadge);
                        }
                    }
                } else if (badge) {
                    badge.remove();
                }
            });
        }
        
        // Auto-refresh every 60 seconds for new reports
        setInterval(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>