<?php 
if(session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Models/Rewards.php';
require_once __DIR__ . '/../../Models/Points.php';

$studentID = $_SESSION['userID'] ?? 3; 
$userRole = $_SESSION['role'] ?? 'student';

error_log("Rewards View: Session userID=" . ($_SESSION['userID'] ?? 'NOT SET') . ", Using studentID=$studentID");

$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$studentID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

error_log("Rewards View: User points query result - " . print_r($user, true));

$balance = $user ? max(0, (int)$user['points']) : 100; 

error_log("Rewards View: Final balance = $balance");

$allRewards = Rewards::getAll($pdo);
$allBundles = Rewards::getAllBundles($pdo);
$tierProgress = RewardTiers::getTierProgress($pdo, $studentID);
$currentTier = $tierProgress['current_tier'];
$nextTier = $tierProgress['next_tier'];

$myRedemptions = [];
$stmt = $pdo->prepare("SELECT r.title as reward_title, r.pointsCost, al.points_amount as pointsSpent, al.created_at as redeemed_at, r.category as reward_category FROM activity_log al JOIN rewards r ON al.target_id = r.id WHERE al.user_id = ? AND al.activity_type = 'redeem_reward' ORDER BY al.created_at DESC LIMIT 5");
$stmt->execute([$studentID]);
$myRedemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests
$myRequests = Rewards::getStudentRequests($pdo, $studentID);

// Get recommended rewards
$recommendations = Rewards::recommendRewards($pdo, $studentID);

// Group rewards by category
$rewardsByCategory = [];
foreach ($allRewards as $reward) {
    $category = $reward['category'];
    if (!isset($rewardsByCategory[$category])) {
        $rewardsByCategory[$category] = [];
    }
    $rewardsByCategory[$category][] = $reward;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rewards Store - Student</title>
    <link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: #2c3e50;
        }
        .container {
            max-width: 1200px;
        }
        .reward-card { 
            transition: all 0.3s ease; 
            border-radius: 12px; 
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: none;
            height: 100%;
            border-left: 5px solid #2563eb;
        }
        .reward-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
        }
        
        .bundle-card {
            border-left: 5px solid #059669;
            background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
        }
        
        .redeem-btn { 
            background: #2563eb;
            border: none; 
            border-radius: 8px; 
            padding: 10px 20px; 
            font-weight: 600; 
            color: white;
            transition: all 0.3s ease;
        }
        .redeem-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        
        .bundle-btn {
            background: #059669;
        }
        .bundle-btn:hover {
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }
        
        .request-btn {
            background: #f59e0b;
        }
        .request-btn:hover {
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
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
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .savings-badge {
            background: #ef4444;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .tier-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #2563eb;
        }
        .progress-container { 
            background: #e5e7eb; 
            border-radius: 10px; 
            height: 12px; 
            margin: 15px 0; 
        }
        .progress-bar { 
            background: #2563eb; 
            height: 100%; 
            border-radius: 10px; 
            transition: width 0.5s ease; 
        }
        .tier-badge {
            font-size: 4rem;
            margin-bottom: 15px;
            line-height: 1;
        }
        
        .redemptions-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #059669;
        }
        
        .requests-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #f59e0b;
        }
        
        .recommendations-section {
            background: #3490dc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 8px 25px rgba(52, 144, 220, 0.3);
        }
        
        .redemption-item, .request-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #059669;
        }
        
        .request-item {
            border-left: 4px solid #f59e0b;
        }
        
        .points-display {
            background: white; 
            border: 3px solid #2563eb; 
            color: #2563eb; 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center; 
            font-size: 1.5em; 
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
        
        .section-title {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #2563eb;
        }
        
        .recommendation-title {
            color: white;
            border-bottom: 3px solid rgba(255,255,255,0.3);
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
        
        .btn-disabled {
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: not-allowed;
        }
        
        .category-header {
            background: #2563eb;
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin: 30px 0 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .category-header h4 {
            margin: 0;
            font-weight: 700;
        }
        .category-icon {
            font-size: 1.8rem;
            margin-right: 10px;
        }
        
        .bundle-header {
            background: #059669;
        }
        
        .no-achievements {
            text-align: center;
            padding: 30px;
            color: #6b7280;
        }
        
        .balance-info {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .earn-points-box {
            background: #f59e0b;
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        
        .recommendation-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Category Navigation Styles */
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
        .category-section {
            display: none;
        }
        .category-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Tier Colors */
        .tier-badge-bronze {
            background: #cd7f32;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #8b4513;
        }
        .tier-badge-silver {
            background: #c0c0c0;
            color: #333;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #808080;
        }
        .tier-badge-gold {
            background: #ffd700;
            color: #333;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #b8860b;
        }
        .tier-badge-platinum {
            background: #e5e4e2;
            color: #333;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #999;
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

<main class="container py-4">
    <!-- Points Display -->
    <div class="points-display">
        <i class="fas fa-star me-2"></i>
        <span id="balance"><?= $balance ?></span> Points Available
        <div class="balance-info">Last updated: <?= date('H:i:s') ?></div>
        
        <?php if ($balance <= 25): ?>
            <div class="mt-2">
                <?php if ($balance <= 0): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        You have no points! Complete challenges to earn rewards.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        You need <?= max(0, 25 - $balance) ?> more points to reach Bronze tier.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Earn Points Box (if low/negative points) -->
    <?php if ($balance <= 25): ?>
    <div class="earn-points-box">
        <h4><i class="fas fa-bullhorn me-2"></i>Earn More Points!</h4>
        <p class="mb-2">Complete challenges to earn points and unlock rewards</p>
        <a href="../front-office/Challenges.php" class="btn btn-light">
            <i class="fas fa-tasks me-1"></i> View Challenges
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Recommendations Section -->
    <?php if (!empty($recommendations) && (count($recommendations['affordable']) > 0 || count($recommendations['popular']) > 0)): ?>
    <div class="recommendations-section">
        <h3 class="section-title recommendation-title">
            <i class="fas fa-lightbulb me-2"></i>Recommended For You
        </h3>
        
        <div class="row">
            <?php if (!empty($recommendations['affordable'])): ?>
            <div class="col-md-6">
                <h5 class="text-white"><i class="fas fa-wallet me-2"></i>Within Your Budget</h5>
                <?php foreach($recommendations['affordable'] as $rec): ?>
                    <div class="recommendation-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($rec['title']) ?></strong>
                                <div class="small opacity-80"><?= $rec['pointsCost'] ?> points</div>
                            </div>
                            <a href="../../Controllers/RewardsController.php?action=redeem&id=<?= $rec['id'] ?>" 
                               class="btn btn-sm btn-light">
                                Redeem
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($recommendations['popular'])): ?>
            <div class="col-md-6">
                <h5 class="text-white"><i class="fas fa-fire me-2"></i>Most Popular</h5>
                <?php foreach($recommendations['popular'] as $rec): ?>
                    <div class="recommendation-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($rec['title']) ?></strong>
                                <div class="small opacity-80"><?= $rec['pointsCost'] ?> points</div>
                            </div>
                            <a href="../../Controllers/RewardsController.php?action=redeem&id=<?= $rec['id'] ?>" 
                               class="btn btn-sm btn-light">
                                Redeem
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($recommendations['bundles'])): ?>
        <div class="mt-3">
            <h5 class="text-white"><i class="fas fa-box me-2"></i>Special Bundles</h5>
            <div class="row">
                <?php foreach($recommendations['bundles'] as $bundle): ?>
                    <div class="col-md-6">
                        <div class="recommendation-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($bundle['name']) ?></strong>
                                    <div class="small opacity-80"><?= $bundle['total_cost'] ?> points</div>
                                </div>
                                <a href="../../Controllers/RewardsController.php?action=redeem_bundle&id=<?= $bundle['id'] ?>" 
                                   class="btn btn-sm btn-light">
                                    Get Bundle
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tier Progress Section -->
    <div class="tier-section">
        <h3 class="section-title">
            <i class="fas fa-trophy me-2"></i>Your Reward Tier
        </h3>
        <div class="row align-items-center">
            <div class="col-md-8">
                <?php if ($currentTier): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="fw-bold mb-0" style="color: #2563eb;">
                            <?= $currentTier['badge_name'] ?> <?= $currentTier['name'] ?> Tier
                        </h4>
                        <div class="text-end">
                            <div class="fw-bold" style="color: #2563eb; font-size: 1.5rem;"><?= $balance ?> Points</div>
                            <small class="text-muted">Current Balance</small>
                        </div>
                    </div>
                    <p class="text-muted mb-3"><?= $currentTier['description'] ?></p>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= $tierProgress['progress'] ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Progress: <?= round($tierProgress['progress'], 1) ?>%</small>
                        <?php if ($nextTier): ?>
                            <small class="text-muted fw-bold">
                                <?= $tierProgress['points_to_next'] ?> points to <?= $nextTier['name'] ?> Tier
                            </small>
                        <?php else: ?>
                            <small class="text-success fw-bold">
                                <i class="fas fa-crown me-1"></i>Highest tier achieved!
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-gift me-1"></i>
                            <strong>Benefits:</strong> <?= $currentTier['benefits'] ?>
                        </small>
                    </div>
                <?php else: ?>
                    <h4 class="fw-bold" style="color: #2563eb;">Getting Started</h4>
                    <p class="text-muted mb-3">Earn points to unlock reward tiers and access exclusive rewards!</p>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= min(max(($balance / 25) * 100, 0), 100) ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Progress to Bronze: <?= max($balance, 0) ?>/25 points</small>
                        <small class="text-muted fw-bold"><?= max(0, 25 - max($balance, 0)) ?> points needed</small>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-center">
                <div class="tier-badge">
                    <?= $currentTier ? $currentTier['badge_name'] : '🎯' ?>
                </div>
                <div class="fw-bold fs-5" style="color: #2563eb;">
                    <?= $currentTier ? $currentTier['name'] . ' Tier' : 'Starter' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Requests Section -->
    <?php if (!empty($myRequests)): ?>
    <div class="requests-section">
        <h3 class="section-title">
            <i class="fas fa-clock me-2"></i>My Pending Requests
        </h3>
        <?php foreach ($myRequests as $request): ?>
            <div class="request-item">
                <div>
                    <strong><?= htmlspecialchars($request['reward_title']) ?></strong>
                    <div class="text-muted" style="font-size: 0.9rem;">
                        <?= $request['pointsCost'] ?> points • 
                        Requested: <?= date('M d, Y H:i', strtotime($request['requested_at'])) ?>
                        <?php if($request['teacher_response']): ?>
                            <br>Teacher: <?= htmlspecialchars($request['teacher_response']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge status-<?= $request['status'] ?>">
                        <?= ucfirst($request['status']) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Redemption History (ONLY if exists) -->
    <?php if (!empty($myRedemptions)): ?>
    <div class="redemptions-section">
        <h3 class="section-title">
            <i class="fas fa-history me-2"></i>Recent Redemptions
        </h3>
        <?php foreach ($myRedemptions as $redemption): ?>
            <div class="redemption-item">
                <div>
                    <strong><?= htmlspecialchars($redemption['reward_title']) ?></strong>
                    <div class="text-muted" style="font-size: 0.9rem;">
                        <?= $redemption['reward_category'] ?> • <?= date('M d, Y H:i', strtotime($redemption['redeemed_at'])) ?>
                    </div>
                </div>
                <div class="text-danger fw-bold">
                    -<?= abs($redemption['pointsSpent']) ?> pts
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Special Bundles Section -->
    <?php if (!empty($allBundles)): ?>
    <div class="mb-4">
        <h3 class="section-title">
            <i class="fas fa-box me-2"></i>Special Reward Bundles
        </h3>
    </div>

    <div class="row g-4 mb-5">
        <?php foreach ($allBundles as $bundle): 
            $bundleCost = (int)$bundle['total_cost'];
            $discount = (int)$bundle['discount_percentage'];
            $canAfford = $balance >= $bundleCost;
            $sampleItems = $bundle['sample_items'] ?? '';
            ?>
            <div class="col-lg-6">
                <div class="reward-card bundle-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="fw-bold mb-0" style="color: #059669;">
                                <i class="fas fa-box-open me-2"></i>
                                <?= htmlspecialchars($bundle['name']) ?>
                            </h5>
                            <div class="d-flex flex-column align-items-end">
                                <span class="points-badge mb-1"><?= $bundleCost ?> pts</span>
                                <?php if($discount > 0): ?>
                                    <span class="discount-badge mt-1">Save <?= $discount ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p class="card-text mb-3 text-muted"><?= htmlspecialchars($bundle['description']) ?></p>
                        
                        <?php if($sampleItems): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-gift me-1"></i>
                                    <strong>Includes:</strong> <?= htmlspecialchars($sampleItems) ?>
                                    <?php if($bundle['item_count'] > 3): ?>
                                        and <?= ($bundle['item_count'] - 3) ?> more items
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <?php if($bundle['limited_quantity']): ?>
                                <span class="badge-status badge-inactive">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Limited: <?= $bundle['limited_quantity'] ?> available
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($canAfford): ?>
                            <form action="../../Controllers/RewardsController.php?action=redeem_bundle&id=<?= $bundle['id'] ?>" 
                                  method="POST" 
                                  onsubmit="return confirm('Redeem \"<?= htmlspecialchars($bundle['name']) ?>\" bundle for <?= $bundleCost ?> points?');">
                                <button type="submit" class="btn bundle-btn w-100">
                                    <i class="fas fa-box-open me-2"></i>
                                    Get Bundle (<?= $bundleCost ?> pts)
                                </button>
                            </form>
                        <?php else: ?>
                            <?php $needed = $bundleCost - $balance; ?>
                            <button class="btn btn-disabled w-100" disabled>
                                <i class="fas fa-lock me-2"></i>
                                Need <?= $needed ?> more points
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- All Available Rewards -->
    <div class="mb-4">
        <h3 class="section-title">
            <i class="fas fa-gift me-2"></i>All Available Rewards
        </h3>
    </div>

    <!-- Category Navigation -->
    <div class="category-navigation" id="categoryNav">
        <?php 
        $categories = array_keys($rewardsByCategory);
        foreach ($categories as $index => $category): 
        ?>
            <button class="category-btn <?= $index === 0 ? 'active' : '' ?>" 
                    onclick="showCategory('<?= $category ?>', this)">
                <?= $category ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php 
    foreach ($rewardsByCategory as $category => $rewards): 
    ?>
        <div class="category-section <?= array_key_first($rewardsByCategory) === $category ? 'active' : '' ?>" 
             id="category-<?= $category ?>">
            
            <div class="category-header">
                <div>
                    <?php 
                    $icon = 'fa-gift';
                    if($category == 'Badge') $icon = 'fa-medal';
                    if($category == 'Bonus Points') $icon = 'fa-coins';
                    if($category == 'Certificate') $icon = 'fa-certificate';
                    if($category == 'Perk') $icon = 'fa-star';
                    if($category == 'Discount') $icon = 'fa-tag';
                    ?>
                    <i class="fas <?= $icon ?> category-icon"></i>
                    <h4 class="d-inline"><?= $category ?></h4>
                </div>
                <span class="badge bg-light text-dark fs-6"><?= count($rewards) ?> items</span>
            </div>

            <div class="row g-4 mb-5">
                <?php foreach ($rewards as $r): 
                    $rewardCost = (int)$r['pointsCost'];
                    $rewardAvailability = (int)$r['availability'];
                    $rewardStatus = $r['status'];
                    $rewardMinTier = $r['min_tier'];
                    
                    // Check if student can afford
                    $canAfford = $balance >= $rewardCost;
                    
                    // Check if needs approval (missing 10 or fewer points)
                    $needsApproval = !$canAfford && ($rewardCost - $balance) <= 10;
                    
                    // Check availability
                    $isAvailable = $rewardAvailability > 0;
                    
                    // Check status
                    $isActive = $rewardStatus == 'Active';
                    
                    // Check tier requirement
                    $hasTier = true;
                    if ($rewardMinTier && $currentTier) {
                        $tierOrder = ['Bronze' => 1, 'Silver' => 2, 'Gold' => 3, 'Platinum' => 4];
                        $currentTierOrder = $tierOrder[$currentTier['name']] ?? 0;
                        $requiredTierOrder = $tierOrder[$rewardMinTier] ?? 0;
                        $hasTier = $currentTierOrder >= $requiredTierOrder;
                    }
                    
                    // Can redeem if ALL conditions are met
                    $canRedeem = $canAfford && $isAvailable && $isActive && $hasTier;
                    
                    // Calculate needed points
                    $neededPoints = max(0, $rewardCost - $balance);
                    ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="reward-card card h-100">
                            <div class="card-body p-4">
                                <h5 class="card-title fw-bold mb-2" style="color: #2563eb;">
                                    <i class="fas fa-star me-2"></i>
                                    <?= htmlspecialchars($r['title']) ?>
                                </h5>
                                <p class="card-text mb-3 text-muted"><?= htmlspecialchars($r['description']) ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="points-badge">
                                        <?= $rewardCost ?> pts
                                    </span>
                                    <?php if($rewardMinTier): ?>
                                        <?php 
                                        $tierClass = 'tier-badge-' . strtolower($rewardMinTier);
                                        ?>
                                        <span class="<?= $tierClass ?>">
                                            <?= $rewardMinTier ?>+
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <?php if(!$isActive): ?>
                                        <span class="badge-status badge-inactive">Inactive</span>
                                    <?php elseif(!$isAvailable): ?>
                                        <span class="badge-status badge-inactive">Out of Stock</span>
                                    <?php elseif($canRedeem): ?>
                                        <span class="badge-status badge-active">Available</span>
                                    <?php else: ?>
                                        <?php if(!$hasTier): ?>
                                            <span class="badge-status badge-inactive">Need <?= $rewardMinTier ?> Tier</span>
                                        <?php elseif($needsApproval): ?>
                                            <span class="badge-status badge-inactive">Request approval</span>
                                        <?php elseif(!$canAfford): ?>
                                            <span class="badge-status badge-inactive">Need <?= $neededPoints ?> more points</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-inactive">Cannot redeem</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if($canRedeem): ?>
                                    <form action="../../Controllers/RewardsController.php?action=redeem&id=<?= $r['id'] ?>" method="POST" onsubmit="return confirm('Redeem \"<?= htmlspecialchars($r['title']) ?>\" for <?= $rewardCost ?> points?');">
                                        <button type="submit" class="btn redeem-btn w-100">
                                            <i class="fas fa-gift me-2"></i>
                                            Redeem (<?= $rewardCost ?> pts)
                                        </button>
                                    </form>
                                <?php elseif($needsApproval): ?>
                                    <form action="../../Controllers/RewardsController.php?action=request_approval&id=<?= $r['id'] ?>" method="POST" onsubmit="return confirm('Request teacher approval for \"<?= htmlspecialchars($r['title']) ?>\"? You need <?= $neededPoints ?> more points.');">
                                        <input type="hidden" name="message" value="Need <?= $neededPoints ?> more points to redeem <?= htmlspecialchars($r['title']) ?>">
                                        <button type="submit" class="btn request-btn w-100">
                                            <i class="fas fa-hand-paper me-2"></i>
                                            Request Approval (Need <?= $neededPoints ?> pts)
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-disabled w-100" disabled>
                                        <?php if(!$isActive): ?>
                                            <i class="fas fa-times me-2"></i>
                                            Inactive Reward
                                        <?php elseif(!$isAvailable): ?>
                                            <i class="fas fa-times me-2"></i>
                                            Out of Stock
                                        <?php elseif(!$hasTier): ?>
                                            <i class="fas fa-lock me-2"></i>
                                            Requires <?= $rewardMinTier ?> Tier
                                        <?php elseif(!$canAfford): ?>
                                            <i class="fas fa-lock me-2"></i>
                                            Need <?= $neededPoints ?> more points
                                        <?php else: ?>
                                            <i class="fas fa-lock me-2"></i>
                                            Cannot Redeem
                                        <?php endif; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<script>
// Simple page refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);

// Category Navigation
const categorySections = document.querySelectorAll('.category-section');

function showCategory(category, button) {
    // Hide all category sections
    categorySections.forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected category
    document.getElementById('category-' + category).classList.add('active');
    
    // Update active button
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    button.classList.add('active');
}

// Initialize first category as active
document.addEventListener('DOMContentLoaded', function() {
    // First category is already active by default
    // Ensure the first category button is active
    const firstButton = document.querySelector('.category-btn');
    if (firstButton) {
        firstButton.classList.add('active');
    }
});
</script>

</body>
</html>
