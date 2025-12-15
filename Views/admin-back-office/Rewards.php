<?php 
if(session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../config.php'; 
require_once __DIR__ . '/../../Models/Rewards.php';
require_once __DIR__ . '/../../Models/Points.php';

$rewards = Rewards::getAll($pdo);  

// Group rewards by category for the tabs
$rewardsByCategory = [];
foreach ($rewards as $reward) {
    $category = $reward['category'] ?? 'Uncategorized';
    if (!isset($rewardsByCategory[$category])) {
        $rewardsByCategory[$category] = [];
    }
    $rewardsByCategory[$category][] = $reward;
}

$allTiers = RewardTiers::getAllTiers($pdo);
$allBundles = Rewards::getAllBundles($pdo);

$redemptionStats = Rewards::getRedemptionStats($pdo, date('Y-m-01'), date('Y-m-t'));
$popularRewards = Rewards::getPopularRewards($pdo, 10);

// FIXED: Get low stock rewards (availability 5 or less)
$lowStockRewards = Rewards::getLowStockRewards($pdo, 10); // Get all low stock items
$pendingRequests = Rewards::getPendingRequests($pdo);

$tierDistribution = RewardTiers::getTierDistribution($pdo);

$stmt = $pdo->prepare("SELECT SUM(points) as total_points FROM users WHERE role = 'student'");
$stmt->execute();
$totalPoints = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'] ?? 0;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_redemptions, 
           SUM(ABS(points_amount)) as points_redeemed 
    FROM activity_log 
    WHERE activity_type = 'redeem_reward' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$monthlyStats = $stmt->fetch(PDO::FETCH_ASSOC);

$editReward = null;
if(isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    if($editId > 0) {
        $editReward = Rewards::getByID($pdo, $editId);
    }
}

// Handle create if parameter is passed
$showCreateForm = isset($_GET['create']);

// Handle bundle creation/edit
$editBundle = null;
if(isset($_GET['edit_bundle'])) {
    $bundleId = (int)$_GET['edit_bundle'];
    if($bundleId > 0) {
        $editBundle = Rewards::getBundleByID($pdo, $bundleId);
    }
}

$showBundleForm = isset($_GET['create_bundle']);

// Get the current tab from URL or default to analytics
$currentTab = $_GET['tab'] ?? 'analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reward Management - Admin</title>
<link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body { 
    background: #f8fafc; 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    min-height: 100vh;
}
.container { 
    max-width: 1400px; 
    position: relative;
}
.dashboard-header { 
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 12px; 
    padding: 30px; 
    margin-bottom: 30px; 
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    color: white;
}
.stats-card { 
    background: white; 
    border-radius: 12px; 
    padding: 20px; 
    text-align: center; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
    border-left: 5px solid;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 100%;
}
.stats-card:hover { 
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}
.stats-primary { border-left-color: #2563eb; }
.stats-success { border-left-color: #10b981; }
.stats-warning { border-left-color: #f59e0b; }
.stats-danger { border-left-color: #ef4444; }
.stats-purple { border-left-color: #8b5cf6; }
.stats-pink { border-left-color: #ec4899; }

.reward-card { 
    background: white; 
    border-radius: 12px; 
    padding: 20px; 
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
.bundle-card {
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
.bundle-btn {
    background: #8b5cf6;
}
.bundle-btn:hover {
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
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
.discount-badge {
    background: #10b981;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.form-modal {
    display: <?= ($editReward || $showCreateForm || $editBundle || $showBundleForm) ? 'block' : 'none' ?>; 
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
    max-width: 700px; 
    width: 95%;
    max-height: 90vh;
    overflow-y: auto;
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
.feature-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.feature-section h4 {
    color: #2563eb;
    margin-bottom: 20px;
    font-weight: 700;
}
.tier-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 5px solid;
    transition: all 0.3s ease;
}
.tier-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.tier-bronze { border-left-color: #cd7f32; }
.tier-silver { border-left-color: #c0c0c0; }
.tier-gold { border-left-color: #ffd700; }
.tier-platinum { border-left-color: #e5e4e2; }
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
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.progress-bar-custom {
    height: 10px;
    background: #e5e7eb;
    border-radius: 5px;
    overflow: hidden;
    margin: 5px 0;
}
.progress-fill {
    height: 100%;
    background: #2563eb;
    border-radius: 5px;
}
.tab-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
    flex-wrap: wrap;
}
.tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.3s ease;
    white-space: nowrap;
}
.tab-btn:hover {
    color: #2563eb;
    background: #f0f9ff;
}
.tab-btn.active {
    color: #2563eb;
    border-bottom: 3px solid #2563eb;
    background: #f0f9ff;
}
/* Add these styles for validation */
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

/* NEW STYLES FOR TABBED REWARDS AND ALERTS */

/* Category Navigation for Rewards */
.category-navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
    background: #f9fafb;
    padding: 15px;
    border-radius: 12px;
}
.category-btn {
    padding: 10px 20px;
    background: #e5e7eb;
    border: none;
    border-radius: 20px;
    font-weight: 600;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.category-btn:hover {
    background: #d1d5db;
    transform: translateY(-2px);
}
.category-btn.active {
    background: #2563eb;
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.tab-content {
    padding: 25px;
    animation: fadeIn 0.5s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Alerts Sidebar - FIXED BADGE STYLES */
.alert-sidebar {
    position: fixed;
    top: 100px;
    right: 20px;
    z-index: 1000;
    width: 60px;
    transition: all 0.3s ease;
}
.alert-item {
    margin-bottom: 10px;
    width: 60px;
    position: relative;
}
.alert-trigger {
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 12px 12px 0 0;
    padding: 15px;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    transition: all 0.3s ease;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.pending-alert {
    background: #f59e0b;
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
}
.pending-alert:hover {
    background: #d97706;
    box-shadow: 0 12px 30px rgba(245, 158, 11, 0.5);
}
.lowstock-alert:hover {
    background: #dc2626;
    box-shadow: 0 12px 30px rgba(239, 68, 68, 0.5);
}
.alert-trigger .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: white;
    color: #ef4444;
    font-weight: bold;
    font-size: 0.8rem;
    padding: 3px 8px;
    border-radius: 10px;
    min-width: 25px;
    min-height: 25px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    z-index: 1001;
    opacity: 1 !important;
    visibility: visible !important;
}
.pending-alert .badge {
    color: #f59e0b;
}
.alert-sidebar-content {
    position: absolute;
    top: 60px;
    right: 0;
    width: 350px;
    background: white;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    display: none;
    max-height: 70vh;
    overflow-y: auto;
}
.alert-sidebar-content.show {
    display: block;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.alert-sidebar-header {
    background: #fef2f2;
    padding: 20px;
    border-bottom: 2px solid #fecaca;
}
.pending-sidebar-header {
    background: #fffbeb;
    border-bottom: 2px solid #fde68a;
}
.alert-sidebar-body {
    padding: 20px;
}
.alert-sidebar-item {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.pending-sidebar-item {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
}
.alert-sidebar-item:hover {
    transform: translateX(-5px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}
.pending-sidebar-item:hover {
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
}
.alert-sidebar-item h6, .pending-sidebar-item h6 {
    margin-bottom: 5px;
    color: #111827;
    font-size: 1rem;
}
.alert-sidebar-item .stock-count {
    color: #ef4444;
    font-weight: bold;
    font-size: 0.9rem;
}
.pending-sidebar-item .student-name {
    color: #92400e;
    font-weight: bold;
}
.alert-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

/* Main content area adjustment for sidebar */
.main-content-area {
    padding-right: 80px;
}

@media (max-width: 768px) {
    .main-content-area {
        padding-right: 0;
    }
    .alert-sidebar {
        position: relative;
        top: 0;
        right: 0;
        width: 100%;
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    .alert-item {
        width: 100%;
    }
    .alert-trigger {
        width: 100%;
        border-radius: 12px;
    }
    .alert-sidebar-content {
        position: relative;
        top: 0;
        right: 0;
        width: 100%;
        border-radius: 0 0 12px 12px;
    }
    .category-navigation {
        justify-content: center;
    }
    .category-btn {
        min-width: 100px;
        padding: 8px 15px;
    }
    .tab-nav {
        overflow-x: auto;
    }
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

<!-- Alert Sidebar - FIXED: Badges now show numbers for both alerts -->
<div class="alert-sidebar">
    <!-- Low Stock Alert - FIXED: Shows number even when 0 -->
    <div class="alert-item">
        <button class="alert-trigger lowstock-alert" id="lowStockTrigger">
            <i class="fas fa-exclamation-triangle"></i>
            <span class="badge"><?= is_array($lowStockRewards) ? count($lowStockRewards) : 0 ?></span>
        </button>
        <div class="alert-sidebar-content" id="lowStockSidebar">
            <div class="alert-sidebar-header">
                <h4 class="mb-0" style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Low Stock Alerts
                    <span class="badge bg-danger ms-2"><?= is_array($lowStockRewards) ? count($lowStockRewards) : 0 ?></span>
                </h4>
            </div>
            <div class="alert-sidebar-body">
                <?php if(empty($lowStockRewards)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        All rewards are sufficiently stocked!
                    </div>
                <?php else: ?>
                    <?php foreach($lowStockRewards as $reward): ?>
                        <div class="alert-sidebar-item">
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($reward['title']) ?></h6>
                            <p class="text-secondary small mb-2"><?= htmlspecialchars($reward['description']) ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="points-badge"><?= $reward['pointsCost'] ?> pts</span>
                                <span class="stock-count">Only <?= $reward['availability'] ?> left</span>
                            </div>
                            <div class="alert-actions">
                                <a href="?edit_id=<?= $reward['id'] ?>&tab=rewards" class="btn btn-sm btn-outline-danger flex-fill">
                                    <i class="fas fa-edit me-1"></i>Edit & Restock
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Pending Requests Alert - FIXED: Shows number even when 0 -->
    <div class="alert-item">
        <button class="alert-trigger pending-alert" id="pendingTrigger">
            <i class="fas fa-clock"></i>
            <span class="badge"><?= is_array($pendingRequests) ? count($pendingRequests) : 0 ?></span>
        </button>
        <div class="alert-sidebar-content" id="pendingSidebar">
            <div class="alert-sidebar-header pending-sidebar-header">
                <h4 class="mb-0" style="color: #f59e0b;">
                    <i class="fas fa-clock me-2"></i>
                    Pending Requests
                    <span class="badge bg-warning ms-2"><?= is_array($pendingRequests) ? count($pendingRequests) : 0 ?></span>
                </h4>
            </div>
            <div class="alert-sidebar-body">
                <?php if(empty($pendingRequests)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No pending approval requests.
                    </div>
                <?php else: ?>
                    <?php foreach($pendingRequests as $request): ?>
                        <div class="pending-sidebar-item">
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($request['student_name']) ?></h6>
                            <p class="text-secondary small mb-2"><?= htmlspecialchars($request['reward_title']) ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="points-badge"><?= $request['reward_cost'] ?> pts</span>
                                <span class="student-name">Has <?= $request['student_points'] ?> pts</span>
                            </div>
                            <div class="alert-actions">
                                <?php if($request['student_points'] >= $request['reward_cost']): ?>
                                    <form action="../../Controllers/RewardsController.php" method="POST" class="d-inline w-100">
                                        <input type="hidden" name="action" value="teacher_approve">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <input type="hidden" name="tab" value="pending">
                                        <button type="submit" class="btn btn-sm btn-success w-100">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-danger w-100 py-2">Needs <?= $request['reward_cost'] - $request['student_points'] ?> more pts</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<main class="container py-5">
    <div class="main-content-area">

        <div class="dashboard-header text-center">
            <h1 class="mb-3">
                <i class="fas fa-crown me-3"></i>Reward Management - Admin Dashboard
            </h1>
            <p class="mb-0 opacity-90">Complete control panel for reward system management</p>
        </div>

        <!-- Quick Stats Overview - FIXED: Low stock count shows properly -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card stats-success" onclick="showMainTab('analytics')">
                    <i class="fas fa-gift mb-3" style="font-size: 2rem; color: #10b981;"></i>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #111827;">
                        <?= $monthlyStats['total_redemptions'] ?? 0 ?>
                    </div>
                    <small class="text-muted">Redemptions (30 days)</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card stats-warning" onclick="showMainTab('pending')">
                    <i class="fas fa-clock mb-3" style="font-size: 2rem; color: #f59e0b;"></i>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #111827;">
                        <?= is_array($pendingRequests) ? count($pendingRequests) : 0 ?>
                    </div>
                    <small class="text-muted">Pending Requests</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card stats-danger" onclick="showMainTab('lowstock')">
                    <i class="fas fa-exclamation-triangle mb-3" style="font-size: 2rem; color: #ef4444;"></i>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #111827;">
                        <?= is_array($lowStockRewards) ? count($lowStockRewards) : 0 ?>
                    </div>
                    <small class="text-muted">Low Stock Items</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card stats-purple" onclick="showMainTab('tiers')">
                    <i class="fas fa-trophy mb-3" style="font-size: 2rem; color: #8b5cf6;"></i>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #111827;">
                        <?= count($allTiers) ?>
                    </div>
                    <small class="text-muted">Active Tiers</small>
                </div>
            </div>
        </div>

        <!-- Tab Navigation - FIXED: Badge counts show properly -->
        <div class="tab-nav">
            <button class="tab-btn <?= $currentTab == 'analytics' ? 'active' : '' ?>" onclick="showMainTab('analytics')">
                <i class="fas fa-chart-bar me-2"></i>Analytics
            </button>
            <button class="tab-btn <?= $currentTab == 'rewards' ? 'active' : '' ?>" onclick="showMainTab('rewards')">
                <i class="fas fa-gift me-2"></i>Rewards
            </button>
            <button class="tab-btn <?= $currentTab == 'bundles' ? 'active' : '' ?>" onclick="showMainTab('bundles')">
                <i class="fas fa-box me-2"></i>Bundles
            </button>
            <button class="tab-btn <?= $currentTab == 'tiers' ? 'active' : '' ?>" onclick="showMainTab('tiers')">
                <i class="fas fa-trophy me-2"></i>Tiers
            </button>
            <button class="tab-btn <?= $currentTab == 'pending' ? 'active' : '' ?>" onclick="showMainTab('pending')">
                <i class="fas fa-clock me-2"></i>Pending
                <span class="badge bg-warning ms-1"><?= is_array($pendingRequests) ? count($pendingRequests) : 0 ?></span>
            </button>
            <button class="tab-btn <?= $currentTab == 'lowstock' ? 'active' : '' ?>" onclick="showMainTab('lowstock')">
                <i class="fas fa-exclamation-triangle me-2"></i>Low Stock
                <span class="badge bg-danger ms-1"><?= is_array($lowStockRewards) ? count($lowStockRewards) : 0 ?></span>
            </button>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics-tab" class="tab-content" style="<?= $currentTab == 'analytics' ? 'display: block;' : 'display: none;' ?>">
            <div class="row g-4">
                <!-- Redemption Stats -->
                <div class="col-lg-6">
                    <div class="feature-section">
                        <h4><i class="fas fa-chart-pie me-2"></i>Redemptions by Category</h4>
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
                                            <td><?= number_format($stat['total_points_spent']) ?> pts</td>
                                            <td><?= round($stat['avg_points_per_redemption'], 1) ?> pts</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Popular Rewards -->
                <div class="col-lg-6">
                    <div class="feature-section">
                        <h4><i class="fas fa-fire me-2"></i>Top 10 Popular Rewards</h4>
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

                <!-- Tier Distribution -->
                <div class="col-lg-6">
                    <div class="feature-section">
                        <h4><i class="fas fa-users me-2"></i>Student Tier Distribution</h4>
                        <?php if(empty($tierDistribution)): ?>
                            <p class="text-muted">No tier distribution data available.</p>
                        <?php else: ?>
                            <table class="analytics-table">
                                <thead>
                                    <tr>
                                        <th>Tier</th>
                                        <th>Students</th>
                                        <th>Points Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($tierDistribution as $tier): ?>
                                        <tr>
                                            <td><strong><?= $tier['tier_name'] ?></strong></td>
                                            <td><?= $tier['student_count'] ?> students</td>
                                            <td>
                                                <?= $tier['min_points'] ?> 
                                                <?= $tier['max_points'] ? ' - ' . $tier['max_points'] : '+' ?> 
                                                points
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Summary -->
                <div class="col-lg-6">
                    <div class="feature-section">
                        <h4><i class="fas fa-calendar-alt me-2"></i>Monthly Summary</h4>
                        <div class="chart-container">
                            <div class="mb-3">
                                <strong>Total Redemptions:</strong> 
                                <span class="float-end"><?= $monthlyStats['total_redemptions'] ?? 0 ?></span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?= min(($monthlyStats['total_redemptions'] ?? 0) / 100 * 100, 100) ?>%"></div>
                            </div>
                            
                            <div class="mb-3 mt-4">
                                <strong>Points Redeemed:</strong> 
                                <span class="float-end"><?= number_format($monthlyStats['points_redeemed'] ?? 0) ?> pts</span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?= min(($monthlyStats['points_redeemed'] ?? 0) / 10000 * 100, 100) ?>%"></div>
                            </div>
                            
                            <div class="mt-4">
                                <strong>Redemption Rate:</strong> 
                                <span class="float-end">
                                    <?php 
                                    $totalStudents = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch()['count'];
                                    $redemptionRate = $totalStudents > 0 ? (($monthlyStats['total_redemptions'] ?? 0) / $totalStudents) * 100 : 0;
                                    echo round($redemptionRate, 1) . '%';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rewards Tab with Category Navigation -->
        <div id="rewards-tab" class="tab-content" style="<?= $currentTab == 'rewards' ? 'display: block;' : 'display: none;' ?>">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">
                    <i class="fas fa-gift me-2" style="color: #2563eb;"></i>
                    All Rewards by Category
                </h3>
                <div class="d-flex gap-2">
                    <a href="?create=new&tab=rewards" class="btn create-btn">
                        <i class="fas fa-plus me-2"></i>New Reward
                    </a>
                    <a href="?create_bundle=new&tab=rewards" class="btn bundle-btn">
                        <i class="fas fa-box me-2"></i>New Bundle
                    </a>
                </div>
            </div>

            <?php if (!empty($rewardsByCategory)): ?>
                <!-- Category Navigation -->
                <div class="category-navigation" id="categoryNav">
                    <?php 
                    $firstCategory = true;
                    $firstCategoryId = '';
                    foreach($rewardsByCategory as $category => $categoryRewards): 
                        $categoryId = strtolower(str_replace(' ', '-', $category));
                        if ($firstCategory) {
                            $firstCategoryId = $categoryId;
                        }
                        $isActive = ($_GET['category'] ?? $firstCategoryId) == $categoryId;
                    ?>
                        <button class="category-btn <?= $isActive ? 'active' : '' ?>" 
                                onclick="showCategory('<?= $categoryId ?>', true)">
                            <?= htmlspecialchars($category) ?>
                            <span class="badge bg-primary ms-1"><?= count($categoryRewards) ?></span>
                        </button>
                    <?php 
                        $firstCategory = false;
                    endforeach; 
                    ?>
                </div>

                <!-- Tab Content Container -->
                <div class="tabs-container">
                    <?php 
                    $firstTab = true;
                    foreach($rewardsByCategory as $category => $categoryRewards): 
                        $categoryId = strtolower(str_replace(' ', '-', $category));
                        $isActive = ($_GET['category'] ?? $firstCategoryId) == $categoryId;
                    ?>
                        <div id="tab-<?= $categoryId ?>" class="tab-content" style="<?= $isActive ? 'display: block;' : 'display: none;' ?>">
                            <?php if (empty($categoryRewards)): ?>
                                <p class="text-center text-muted fs-5">No rewards found in <?= $category ?> category.</p>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach ($categoryRewards as $r): ?>
                                        <div class="col-lg-6 col-xl-4">
                                            <div class="reward-card">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h5 class="fw-bold mb-0" style="color: #111827;">
                                                        <?= htmlspecialchars($r['title']) ?>
                                                    </h5>
                                                    <div class="d-flex flex-column align-items-end">
                                                        <span class="points-badge mb-1"><?= $r['pointsCost'] ?> pts</span>
                                                        <?php if($r['availability'] <= 5): ?>
                                                            <span class="badge bg-danger mt-1">Low Stock</span>
                                                        <?php endif; ?>
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
                                                    <span class="badge bg-light border text-dark"><?= $r['availability'] ?> left</span>
                                                    <?php if($r['min_tier']): ?>
                                                        <span class="badge bg-info text-white"><?= $r['min_tier'] ?>+</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex gap-3">
                                                    <a href="?edit_id=<?= $r['id'] ?>&tab=rewards&category=<?= $categoryId ?>" class="btn edit-btn flex-fill">
                                                        <i class="fas fa-edit me-1"></i>Edit
                                                    </a>
                                                    <form action="../../Controllers/RewardsController.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this reward?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                        <input type="hidden" name="tab" value="rewards">
                                                        <input type="hidden" name="category" value="<?= $categoryId ?>">
                                                        <button type="submit" class="btn delete-btn">
                                                            <i class="fas fa-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php 
                        $firstTab = false;
                    endforeach; 
                    ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted fs-5">No rewards found. Create your first reward!</p>
            <?php endif; ?>
        </div>

        <!-- Bundles Tab -->
        <div id="bundles-tab" class="tab-content" style="<?= $currentTab == 'bundles' ? 'display: block;' : 'display: none;' ?>">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">
                    <i class="fas fa-box me-2" style="color: #8b5cf6;"></i>
                    Reward Bundles
                </h3>
                <a href="?create_bundle=new&tab=bundles" class="btn bundle-btn">
                    <i class="fas fa-plus me-2"></i>New Bundle
                </a>
            </div>
            
            <?php if ($allBundles): ?>
                <div class="row g-4">
                    <?php foreach ($allBundles as $bundle): 
                        $itemsDetail = $bundle['sample_items'] ?? '';
                        ?>
                        <div class="col-lg-6">
                            <div class="reward-card bundle-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="fw-bold mb-0" style="color: #8b5cf6;">
                                        <i class="fas fa-box-open me-2"></i>
                                        <?= htmlspecialchars($bundle['name']) ?>
                                    </h5>
                                    <div class="d-flex flex-column align-items-end">
                                        <span class="points-badge mb-1"><?= $bundle['total_cost'] ?> pts</span>
                                        <?php if($bundle['discount_percentage'] > 0): ?>
                                            <span class="discount-badge mt-1">Save <?= $bundle['discount_percentage'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <p class="card-text mb-3 text-muted"><?= htmlspecialchars($bundle['description']) ?></p>
                                
                                <?php if($itemsDetail): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-gift me-1"></i>
                                            <strong>Includes:</strong> <?= htmlspecialchars($itemsDetail) ?>
                                            <?php if($bundle['item_count'] > 3): ?>
                                                and <?= ($bundle['item_count'] - 3) ?> more items
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <span class="badge-status <?= $bundle['status'] == 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= ucfirst($bundle['status']) ?>
                                    </span>
                                    <?php if($bundle['limited_quantity']): ?>
                                        <span class="badge bg-warning ms-2">
                                            Limited: <?= $bundle['limited_quantity'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="?edit_bundle=<?= $bundle['id'] ?>&tab=bundles" class="btn bundle-btn flex-fill">
                                        <i class="fas fa-edit me-1"></i>Edit Bundle
                                    </a>
                                    <button class="btn btn-outline-danger" onclick="deleteBundle(<?= $bundle['id'] ?>, '<?= htmlspecialchars($bundle['name']) ?>', 'bundles')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted fs-5">No bundles found. Create your first bundle!</p>
            <?php endif; ?>
        </div>

        <!-- Tiers Tab -->
        <div id="tiers-tab" class="tab-content" style="<?= $currentTab == 'tiers' ? 'display: block;' : 'display: none;' ?>">
            <div class="feature-section">
                <h4><i class="fas fa-trophy me-2"></i>Reward Tiers Configuration</h4>
                <div class="row g-3">
                    <?php foreach ($allTiers as $tier): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="tier-card tier-<?= strtolower($tier['name']) ?>">
                                <div class="text-center mb-3">
                                    <div style="font-size: 2rem;"><?= $tier['badge_name'] ?></div>
                                    <h5 class="fw-bold mb-1"><?= $tier['name'] ?> Tier</h5>
                                    <small class="text-muted">
                                        <?= $tier['min_points'] ?> 
                                        <?= $tier['max_points'] ? ' - ' . $tier['max_points'] : '+' ?> 
                                        points
                                    </small>
                                </div>
                                <p class="small text-muted mb-2"><?= $tier['description'] ?></p>
                                <div class="benefits small">
                                    <strong>Benefits:</strong> <?= $tier['benefits'] ?>
                                </div>
                                <div class="mt-3">
                                    <span class="badge-status <?= $tier['status'] == 'Active' ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $tier['status'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pending Requests Tab -->
        <div id="pending-tab" class="tab-content" style="<?= $currentTab == 'pending' ? 'display: block;' : 'display: none;' ?>">
            <div class="feature-section">
                <h4><i class="fas fa-clock me-2" style="color: #f59e0b;"></i>
                    Pending Approval Requests
                    <span class="badge bg-warning ms-2"><?= is_array($pendingRequests) ? count($pendingRequests) : 0 ?></span>
                </h4>
                
                <?php if(empty($pendingRequests)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No pending approval requests at the moment.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Reward</th>
                                    <th>Points Needed</th>
                                    <th>Requested</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pendingRequests as $request): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['student_name']) ?></td>
                                        <td><?= htmlspecialchars($request['reward_title']) ?></td>
                                        <td>
                                            <?php 
                                            $needed = $request['reward_cost'] - $request['student_points'];
                                            if($needed > 0) {
                                                echo '<span class="badge bg-danger">' . $needed . ' more pts</span>';
                                            } else {
                                                echo '<span class="badge bg-success">Has enough</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?= date('M d, H:i', strtotime($request['requested_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-warning">Pending</span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <?php if($request['student_points'] >= $request['reward_cost']): ?>
                                                    <form action="../../Controllers/RewardsController.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="teacher_approve">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <input type="hidden" name="tab" value="pending">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check me-1"></i>Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form action="../../Controllers/RewardsController.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="teacher_reject">
                                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                    <input type="hidden" name="tab" value="pending">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Tab -->
        <div id="lowstock-tab" class="tab-content" style="<?= $currentTab == 'lowstock' ? 'display: block;' : 'display: none;' ?>">
            <div class="feature-section">
                <h4><i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i>
                    Low Stock Alerts
                    <span class="badge bg-danger ms-2"><?= is_array($lowStockRewards) ? count($lowStockRewards) : 0 ?></span>
                </h4>
                
                <?php if(empty($lowStockRewards)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        All rewards are sufficiently stocked!
                    </div>
                <?php else: ?>
                    <div class="row g-4">
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
                                        <a href="?edit_id=<?= $reward['id'] ?>&tab=lowstock" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Restock
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div> <!-- End of main-content-area -->
</main>

<!-- Reward Form Modal -->
<div id="rewardForm" class="form-modal">
    <div class="form-container">
        <h3 class="form-title">
            <?php if($editReward): ?>
                <i class="fas fa-edit me-2"></i>Edit Reward
            <?php elseif($editBundle): ?>
                <i class="fas fa-edit me-2"></i>Edit Bundle
            <?php elseif($showBundleForm): ?>
                <i class="fas fa-plus me-2"></i>Create New Bundle
            <?php else: ?>
                <i class="fas fa-plus me-2"></i>Create New Reward
            <?php endif; ?>
        </h3>
        
        <?php if($editBundle || $showBundleForm): ?>
        <!-- Bundle Form with validation messages -->
        <form id="bundleForm" method="POST" action="../../Controllers/RewardsController.php">
            <?php if($editBundle): ?>
                <input type="hidden" name="action" value="update_bundle">
                <input type="hidden" name="id" value="<?= $editBundle['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="create_bundle">
            <?php endif; ?>
            <input type="hidden" name="tab" value="<?= $_GET['tab'] ?? 'bundles' ?>">
            <input type="hidden" name="category" value="<?= $_GET['category'] ?? '' ?>">
            
            <div class="mb-3">
                <label for="bundle_name" class="form-label required-field">Bundle Name</label>
                <input type="text" id="bundle_name" name="name" class="form-control" 
                       value="<?= $editBundle ? htmlspecialchars($editBundle['name']) : '' ?>" />
                <div class="error-message" id="bundleNameError"></div>
            </div>
            
            <div class="mb-3">
                <label for="bundle_description" class="form-label required-field">Description</label>
                <textarea id="bundle_description" name="description" class="form-control" rows="3"><?= $editBundle ? htmlspecialchars($editBundle['description']) : '' ?></textarea>
                <div class="error-message" id="bundleDescriptionError"></div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="total_cost" class="form-label required-field">Total Points Cost</label>
                    <input type="text" id="total_cost" name="total_cost" class="form-control" 
                           value="<?= $editBundle ? $editBundle['total_cost'] : '' ?>" />
                    <div class="error-message" id="totalCostError"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="discount" class="form-label">Discount Percentage</label>
                    <input type="text" id="discount" name="discount_percentage" class="form-control" 
                           value="<?= $editBundle ? $editBundle['discount_percentage'] : '0' ?>" />
                    <div class="error-message" id="discountError"></div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="limited_quantity" class="form-label">Limited Quantity (optional)</label>
                    <input type="text" id="limited_quantity" name="limited_quantity" class="form-control" 
                           value="<?= $editBundle ? $editBundle['limited_quantity'] : '' ?>" />
                    <div class="error-message" id="quantityError"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label required-field">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Select Status</option>
                        <option value="active" <?= ($editBundle && $editBundle['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="upcoming" <?= ($editBundle && $editBundle['status'] == 'upcoming') ? 'selected' : '' ?>>Upcoming</option>
                        <option value="expired" <?= ($editBundle && $editBundle['status'] == 'expired') ? 'selected' : '' ?>>Expired</option>
                        <option value="inactive" <?= ($editBundle && $editBundle['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <div class="error-message" id="statusError"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label required-field">Select Rewards for Bundle</label>
                <div id="rewardsSelectionError" class="error-message"></div>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                    <?php 
                    $selectedRewards = [];
                    if($editBundle) {
                        $stmt = $pdo->prepare("SELECT reward_id FROM bundle_items WHERE bundle_id = ?");
                        $stmt->execute([$editBundle['id']]);
                        $selectedRewards = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    ?>
                    <?php foreach($rewards as $reward): ?>
                        <div class="form-check">
                            <input class="form-check-input bundle-reward-checkbox" type="checkbox" name="reward_ids[]" 
                                   value="<?= $reward['id'] ?>" 
                                   id="reward_<?= $reward['id'] ?>"
                                   <?= in_array($reward['id'], $selectedRewards) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reward_<?= $reward['id'] ?>">
                                <?= htmlspecialchars($reward['title']) ?> (<?= $reward['pointsCost'] ?> pts)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-3">
                <a href="?tab=<?= $_GET['tab'] ?? 'bundles' ?>&category=<?= $_GET['category'] ?? '' ?>" class="btn btn-outline-secondary px-4">Cancel</a>
                <button type="submit" class="btn bundle-btn px-4">
                    <i class="fas fa-save me-2"></i><?= $editBundle ? 'Update' : 'Save' ?> Bundle
                </button>
            </div>
        </form>
        
        <?php else: ?>
        <!-- Reward Form -->
        <form id="rewardFormData" method="POST" action="../../Controllers/RewardsController.php">
            <?php if($editReward): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $editReward['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="create">
            <?php endif; ?>
            <input type="hidden" name="tab" value="<?= $_GET['tab'] ?? 'rewards' ?>">
            <input type="hidden" name="category" value="<?= $_GET['category'] ?? $firstCategoryId ?>">
            
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
                <a href="?tab=<?= $_GET['tab'] ?? 'rewards' ?>&category=<?= $_GET['category'] ?? $firstCategoryId ?>" class="btn btn-outline-secondary px-4">Cancel</a>
                <button type="submit" class="btn create-btn px-4">
                    <i class="fas fa-save me-2"></i><?= $editReward ? 'Update' : 'Save' ?> Reward
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Main Tab Navigation
function showMainTab(tabName) {
    // Hide all main tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.style.display = 'block';
    }
    
    // Activate selected button
    event.target.classList.add('active');
    
    // Update URL without reloading
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    // If switching to rewards tab, initialize first category
    if (tabName === 'rewards') {
        setTimeout(() => {
            const firstCategoryBtn = document.querySelector('.category-btn');
            if (firstCategoryBtn && !document.querySelector('.category-btn.active')) {
                const categoryId = firstCategoryBtn.onclick.toString().match(/'([^']+)'/)[1];
                showCategory(categoryId, true);
            }
        }, 100);
    }
}

// Category Tab functionality for rewards tab
function showCategory(categoryId, preventScroll = false) {
    // Hide all category tabs
    document.querySelectorAll('#rewards-tab .tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Show selected category tab
    const selectedTab = document.getElementById('tab-' + categoryId);
    if (selectedTab) {
        selectedTab.style.display = 'block';
    }
    
    // Update category navigation buttons
    document.querySelectorAll('.category-btn').forEach(button => {
        button.classList.remove('active');
    });
    
    // Find and activate the correct category button
    document.querySelectorAll('.category-btn').forEach(button => {
        if (button.onclick.toString().includes(categoryId)) {
            button.classList.add('active');
        }
    });
    
    // Update URL without reloading
    const url = new URL(window.location);
    url.searchParams.set('tab', 'rewards');
    url.searchParams.set('category', categoryId);
    window.history.pushState({}, '', url);
    
    // Only scroll if explicitly requested
    if (!preventScroll && selectedTab) {
        selectedTab.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Alert sidebar functionality
let lowStockSidebarVisible = false;
let pendingSidebarVisible = false;

function toggleLowStockSidebar() {
    const sidebar = document.getElementById('lowStockSidebar');
    const trigger = document.getElementById('lowStockTrigger');
    
    // Close pending sidebar if open
    if (pendingSidebarVisible) {
        document.getElementById('pendingSidebar').classList.remove('show');
        pendingSidebarVisible = false;
    }
    
    if (lowStockSidebarVisible) {
        sidebar.classList.remove('show');
        trigger.style.borderRadius = '12px 12px 0 0';
        trigger.style.background = '#ef4444';
    } else {
        sidebar.classList.add('show');
        trigger.style.borderRadius = '12px';
        trigger.style.background = '#dc2626';
    }
    
    lowStockSidebarVisible = !lowStockSidebarVisible;
}

function togglePendingSidebar() {
    const sidebar = document.getElementById('pendingSidebar');
    const trigger = document.getElementById('pendingTrigger');
    
    // Close low stock sidebar if open
    if (lowStockSidebarVisible) {
        document.getElementById('lowStockSidebar').classList.remove('show');
        lowStockSidebarVisible = false;
    }
    
    if (pendingSidebarVisible) {
        sidebar.classList.remove('show');
        trigger.style.borderRadius = '12px 12px 0 0';
        trigger.style.background = '#f59e0b';
    } else {
        sidebar.classList.add('show');
        trigger.style.borderRadius = '12px';
        trigger.style.background = '#d97706';
    }
    
    pendingSidebarVisible = !pendingSidebarVisible;
}

// Close alert sidebars when clicking outside
document.addEventListener('click', function(event) {
    const lowStockSidebar = document.getElementById('lowStockSidebar');
    const lowStockTrigger = document.getElementById('lowStockTrigger');
    const pendingSidebar = document.getElementById('pendingSidebar');
    const pendingTrigger = document.getElementById('pendingTrigger');
    
    if (lowStockSidebarVisible && 
        !lowStockSidebar.contains(event.target) && 
        !lowStockTrigger.contains(event.target)) {
        toggleLowStockSidebar();
    }
    
    if (pendingSidebarVisible && 
        !pendingSidebar.contains(event.target) && 
        !pendingTrigger.contains(event.target)) {
        togglePendingSidebar();
    }
});

// Fix for alert triggers - ensure they work
document.getElementById('lowStockTrigger').addEventListener('click', function(e) {
    e.stopPropagation();
    toggleLowStockSidebar();
});

document.getElementById('pendingTrigger').addEventListener('click', function(e) {
    e.stopPropagation();
    togglePendingSidebar();
});

// Bundle deletion
function deleteBundle(bundleId, bundleName, currentTab) {
    if(confirm('Are you sure you want to delete the bundle "' + bundleName + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../../Controllers/RewardsController.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_bundle';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = bundleId;
        form.appendChild(idInput);
        
        const tabInput = document.createElement('input');
        tabInput.type = 'hidden';
        tabInput.name = 'tab';
        tabInput.value = currentTab || 'bundles';
        form.appendChild(tabInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Load saved tab from URL
function loadSavedTab() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlTab = urlParams.get('tab') || 'analytics';
    const urlCategory = urlParams.get('category');
    
    // Show the main tab
    const tabButton = document.querySelector(`.tab-btn[onclick*="${urlTab}"]`);
    if (tabButton) {
        // Remove active class from all buttons first
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to current tab button
        tabButton.classList.add('active');
        
        // Show the tab content
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        const selectedTab = document.getElementById(urlTab + '-tab');
        if (selectedTab) {
            selectedTab.style.display = 'block';
        }
    }
    
    // If it's the rewards tab and there's a category, show that category
    if (urlTab === 'rewards' && urlCategory) {
        setTimeout(() => {
            showCategory(urlCategory, true);
        }, 100);
    }
}

// Validation for reward form
if(document.getElementById('rewardFormData')) {
    document.getElementById('rewardFormData').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('#rewardFormData .error-message').forEach(el => {
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
}

// Validation for bundle form
if(document.getElementById('bundleForm')) {
    document.getElementById('bundleForm').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('#bundleForm .error-message').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        
        // Validate bundle name
        const bundleName = document.getElementById('bundle_name').value.trim();
        if (bundleName.length < 3) {
            document.getElementById('bundleNameError').textContent = 'Bundle name must be at least 3 characters';
            document.getElementById('bundleNameError').style.display = 'block';
            isValid = false;
        }
        
        // Validate description
        const bundleDescription = document.getElementById('bundle_description').value.trim();
        if (bundleDescription.length < 3) {
            document.getElementById('bundleDescriptionError').textContent = 'Description must be at least 3 characters';
            document.getElementById('bundleDescriptionError').style.display = 'block';
            isValid = false;
        }
        
        // Validate total cost
        const totalCost = document.getElementById('total_cost').value;
        const costNum = parseInt(totalCost);
        if (isNaN(costNum) || costNum < 1) {
            document.getElementById('totalCostError').textContent = 'Total cost must be at least 1 point';
            document.getElementById('totalCostError').style.display = 'block';
            isValid = false;
        } else if (costNum > 10000) {
            document.getElementById('totalCostError').textContent = 'Total cost cannot exceed 10000 points';
            document.getElementById('totalCostError').style.display = 'block';
            isValid = false;
        }
        
        // Validate discount
        const discount = document.getElementById('discount').value;
        if (discount) {
            const discountNum = parseInt(discount);
            if (isNaN(discountNum) || discountNum < 0 || discountNum > 100) {
                document.getElementById('discountError').textContent = 'Discount must be between 0 and 100';
                document.getElementById('discountError').style.display = 'block';
                isValid = false;
            }
        }
        
        // Validate limited quantity
        const limitedQuantity = document.getElementById('limited_quantity').value;
        if (limitedQuantity) {
            const quantityNum = parseInt(limitedQuantity);
            if (isNaN(quantityNum) || quantityNum < 1) {
                document.getElementById('quantityError').textContent = 'Limited quantity must be at least 1';
                document.getElementById('quantityError').style.display = 'block';
                isValid = false;
            }
        }
        
        // Validate status
        const status = document.getElementById('status').value;
        if (!status) {
            document.getElementById('statusError').textContent = 'Please select a status';
            document.getElementById('statusError').style.display = 'block';
            isValid = false;
        }
        
        // Check if at least one reward is selected
        const rewardCheckboxes = document.querySelectorAll('.bundle-reward-checkbox:checked');
        if (rewardCheckboxes.length === 0) {
            document.getElementById('rewardsSelectionError').textContent = 'Please select at least one reward for the bundle';
            document.getElementById('rewardsSelectionError').style.display = 'block';
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            return false;
        }
    });
}

// Close form modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('rewardForm');
    const formContainer = document.querySelector('.form-container');
    
    if (modal && modal.style.display === 'block' && !formContainer.contains(e.target)) {
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'rewards';
        const currentCategory = new URLSearchParams(window.location.search).get('category') || '';
        window.location.href = `?tab=${currentTab}&category=${currentCategory}`;
    }
});

// Fix for alert badges - ensure they always show numbers
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadSavedTab();
    
    // Ensure alert badges are visible
    const lowStockBadge = document.querySelector('#lowStockTrigger .badge');
    const pendingBadge = document.querySelector('#pendingTrigger .badge');
    
    if (lowStockBadge) {
        lowStockBadge.style.display = 'flex';
        lowStockBadge.style.visibility = 'visible';
        lowStockBadge.style.opacity = '1';
    }
    
    if (pendingBadge) {
        pendingBadge.style.display = 'flex';
        pendingBadge.style.visibility = 'visible';
        pendingBadge.style.opacity = '1';
    }
});
</script>

</body>
</html>
