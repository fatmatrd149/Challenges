<?php 
if(session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../config.php'; 
require_once __DIR__ . '/../../Models/Rewards.php';
require_once __DIR__ . '/../../Models/Points.php';

$teacherID = $_SESSION['userID'] ?? 2;
$rewards = Rewards::getAll($pdo);  

$pendingRequests = Rewards::getPendingRequests($pdo);
$redemptionStats = Rewards::getRedemptionStats($pdo, date('Y-m-01'), date('Y-m-t'));
$popularRewards = Rewards::getPopularRewards($pdo, 5);
$lowStockRewards = Rewards::getLowStockRewards($pdo, 5); // FIXED: Changed from 10 to 5 to match admin

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_actions,
           SUM(CASE WHEN action_type = 'approve_reward' THEN 1 ELSE 0 END) as approvals,
           SUM(CASE WHEN action_type = 'reject_reward' THEN 1 ELSE 0 END) as rejections
    FROM teacher_actions 
    WHERE teacher_id = ?
");
$stmt->execute([$teacherID]);
$teacherStats = $stmt->fetch(PDO::FETCH_ASSOC);

$editReward = null;
if(isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    if($editId > 0) {
        $editReward = Rewards::getByID($pdo, $editId);
    }
}

$showCreateForm = isset($_GET['create']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reward Management - Teacher</title>
<link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body { 
    background: #f8fafc; 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    min-height: 100vh;
}
.container { max-width: 1400px; }
.dashboard-header { 
    background: #2563eb;
    border-radius: 12px; 
    padding: 30px; 
    margin-bottom: 30px; 
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    color: white;
}
.stats-card { 
    background: white; 
    border-radius: 12px; 
    padding: 25px; 
    text-align: center; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
    border-left: 5px solid #2563eb;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 100%;
}
.stats-card:hover { 
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}
.reward-card { 
    background: white; 
    border-radius: 12px; 
    padding: 24px; 
    margin-bottom: 20px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
    border-left: 5px solid #059669;
    transition: all 0.3s ease;
    height: 100%;
}
.reward-card:hover { 
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.12);
}
.request-card {
    border-left: 5px solid #f59e0b;
}
.analytics-card {
    border-left: 5px solid #8b5cf6;
}
.create-btn, .edit-btn { 
    background: #2563eb;
    color: white; 
    border: none; 
    border-radius: 8px; 
    padding: 10px 22px; 
    font-weight: 600; 
    transition: all 0.3s ease;
}
.create-btn:hover, .edit-btn:hover { 
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}
.delete-btn { 
    background: #dc2626;
    color: white; 
    border: none; 
    border-radius: 8px; 
    padding: 10px 22px; 
    font-weight: 600; 
    transition: all 0.3s ease;
}
.delete-btn:hover { 
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
}
.approve-btn {
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.approve-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}
.reject-btn {
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.reject-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}
.badge-status { 
    padding: 6px 12px; 
    border-radius: 20px; 
    font-size: 0.8rem; 
    font-weight: 600; 
}
.badge-active { 
    background: #d1fae5;
    color: #065f46; 
}
.badge-inactive { 
    background: #e5e7eb;
    color: #6b7280; 
}
.points-badge { 
    background: #fcd34d;
    color: #92400e; 
    padding: 8px 16px; 
    border-radius: 20px; 
    font-weight: 700; 
    font-size: 0.9rem; 
}
.form-modal {
    display: <?= ($editReward || $showCreateForm) ? 'block' : 'none' ?>; 
    position: fixed; 
    top: 0; left: 0; 
    width: 100%; height: 100%; 
    background: rgba(0,0,0,0.6); 
    z-index: 9999;
    backdrop-filter: blur(5px);
}
.form-container {
    position: fixed; 
    top: 50%; left: 50%; 
    transform: translate(-50%, -50%);
    background: white; 
    padding: 40px; 
    border-radius: 16px; 
    max-width: 600px; 
    width: 95%;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
}
.form-title { 
    color: #1f2937; 
    margin-bottom: 30px; 
    font-size: 1.6rem; 
    font-weight: 700; 
    text-align: center;
}
.form-control:focus {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    border-color: #2563eb;
    outline: none;
}
label {
    font-weight: 600;
    color: #374151;
}
.category-badge {
    background: #c7d2fe;
    color: #3730a3;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.alert-message {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    padding: 15px 25px;
    border-radius: 12px;
    font-weight: bold;
    font-size: 1rem;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.alert-success {
    background: #059669;
    color: white;
    border-left: 5px solid #047857;
}
.alert-error {
    background: #dc2626;
    color: white;
    border-left: 5px solid #b91c1c;
}
.section-header {
    background: #f3f4f6;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 5px solid #2563eb;
}
.analytics-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.analytics-table th, .analytics-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.analytics-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}
.request-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.student-points {
    background: #e0e7ff;
    color: #3730a3;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}
/* Add validation styles */
.required-field::after {
    content: " *";
    color: #dc2626;
}
.error-message {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 4px;
    display: none;
}
</style>
</head>
<body>

<!-- Success/Error Messages -->
<?php if(isset($_SESSION['success'])): ?>
    <div class="alert-message alert-success">
        <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
        <?php unset($_SESSION['success']); ?>
    </div>
    <script>
        setTimeout(() => {
            const alert = document.querySelector('.alert-message');
            if(alert) alert.style.display = 'none';
        }, 5000);
    </script>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert-message alert-error">
        <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
        <?php unset($_SESSION['error']); ?>
    </div>
    <script>
        setTimeout(() => {
            const alert = document.querySelector('.alert-message');
            if(alert) alert.style.display = 'none';
        }, 5000);
    </script>
<?php endif; ?>

<main class="container py-5">

    <div class="dashboard-header text-center">
        <h1 class="mb-3">
            <i class="fas fa-chalkboard-teacher me-3"></i>Reward Management - Teacher
        </h1>
        <p class="mb-0 opacity-90">Teacher panel for managing student rewards and approvals</p>
    </div>

    <!-- Quick Stats Section -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card" onclick="window.location.href='#pending-requests'">
                <i class="fas fa-clock mb-3" style="font-size: 2.5rem; color: #f59e0b;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= count($pendingRequests) ?>
                </div>
                <small class="text-muted">Pending Requests</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-check-circle mb-3" style="font-size: 2.5rem; color: #10b981;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= $teacherStats['approvals'] ?? 0 ?>
                </div>
                <small class="text-muted">Approved This Month</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card" onclick="window.location.href='#rewards-section'">
                <i class="fas fa-gift mb-3" style="font-size: 2.5rem; color: #2563eb;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= count($rewards) ?>
                </div>
                <small class="text-muted">Total Rewards</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card" onclick="window.location.href='#low-stock'">
                <i class="fas fa-exclamation-triangle mb-3" style="font-size: 2.5rem; color: #ef4444;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= count($lowStockRewards) ?>
                </div>
                <small class="text-muted">Low Stock Items</small>
            </div>
        </div>
    </div>

    <!-- Pending Requests Section -->
    <div id="pending-requests" class="section-header">
        <h3 class="mb-0">
            <i class="fas fa-clock me-2" style="color: #f59e0b;"></i>
            Pending Approval Requests
            <?php if(count($pendingRequests) > 0): ?>
                <span class="badge bg-warning ms-2"><?= count($pendingRequests) ?></span>
            <?php endif; ?>
        </h3>
    </div>

    <?php if(empty($pendingRequests)): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            No pending approval requests at the moment.
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach($pendingRequests as $request): ?>
                <div class="col-lg-6">
                    <div class="reward-card request-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="fw-bold mb-0" style="color: #111827;">
                                <?= htmlspecialchars($request['reward_title']) ?>
                            </h5>
                            <div class="d-flex flex-column align-items-end">
                                <span class="points-badge mb-1"><?= $request['reward_cost'] ?> pts</span>
                                <span class="student-points mt-1">
                                    Student has: <?= $request['student_points'] ?> pts
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Student:</strong> <?= htmlspecialchars($request['student_name']) ?>
                            <?php if($request['student_points'] < $request['reward_cost']): ?>
                                <span class="badge bg-danger ms-2">
                                    Needs <?= $request['reward_cost'] - $request['student_points'] ?> more points
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2">Has enough points</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($request['student_message']): ?>
                            <div class="alert alert-light mb-3">
                                <strong>Student's Message:</strong>
                                <p class="mb-0"><?= htmlspecialchars($request['student_message']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-secondary mb-3">
                            <strong>Category:</strong> <?= $request['reward_category'] ?><br>
                            <strong>Requested:</strong> <?= date('M d, Y H:i', strtotime($request['requested_at'])) ?>
                        </p>
                        
                        <div class="request-actions">
                            <?php if($request['student_points'] >= $request['reward_cost']): ?>
                                <form action="../../Controllers/RewardsController.php?action=teacher_approve&request_id=<?= $request['id'] ?>" 
                                      method="POST" 
                                      class="d-inline"
                                      onsubmit="return confirm('Approve this reward for <?= htmlspecialchars($request['student_name']) ?>?');">
                                    <button type="submit" class="btn approve-btn">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn approve-btn" disabled title="Student doesn't have enough points">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                            <?php endif; ?>
                            
                            <form action="../../Controllers/RewardsController.php?action=teacher_reject&request_id=<?= $request['id'] ?>" 
                                  method="POST" 
                                  class="d-inline"
                                  onsubmit="return confirm('Reject this request from <?= htmlspecialchars($request['student_name']) ?>?');">
                                <button type="submit" class="btn reject-btn">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="document.getElementById('notes-<?= $request['id'] ?>').style.display='block'">
                                <i class="fas fa-comment me-1"></i>Add Notes
                            </button>
                        </div>
                        
                        <div id="notes-<?= $request['id'] ?>" style="display: none; margin-top: 10px;">
                            <form action="../../Controllers/RewardsController.php?action=teacher_approve&request_id=<?= $request['id'] ?>" 
                                  method="POST">
                                <textarea name="notes" class="form-control mb-2" 
                                          placeholder="Add approval notes (optional)" rows="2"></textarea>
                                <button type="submit" class="btn btn-sm btn-primary">Approve with Notes</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Analytics Section -->
    <div class="section-header">
        <h3 class="mb-0">
            <i class="fas fa-chart-bar me-2" style="color: #8b5cf6;"></i>
            Reward Analytics
        </h3>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-6">
            <div class="reward-card analytics-card">
                <h5 class="fw-bold mb-3" style="color: #8b5cf6;">
                    <i class="fas fa-chart-pie me-2"></i>Redemptions by Category
                </h5>
                
                <?php if(empty($redemptionStats)): ?>
                    <p class="text-muted">No redemption data available yet.</p>
                <?php else: ?>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Redemptions</th>
                                <th>Points Spent</th>
                                <th>Avg/Item</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($redemptionStats as $stat): ?>
                                <tr>
                                    <td><?= $stat['category'] ?></td>
                                    <td><?= $stat['redemption_count'] ?></td>
                                    <td><?= $stat['total_points_spent'] ?> pts</td>
                                    <td><?= round($stat['avg_points_per_redemption'], 1) ?> pts</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="reward-card analytics-card">
                <h5 class="fw-bold mb-3" style="color: #8b5cf6;">
                    <i class="fas fa-fire me-2"></i>Most Popular Rewards
                </h5>
                
                <?php if(empty($popularRewards)): ?>
                    <p class="text-muted">No popularity data available yet.</p>
                <?php else: ?>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Reward</th>
                                <th>Category</th>
                                <th>Redemptions</th>
                                <th>Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($popularRewards as $popular): ?>
                                <tr>
                                    <td><?= htmlspecialchars($popular['title']) ?></td>
                                    <td><?= $popular['category'] ?></td>
                                    <td><?= $popular['redemption_count'] ?></td>
                                    <td><?= $popular['pointsCost'] ?> pts</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <div id="low-stock" class="section-header">
        <h3 class="mb-0">
            <i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i>
            Low Stock Alerts
            <?php if(count($lowStockRewards) > 0): ?>
                <span class="badge bg-danger ms-2"><?= count($lowStockRewards) ?></span>
            <?php endif; ?>
        </h3>
    </div>

    <?php if(empty($lowStockRewards)): ?>
        <div class="alert alert-success mb-4">
            <i class="fas fa-check-circle me-2"></i>
            All rewards are sufficiently stocked!
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach($lowStockRewards as $reward): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="reward-card" style="border-left-color: #ef4444;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="fw-bold mb-0" style="color: #111827;">
                                <?= htmlspecialchars($reward['title']) ?>
                            </h6>
                            <span class="badge bg-danger">Only <?= $reward['availability'] ?> left</span>
                        </div>
                        <p class="text-secondary small mb-2"><?= htmlspecialchars($reward['description']) ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="points-badge"><?= $reward['pointsCost'] ?> pts</span>
                            <a href="?edit_id=<?= $reward['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit me-1"></i>Restock
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Create Reward Button -->
    <div class="d-flex justify-content-center mb-5">
        <a href="?create=new" class="btn create-btn fs-5 px-5 py-3">
            <i class="fas fa-plus me-2"></i>Create New Reward
        </a>
    </div>

    <!-- Main Rewards Grid -->
    <div id="rewards-section" class="section-header">
        <h3 class="mb-0">
            <i class="fas fa-gift me-2" style="color: #2563eb;"></i>
            All Rewards
        </h3>
    </div>
    
    <?php if ($rewards): ?>
        <div class="row g-4">
            <?php foreach ($rewards as $r): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="reward-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="fw-bold mb-0" style="color: #111827;">
                                <?= htmlspecialchars($r['title']) ?>
                            </h5>
                            <div class="d-flex flex-column align-items-end">
                                <span class="points-badge mb-1"><?= $r['pointsCost'] ?> pts</span>
                            </div>
                        </div>
                        <p class="text-secondary mb-3"><?= htmlspecialchars($r['description']) ?></p>
                        <div class="d-flex gap-3 flex-wrap mb-3">
                            <span class="category-badge"><?= htmlspecialchars($r['category']) ?></span>
                            <?php if($r['type']): ?>
                                <span class="badge-status bg-light border text-dark"><?= htmlspecialchars($r['type']) ?></span>
                            <?php endif; ?>
                            <span class="badge-status <?= $r['status']=='Active' ? 'badge-active' : 'badge-inactive' ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                            <span class="badge bg-light border text-dark"><?= $r['availability'] ?> available</span>
                            <?php if($r['min_tier']): ?>
                                <span class="badge bg-info text-white"><?= $r['min_tier'] ?>+</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-3">
                            <a href="?edit_id=<?= $r['id'] ?>" class="btn edit-btn flex-fill">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <form action="../../Controllers/RewardsController.php?action=delete&id=<?= $r['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this reward?');">
                                <button type="submit" class="btn delete-btn">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-muted fs-5">No rewards found. Create your first reward!</p>
    <?php endif; ?>

</main>

<!-- Modal -->
<div id="rewardForm" class="form-modal">
    <div class="form-container">
        <h3 class="form-title">
            <?php if($editReward): ?>
                <i class="fas fa-edit me-2"></i>Edit Reward
            <?php else: ?>
                <i class="fas fa-plus me-2"></i>Create New Reward
            <?php endif; ?>
        </h3>
        <form id="rewardFormData" method="POST" action="../../Controllers/RewardsController.php?<?= $editReward ? 'action=update&id=' . $editReward['id'] : 'action=create' ?>">
            
            <div class="mb-3">
                <label for="title" class="form-label required-field">Reward Title</label>
                <input type="text" id="title" name="title" class="form-control" value="<?= $editReward ? htmlspecialchars($editReward['title']) : '' ?>" />
                <div class="error-message" id="titleError"></div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pointsCost" class="form-label required-field">Points Cost</label>
                    <input type="text" id="pointsCost" name="pointsCost" class="form-control" value="<?= $editReward ? $editReward['pointsCost'] : '' ?>" />
                    <div class="error-message" id="pointsError"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="availability" class="form-label required-field">Availability</label>
                    <input type="text" id="availability" name="availability" class="form-control" value="<?= $editReward ? $editReward['availability'] : '' ?>" />
                    <div class="error-message" id="availabilityError"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label required-field">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= $editReward ? htmlspecialchars($editReward['description']) : '' ?></textarea>
                <div class="error-message" id="descriptionError"></div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="category" class="form-label required-field">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">Select Category</option>
                        <option value="Badge" <?= ($editReward && $editReward['category'] == 'Badge') ? 'selected' : '' ?>>Badge</option>
                        <option value="Bonus Points" <?= ($editReward && $editReward['category'] == 'Bonus Points') ? 'selected' : '' ?>>Bonus Points</option>
                        <option value="Certificate" <?= ($editReward && $editReward['category'] == 'Certificate') ? 'selected' : '' ?>>Certificate</option>
                        <option value="Perk" <?= ($editReward && $editReward['category'] == 'Perk') ? 'selected' : '' ?>>Perk</option>
                        <option value="Discount" <?= ($editReward && $editReward['category'] == 'Discount') ? 'selected' : '' ?>>Discount</option>
                        <option value="Special" <?= ($editReward && $editReward['category'] == 'Special') ? 'selected' : '' ?>>Special</option>
                    </select>
                    <div class="error-message" id="categoryError"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="type" class="form-label">Type (optional)</label>
                    <input type="text" id="type" name="type" class="form-control" value="<?= $editReward ? htmlspecialchars($editReward['type']) : '' ?>" />
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label required-field">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Select Status</option>
                        <option value="Active" <?= ($editReward && $editReward['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($editReward && $editReward['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <div class="error-message" id="statusError"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="min_tier" class="form-label">Minimum Tier (optional)</label>
                    <select id="min_tier" name="min_tier" class="form-control">
                        <option value="">None</option>
                        <option value="Bronze" <?= ($editReward && $editReward['min_tier'] == 'Bronze') ? 'selected' : '' ?>>Bronze</option>
                        <option value="Silver" <?= ($editReward && $editReward['min_tier'] == 'Silver') ? 'selected' : '' ?>>Silver</option>
                        <option value="Gold" <?= ($editReward && $editReward['min_tier'] == 'Gold') ? 'selected' : '' ?>>Gold</option>
                        <option value="Platinum" <?= ($editReward && $editReward['min_tier'] == 'Platinum') ? 'selected' : '' ?>>Platinum</option>
                    </select>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-3">
                <a href="?" class="btn btn-outline-secondary px-4">Cancel</a>
                <button type="submit" class="btn create-btn px-4">
                    <i class="fas fa-save me-2"></i><?= $editReward ? 'Update' : 'Save' ?> Reward
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Validation with custom error messages
document.getElementById('rewardFormData').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Clear previous errors
    document.querySelectorAll('.error-message').forEach(el => {
        el.textContent = '';
        el.style.display = 'none';
    });
    
    // Validate title
    const title = document.getElementById('title').value.trim();
    if (title.length < 3) {
        document.getElementById('titleError').textContent = 'Title must be at least 3 characters';
        document.getElementById('titleError').style.display = 'block';
        isValid = false;
    }
    
    // Validate points cost
    const pointsCost = document.getElementById('pointsCost').value;
    const pointsNum = parseInt(pointsCost);
    if (isNaN(pointsNum) || pointsNum < 1) {
        document.getElementById('pointsError').textContent = 'Points cost must be at least 1';
        document.getElementById('pointsError').style.display = 'block';
        isValid = false;
    } else if (pointsNum > 1000) {
        document.getElementById('pointsError').textContent = 'Points cost cannot exceed 1000';
        document.getElementById('pointsError').style.display = 'block';
        isValid = false;
    }
    
    // Validate availability
    const availability = document.getElementById('availability').value;
    const availNum = parseInt(availability);
    if (isNaN(availNum) || availNum < 0) {
        document.getElementById('availabilityError').textContent = 'Availability cannot be negative';
        document.getElementById('availabilityError').style.display = 'block';
        isValid = false;
    } else if (availNum > 10000) {
        document.getElementById('availabilityError').textContent = 'Availability cannot exceed 10000';
        document.getElementById('availabilityError').style.display = 'block';
        isValid = false;
    }
    
    // Validate description
    const description = document.getElementById('description').value.trim();
    if (description.length < 3) {
        document.getElementById('descriptionError').textContent = 'Description must be at least 3 characters';
        document.getElementById('descriptionError').style.display = 'block';
        isValid = false;
    }
    
    // Validate category
    const category = document.getElementById('category').value;
    if (!category) {
        document.getElementById('categoryError').textContent = 'Please select a category';
        document.getElementById('categoryError').style.display = 'block';
        isValid = false;
    }
    
    // Validate status
    const status = document.getElementById('status').value;
    if (!status) {
        document.getElementById('statusError').textContent = 'Please select a status';
        document.getElementById('statusError').style.display = 'block';
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        return false;
    }
});
</script>

</body>
</html>