<?php
include 'includes/config.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $description = $conn->real_escape_string($_POST['description']);
    $address = $conn->real_escape_string($_POST['address']);
    $latitude = $conn->real_escape_string($_POST['latitude']);
    $longitude = $conn->real_escape_string($_POST['longitude']);
    $emergency_level = $conn->real_escape_string($_POST['emergency_level']);
    $user_id = $_SESSION['user_id'];
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['report_image']) && $_FILES['report_image']['error'] == 0) {
        $target_dir = "uploads/reports/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['report_image']['name'], PATHINFO_EXTENSION);
        $image_path = $target_dir . time() . '_' . uniqid() . '.' . $file_extension;
        
        move_uploaded_file($_FILES['report_image']['tmp_name'], $image_path);
    }
    
    // Insert report
    $sql = "INSERT INTO reports (user_id, description, address, latitude, longitude, emergency_level, image) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issdsss", $user_id, $description, $address, $latitude, $longitude, $emergency_level, $image_path);
    
    if ($stmt->execute()) {
        $report_id = $stmt->insert_id;
        
        // Create notification for user
        $message = "Your report #$report_id has been submitted successfully";
        $conn->query("INSERT INTO notifications (user_id, report_id, message) VALUES ($user_id, $report_id, '$message')");
        
        // Send email to user
        sendEmailNotification($user['email'], "Report Submitted", "Your report #$report_id has been submitted. We'll notify you of any updates.");
        
        // Notify all admins
        $admins = $conn->query("SELECT id, email FROM users WHERE role = 'admin'");
        while ($admin = $admins->fetch_assoc()) {
            $admin_message = "New $emergency_level report #$report_id from " . $user['fullname'];
            $conn->query("INSERT INTO notifications (user_id, report_id, message) VALUES ({$admin['id']}, $report_id, '$admin_message')");
            
            // Send email to admin
            sendEmailNotification($admin['email'], "New Report Alert", "A new $emergency_level report has been submitted. Check admin dashboard for details.");
        }
        
        $_SESSION['success'] = "Report submitted successfully!";
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Error submitting report: " . $conn->error;
    }
}

// Email function
function sendEmailNotification($to, $subject, $message) {
    // Will implement with PHPMailer
    // For now, log it
    global $conn;
    $conn->query("INSERT INTO email_logs (recipient_email, subject, message) VALUES ('$to', '$subject', '$message')");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Problem - PLASU Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 4px;
            display: none;
        }
        .emergency-badge {
            padding: 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .emergency-normal { background-color: #28a745; color: white; }
        .emergency-urgent { background-color: #ffc107; color: black; }
        .emergency-emergency { background-color: #dc3545; color: white; animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Report Security Problem</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="reportForm">
                            <div class="mb-3">
                                <label for="description" class="form-label">Problem Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Location on Map <span class="text-danger">*</span></label>
                                <div id="map"></div>
                                <input type="hidden" name="latitude" id="latitude" required>
                                <input type="hidden" name="longitude" id="longitude" required>
                                <input type="text" class="form-control mt-2" id="address" name="address" placeholder="Selected address will appear here" readonly required>
                                <small class="text-muted">Click on the map to set the exact location</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="report_image" class="form-label">Attach Image (Optional)</label>
                                <input type="file" class="form-control" id="report_image" name="report_image" accept="image/*" onchange="previewImage(this)">
                                <img class="image-preview" id="imagePreview" src="#" alt="Preview">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Emergency Level <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="emergency_level" id="normal" value="normal" checked>
                                        <label class="form-check-label text-success" for="normal">
                                            <i class="bi bi-check-circle"></i> Normal
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="emergency_level" id="urgent" value="urgent">
                                        <label class="form-check-label text-warning" for="urgent">
                                            <i class="bi bi-exclamation-triangle"></i> Urgent
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="emergency_level" id="emergency" value="emergency">
                                        <label class="form-check-label text-danger" for="emergency">
                                            <i class="bi bi-exclamation-diamond"></i> Emergency
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="livePreview" class="mb-3 p-3 border rounded" style="display: none;">
                                <h6>Live Preview:</h6>
                                <p id="previewDescription"></p>
                                <span id="previewEmergency" class="emergency-badge"></span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-send"></i> Submit Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        let map = L.map('map').setView([9.2185, 9.5175], 15); // PLASU coordinates
        let marker;
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Add click event to map
        map.on('click', function(e) {
            let lat = e.latlng.lat;
            let lng = e.latlng.lng;
            
            // Update hidden inputs
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Update or create marker
            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                marker = L.marker(e.latlng).addTo(map);
            }
            
            // Reverse geocoding to get address
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('address').value = data.display_name;
                });
        });
        
        // Image preview
        function previewImage(input) {
            let preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Live preview
        document.getElementById('description').addEventListener('input', function() {
            document.getElementById('previewDescription').textContent = this.value;
            document.getElementById('livePreview').style.display = 'block';
        });
        
        document.querySelectorAll('input[name="emergency_level"]').forEach(radio => {
            radio.addEventListener('change', function() {
                let preview = document.getElementById('previewEmergency');
                preview.textContent = 'Emergency Level: ' + this.value.toUpperCase();
                preview.className = 'emergency-badge emergency-' + this.value;
                document.getElementById('livePreview').style.display = 'block';
            });
        });
        
        // Try to get user's location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                let userLat = position.coords.latitude;
                let userLng = position.coords.longitude;
                map.setView([userLat, userLng], 15);
            });
        }
    </script>
</body>
</html>