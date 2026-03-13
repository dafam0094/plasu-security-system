<?php
include 'includes/config.php';
include 'includes/auth.php';

$id = (int)$_GET['id'];

$report = $conn->query("
    SELECT r.*, u.fullname, u.email 
    FROM reports r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.id = $id
")->fetch_assoc();

if ($report):
?>
<div class="container">
    <div class="row">
        <div class="col-md-6">
            <h6>Description</h6>
            <p><?= nl2br(htmlspecialchars($report['description'])) ?></p>
            
            <h6>Location</h6>
            <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($report['address']) ?></p>
            
            <h6>Reported by</h6>
            <p><?= htmlspecialchars($report['fullname']) ?> (<?= htmlspecialchars($report['email']) ?>)</p>
            
            <h6>Status</h6>
            <span class="badge bg-<?= 
                $report['status'] == 'solved' ? 'success' : 
                ($report['status'] == 'processing' ? 'info' : 'warning') 
            ?>"><?= ucfirst($report['status'])?></span>
            
            <?php if (isset($report['emergency_level'])): ?>
                <h6 class="mt-3">Emergency Level</h6>
                <span class="badge bg-<?= 
                    $report['emergency_level'] == 'emergency' ? 'danger' : 
                    ($report['emergency_level'] == 'urgent' ? 'warning' : 'success') 
                ?>"><?= strtoupper($report['emergency_level'])?></span>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <?php if ($report['image']): ?>
                <h6>Attached Image</h6>
                <img src="<?= $report['image'] ?>" class="img-fluid rounded" alt="Report image">
            <?php endif; ?>
            
            <?php if ($report['latitude'] && $report['longitude']): ?>
                <h6 class="mt-3">Location Map</h6>
                <div id="detailMap" style="height: 200px;"></div>
                <script>
                    let detailMap = L.map('detailMap').setView([<?= $report['latitude'] ?>, <?= $report['longitude'] ?>], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(detailMap);
                    L.marker([<?= $report['latitude'] ?>, <?= $report['longitude'] ?>]).addTo(detailMap);
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<p class="text-danger">Report not found</p>
<?php endif; ?>