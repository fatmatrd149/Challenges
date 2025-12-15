<?php 
if(session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Models/Challenges.php';
require_once __DIR__ . '/../../Models/Rewards.php';
require_once __DIR__ . '/../../Models/Points.php';

$studentID = $_SESSION['userID'] ?? 3;
$availableChallenges = Challenges::getAvailableChallenges($pdo, $studentID);
$timeLimitedChallenges = Challenges::getTimeLimitedChallenges($pdo);
$recurringChallenges = Challenges::getRecurringChallenges($pdo);

$tierProgress = Challenges::getTierProgress($pdo, $studentID);
$currentTier = $tierProgress['current_tier'];
$balance = Points::getBalance($pdo, $studentID);
$nextTier = $tierProgress['next_tier'];

// Get current level and sort option
$currentLevel = isset($_GET['level']) ? (int)$_GET['level'] : 0;
$sortBy = $_GET['sort'] ?? 'default';

// Get challenges for current level with sorting
$levelChallenges = Challenges::getChallengesByLevelSorted($pdo, $currentLevel, $studentID, $sortBy);

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Store scroll position in session for persistence
if (isset($_GET['scroll'])) {
    $_SESSION['challenges_scroll'] = (int)$_GET['scroll'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Challenges - Student</title>
<link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
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
.challenge-card { 
    transition: all 0.3s ease; 
    border-radius: 15px; 
    background: white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border: none;
    height: 100%;
    border-left: 5px solid #2563eb;
}
.challenge-card:hover { 
    transform: translateY(-10px); 
    box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
}

.start-btn { 
    background: #2563eb;
    border: none; 
    border-radius: 8px; 
    padding: 10px 20px; 
    font-weight: 600; 
    color: white;
    transition: all 0.3s ease;
}
.start-btn:hover { 
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}

.locked-btn { 
    background: #6b7280;
    border: none; 
    border-radius: 8px; 
    padding: 10px 20px; 
    font-weight: 600; 
    color: white;
    cursor: not-allowed;
}

.completed-btn { 
    background: #059669;
    border: none; 
    border-radius: 8px; 
    padding: 10px 20px; 
    font-weight: 600; 
    color: white;
    cursor: default;
}

.points-badge {
    background: #fcd34d;
    color: #92400e; 
    padding: 8px 16px; 
    border-radius: 20px; 
    font-weight: 700; 
    font-size: 0.9rem;
}

.tier-progress-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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
    font-size: 2rem;
    margin-bottom: 10px;
}

.special-challenges-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-left: 5px solid #f59e0b;
}
.time-limited-badge {
    background: #fecaca;
    color: #dc2626;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}
.recurring-badge {
    background: #bbf7d0;
    color: #166534;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

.tree-view {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.tree-path { margin-bottom: 2rem; }
.path-header { 
    background: #dbeafe; 
    color: #1e40af; 
    padding: 1rem; 
    border-radius: 12px; 
    margin-bottom: 1rem;
}
.challenge-node { 
    display: flex; 
    align-items: center; 
    margin: 0.5rem 0; 
    padding: 1rem; 
    background: #f8fafc; 
    border-radius: 10px; 
    border-left: 4px solid #2563eb;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
}
.challenge-node:hover {
    transform: translateX(5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.challenge-node.completed { 
    border-left-color: #059669; 
    background: #f0fdf4;
}
.challenge-node.locked { 
    border-left-color: #6b7280; 
    background: #f9fafb; 
    opacity: 0.7; 
}
.node-status { 
    margin-right: 1rem; 
    font-size: 1.2rem; 
}

#challengeConfirm {
    display: none; 
    position: fixed; 
    top: 0; left: 0; 
    width: 100%; height: 100%; 
    background: rgba(0,0,0,0.7); 
    z-index: 9999;
    backdrop-filter: blur(5px);
}
.confirm-box {
    position: absolute; 
    top: 50%; left: 50%; 
    transform: translate(-50%, -50%);
    background: white; 
    padding: 40px; 
    border-radius: 20px; 
    text-align: center; 
    max-width: 500px; 
    width: 90%;
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
    border-left: 5px solid #2563eb;
}
.motivational-icon { font-size: 4rem; margin-bottom: 20px; color: #2563eb; }
.btn-yes { 
    background: #2563eb; 
    border: none; 
    border-radius: 8px; 
    padding: 12px 30px; 
    font-size: 16px; 
    font-weight: 600; 
    color: white; 
    margin: 10px; 
    transition: all 0.3s ease;
}
.btn-yes:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}
.btn-no { 
    background: #e5e7eb; 
    border: none; 
    border-radius: 8px; 
    padding: 12px 30px; 
    font-size: 16px; 
    font-weight: 600; 
    color: #333; 
    margin: 10px; 
    transition: all 0.3s ease;
}
.btn-no:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.section-title {
    color: #1f2937;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 3px solid #2563eb;
}

.message-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    background: #dc2626;
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    font-weight: bold;
    box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
    font-size: 1rem;
    display: none;
}
.message-toast.success {
    background: #059669;
    box-shadow: 0 10px 25px rgba(5, 150, 105, 0.3);
}
.level-pagination {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin: 30px 0 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.level-nav-btn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.level-nav-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}
.level-nav-btn:disabled {
    background: #e5e7eb;
    color: #6b7280;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.level-nav-btn:disabled:hover {
    transform: none;
    box-shadow: none;
}
.level-indicator {
    text-align: center;
}
.level-indicator .badge {
    background: #2563eb;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
}
.sort-options {
    background: white;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.sort-btn {
    background: #e5e7eb;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    margin: 0 5px;
    transition: all 0.3s ease;
    text-decoration: none !important; /* Remove underline */
    color: #374151;
    display: inline-block;
}
.sort-btn.active {
    background: #2563eb;
    color: white;
}
.sort-btn:hover {
    transform: translateY(-2px);
    text-decoration: none !important; /* Remove underline on hover */
}
.category-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    margin: 2px;
}
.skill-tag {
    display: inline-block;
    background: #dcfce7;
    color: #166534;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    margin: 2px;
}
.rating-stars {
    color: #fbbf24;
    font-size: 0.9rem;
}
.rating-count {
    color: #6b7280;
    font-size: 0.8rem;
}
.category-filter {
    background: white;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.category-btn {
    background: #e5e7eb;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    margin: 0 5px;
    transition: all 0.3s ease;
}
.category-btn.active {
    background: #059669;
    color: white;
}
.category-btn:hover {
    transform: translateY(-2px);
}
.rating-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    backdrop-filter: blur(5px);
}
.rating-container {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 40px;
    border-radius: 20px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
}
.star-rating {
    font-size: 2rem;
    color: #e5e7eb;
    cursor: pointer;
    margin-bottom: 20px;
}
.star-rating .active {
    color: #fbbf24;
}
html {
    scroll-behavior: smooth;
}
</style>
</head>
<body>

<?php if ($success_message): ?>
<div class="message-toast success" style="display: block;">
    <?= htmlspecialchars($success_message) ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="message-toast" style="display: block;">
    <?= htmlspecialchars($error_message) ?>
</div>
<?php endif; ?>

<!-- Rating Modal -->
<div id="ratingModal" class="rating-modal">
    <div class="rating-container">
        <h3 class="section-title">
            <i class="fas fa-star me-2"></i>
            Rate this Challenge
        </h3>
        <div class="text-center mb-4">
            <div class="star-rating mb-3" id="ratingStars">
                <i class="far fa-star" data-rating="1"></i>
                <i class="far fa-star" data-rating="2"></i>
                <i class="far fa-star" data-rating="3"></i>
                <i class="far fa-star" data-rating="4"></i>
                <i class="far fa-star" data-rating="5"></i>
            </div>
            <input type="hidden" id="selectedRating" value="0">
            <input type="hidden" id="ratingChallengeId">
            <textarea id="ratingComment" class="form-control" rows="3" placeholder="Share your experience with this challenge..."></textarea>
        </div>
        <div class="d-flex justify-content-end gap-3">
            <button type="button" onclick="hideRatingModal()" class="btn btn-outline-secondary px-4">Cancel</button>
            <button type="button" onclick="submitRating()" class="btn start-btn px-4">
                <i class="fas fa-paper-plane me-2"></i>Submit Rating
            </button>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="challengeConfirm">
    <div class="confirm-box">
        <div class="motivational-icon">🚀</div>
        <h3 id="confirmTitle" style="margin-bottom: 15px; color: #1f2937;"></h3>
        <p style="font-size: 18px; margin-bottom: 25px; color: #4b5563;">
            <strong>Are you ready for this challenge?</strong><br>
            <small>Your success awaits! Earn <span id="confirmPoints" style="color: #2563eb; font-weight: bold;"></span> points</small>
        </p>
        <div>
            <button class="btn-yes" id="confirmYes">
                <i class="fas fa-rocket me-2"></i>Yes, Start Challenge!
            </button>
            <button class="btn-no" id="confirmNo">
                <i class="fas fa-pause me-2"></i>Maybe Later
            </button>
        </div>
    </div>
</div>

<main class="container py-5">
    <!-- Tier Progress Section -->
    <div class="tier-progress-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="section-title">
                    <i class="fas fa-trophy me-2"></i>Your Learning Journey
                </h3>
                <?php if ($currentTier): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Current Tier: <?= $currentTier['badge_name'] ?> <?= $currentTier['name'] ?></span>
                        <span class="fw-bold" style="color: #2563eb;"><?= $balance ?> Points</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= $tierProgress['progress'] ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Progress: <?= round($tierProgress['progress'], 1) ?>%</small>
                        <?php if ($nextTier): ?>
                            <small class="text-muted"><?= $tierProgress['points_to_next'] ?> points to <?= $nextTier['name'] ?></small>
                        <?php else: ?>
                            <small class="text-success fw-bold">🏆 Highest tier achieved!</small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Complete challenges to earn points and unlock reward tiers!</p>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= ($balance / 25) * 100 ?>%"></div>
                    </div>
                    <small class="text-muted"><?= $balance ?>/25 points to Bronze tier</small>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-center">
                <div class="tier-badge">
                    <?= $currentTier ? $currentTier['badge_name'] : '🎯' ?>
                </div>
                <div class="fw-bold" style="color: #2563eb;">
                    <?= $currentTier ? $currentTier['name'] . ' Tier' : 'Getting Started' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Special Challenges Section -->
    <?php if (!empty($timeLimitedChallenges) || !empty($recurringChallenges)): ?>
    <div class="special-challenges-section">
        <h4 class="section-title">
            <i class="fas fa-bolt me-2"></i>Special Challenges
        </h4>
        <div class="row g-4">
            <!-- Time-Limited Challenges -->
            <?php if (!empty($timeLimitedChallenges)): ?>
            <div class="col-md-6">
                <h5 class="fw-semibold mb-3" style="color: #dc2626;">
                    <i class="fas fa-clock me-2"></i>Time-Limited
                </h5>
                <?php foreach ($timeLimitedChallenges as $challenge): ?>
                    <?php 
                    $completed = false;
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'challenge_complete' AND target_id = ?");
                    $stmt->execute([$studentID, $challenge['id']]);
                    $completed = $stmt->fetchColumn() > 0;
                    
                    if (!$completed): 
                    $rating = Challenges::getAverageRating($pdo, $challenge['id']);
                    ?>
                    <div class="challenge-node mb-3">
                        <div class="node-status">⏰</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($challenge['title']) ?></h6>
                            <div class="d-flex align-items-center mb-1">
                                <?php if ($rating['average'] > 0): ?>
                                <div class="rating-stars me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?= $i <= $rating['average'] ? '#fbbf24' : '#e5e7eb' ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="rating-count">(<?= $rating['count'] ?>)</small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= $challenge['points'] ?> points 
                                <?php if ($challenge['time_limit_minutes']): ?>
                                    • <?= $challenge['time_limit_minutes'] ?> min limit
                                <?php endif; ?>
                            </small>
                            <?php if ($challenge['category']): ?>
                                <div class="mt-1">
                                    <span class="category-badge">
                                        <i class="fas fa-tag me-1"></i><?= htmlspecialchars($challenge['category']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($challenge['skill_tags']): ?>
                                <div class="mt-1">
                                    <?php 
                                    $tags = explode(',', $challenge['skill_tags']);
                                    foreach ($tags as $tag):
                                        $trimmedTag = trim($tag);
                                        if (!empty($trimmedTag)):
                                    ?>
                                        <span class="skill-tag"><?= htmlspecialchars($trimmedTag) ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="btn start-btn" 
                                onclick="startChallenge(<?= $challenge['id'] ?>, '<?= htmlspecialchars(addslashes($challenge['title'])) ?>', <?= (int)$challenge['points'] ?>)">
                            Start
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recurring Challenges -->
            <?php if (!empty($recurringChallenges)): ?>
            <div class="col-md-6">
                <h5 class="fw-semibold mb-3" style="color: #059669;">
                    <i class="fas fa-sync-alt me-2"></i>Recurring
                </h5>
                <?php foreach ($recurringChallenges as $challenge): ?>
                    <?php 
                    $completed = false;
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'challenge_complete' AND target_id = ?");
                    $stmt->execute([$studentID, $challenge['id']]);
                    $completed = $stmt->fetchColumn() > 0;
                    
                    if (!$completed): 
                    $rating = Challenges::getAverageRating($pdo, $challenge['id']);
                    ?>
                    <div class="challenge-node mb-3">
                        <div class="node-status">🔄</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($challenge['title']) ?></h6>
                            <div class="d-flex align-items-center mb-1">
                                <?php if ($rating['average'] > 0): ?>
                                <div class="rating-stars me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?= $i <= $rating['average'] ? '#fbbf24' : '#e5e7eb' ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="rating-count">(<?= $rating['count'] ?>)</small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?= $challenge['points'] ?> points • <?= $challenge['recurrence_pattern'] ? htmlspecialchars(ucfirst($challenge['recurrence_pattern'])) : '' ?></small>
                            <?php if ($challenge['category']): ?>
                                <div class="mt-1">
                                    <span class="category-badge">
                                        <i class="fas fa-tag me-1"></i><?= htmlspecialchars($challenge['category']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($challenge['skill_tags']): ?>
                                <div class="mt-1">
                                    <?php 
                                    $tags = explode(',', $challenge['skill_tags']);
                                    foreach ($tags as $tag):
                                        $trimmedTag = trim($tag);
                                        if (!empty($trimmedTag)):
                                    ?>
                                        <span class="skill-tag"><?= htmlspecialchars($trimmedTag) ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="btn start-btn" 
                                onclick="startChallenge(<?= $challenge['id'] ?>, '<?= htmlspecialchars(addslashes($challenge['title'])) ?>', <?= (int)$challenge['points'] ?>)">
                            Start
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Challenge Tree View with Level Pagination and Sorting -->
    <div class="tree-view" id="challengesTree">
        <h3 class="section-title">
            <i class="fas fa-sitemap me-2"></i>
            Learning Paths
        </h3>

        <!-- Sorting Options -->
        <div class="sort-options">
            <h6 class="fw-semibold mb-3">Sort Challenges:</h6>
            <div class="d-flex flex-wrap">
                <a href="javascript:void(0);" onclick="changeSort('default')" class="sort-btn <?= $sortBy == 'default' ? 'active' : '' ?>">
                    <i class="fas fa-sort me-1"></i>Default Order
                </a>
                <a href="javascript:void(0);" onclick="changeSort('points_high')" class="sort-btn <?= $sortBy == 'points_high' ? 'active' : '' ?>">
                    <i class="fas fa-sort-amount-down me-1"></i>Most Points
                </a>
                <a href="javascript:void(0);" onclick="changeSort('points_low')" class="sort-btn <?= $sortBy == 'points_low' ? 'active' : '' ?>">
                    <i class="fas fa-sort-amount-up me-1"></i>Fewest Points
                </a>
                <a href="javascript:void(0);" onclick="changeSort('newest')" class="sort-btn <?= $sortBy == 'newest' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-plus me-1"></i>Newest First
                </a>
            </div>
        </div>

        <!-- Level Challenges -->
        <div class="tree-path">
            <div class="path-header">
                <h4 class="mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                    Level <?= $currentLevel ?> Challenges
                </h4>
            </div>
            
            <?php if (!empty($levelChallenges)): 
                $completedCount = 0;
                foreach ($levelChallenges as $c) {
                    if ($c['completed']) $completedCount++;
                }
                $progress = count($levelChallenges) > 0 ? ($completedCount / count($levelChallenges)) * 100 : 0;
                $canAccess = Challenges::canAccessLevel($pdo, $studentID, $currentLevel);
            ?>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Progress: <?= $completedCount ?>/<?= count($levelChallenges) ?> completed</small>
                    <small class="text-muted"><?= round($progress) ?>%</small>
                </div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                </div>
                
                <?php if (!$canAccess && $currentLevel > 0): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-lock me-2"></i>
                        Complete Level <?= $currentLevel - 1 ?> to unlock these challenges!
                    </div>
                <?php endif; ?>
                
                <div class="row g-3">
                    <?php foreach ($levelChallenges as $c): 
                        $rating = Challenges::getAverageRating($pdo, $c['id']);
                        $userRating = Challenges::getUserRating($pdo, $c['id'], $studentID);
                    ?>
                        <div class="col-lg-6">
                            <div class="challenge-node <?= $c['completed'] ? 'completed' : ($c['unlocked'] && $canAccess ? '' : 'locked') ?>">
                                <div class="node-status">
                                    <?php if ($c['completed']): ?>
                                        ✅
                                    <?php elseif ($c['unlocked'] && $canAccess): ?>
                                        ⭐
                                    <?php else: ?>
                                        🔒
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($c['title']) ?></h6>
                                    
                                    <!-- Rating Display -->
                                    <div class="d-flex align-items-center mb-1">
                                        <?php if ($rating['average'] > 0): ?>
                                        <div class="rating-stars me-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star" style="color: <?= $i <= $rating['average'] ? '#fbbf24' : '#e5e7eb' ?>;"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="rating-count">(<?= $rating['count'] ?>)</small>
                                        <?php endif; ?>
                                        
                                        <!-- Rate Button -->
                                        <?php if ($c['completed'] && !$userRating): ?>
                                            <button class="btn btn-sm start-btn ms-2" onclick="openRatingModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')">
                                                <i class="fas fa-star me-1"></i>Rate
                                            </button>
                                        <?php elseif ($userRating): ?>
                                            <span class="badge bg-light text-dark ms-2">
                                                <i class="fas fa-star text-warning me-1"></i>You rated: <?= $userRating['rating'] ?>/5
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <small class="text-muted"><?= $c['points'] ?> points • <?= htmlspecialchars($c['type']) ?></small>
                                    
                                    <!-- Category and Skill Tags -->
                                    <?php if ($c['category'] || $c['skill_tags']): ?>
                                        <div class="mt-1">
                                            <?php if ($c['category']): ?>
                                                <span class="category-badge">
                                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($c['category']) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($c['skill_tags']): 
                                                $tags = explode(',', $c['skill_tags']);
                                                foreach ($tags as $tag):
                                                    $trimmedTag = trim($tag);
                                                    if (!empty($trimmedTag)):
                                            ?>
                                                <span class="skill-tag"><?= htmlspecialchars($trimmedTag) ?></span>
                                            <?php 
                                                    endif;
                                                endforeach; 
                                            endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($c['completed']): ?>
                                        <button class="btn completed-btn" disabled>
                                            <i class="fas fa-check me-2"></i>Completed
                                        </button>
                                    <?php elseif ($c['unlocked'] && $canAccess): ?>
                                        <button class="btn start-btn" 
                                                onclick="startChallenge(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>', <?= (int)$c['points'] ?>)">
                                            <i class="fas fa-play-circle me-2"></i>
                                            Start
                                        </button>
                                    <?php else: ?>
                                        <button class="btn locked-btn" disabled>
                                            <i class="fas fa-lock me-2"></i>
                                            <?= $currentLevel > 0 ? 'Complete Level ' . ($currentLevel - 1) . ' First' : 'Locked' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No challenges available at this level</h4>
                    <p class="text-muted">Complete prerequisite challenges to unlock more!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Level Pagination Controls - MOVED TO BOTTOM -->
        <div class="level-pagination">
            <div>
                <?php if ($currentLevel > 0): ?>
                    <button class="level-nav-btn" onclick="changeLevel(-1)">
                        <i class="fas fa-chevron-left"></i>
                        Previous Level (<?= $currentLevel - 1 ?>)
                    </button>
                <?php else: ?>
                    <button class="level-nav-btn" disabled>
                        <i class="fas fa-chevron-left"></i>
                        Previous Level
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="level-indicator">
                <span class="badge">
                    <i class="fas fa-layer-group me-1"></i>
                    Level <?= $currentLevel ?>
                    <?php if (!empty($levelChallenges)): ?>
                        <span class="badge bg-white text-dark ms-1">
                            <?= count($levelChallenges) ?> challenges
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            
            <div>
                <?php if ($currentLevel < 2): ?>
                    <button class="level-nav-btn" onclick="changeLevel(1)">
                        Next Level (<?= $currentLevel + 1 ?>)
                        <i class="fas fa-chevron-right"></i>
                    </button>
                <?php else: ?>
                    <button class="level-nav-btn" disabled>
                        Next Level
                        <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Available Challenges Grid -->
    <div class="mb-4">
        <h3 class="section-title">
            <i class="fas fa-bolt me-2"></i>Available Challenges
        </h3>
        <p class="text-muted">Ready to start right now!</p>
    </div>

    <div class="row g-4">
        <?php if ($availableChallenges): ?>
            <?php foreach ($availableChallenges as $c): 
                $rating = Challenges::getAverageRating($pdo, $c['id']);
                $userRating = Challenges::getUserRating($pdo, $c['id'], $studentID);
            ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="challenge-card card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h3 class="card-title mb-0 fw-bold" style="color: #2563eb;">
                                    <i class="fas fa-fire me-2"></i>
                                    <?= htmlspecialchars($c['title']) ?>
                                </h3>
                                <span class="points-badge">
                                    <?= (int)$c['points'] ?>
                                </span>
                            </div>
                            
                            <!-- Rating Display -->
                            <div class="d-flex align-items-center mb-2">
                                <?php if ($rating['average'] > 0): ?>
                                <div class="rating-stars me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?= $i <= $rating['average'] ? '#fbbf24' : '#e5e7eb' ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="rating-count">(<?= $rating['count'] ?>)</small>
                                <?php endif; ?>
                            </div>
                            
                            <p class="card-text mb-3 text-muted"><?= htmlspecialchars($c['description'] ?? $c['criteria'] ?? 'No description') ?></p>
                            
                            <!-- Category and Skills -->
                            <?php if ($c['category'] || $c['skill_tags']): ?>
                            <div class="mb-3">
                                <?php if ($c['category']): ?>
                                    <span class="category-badge mb-1">
                                        <i class="fas fa-tag me-1"></i><?= htmlspecialchars($c['category']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($c['skill_tags']): 
                                    $tags = explode(',', $c['skill_tags']);
                                    foreach (array_slice($tags, 0, 3) as $tag):
                                        $trimmedTag = trim($tag);
                                        if (!empty($trimmedTag)):
                                ?>
                                    <span class="skill-tag"><?= htmlspecialchars($trimmedTag) ?></span>
                                <?php 
                                        endif;
                                    endforeach; 
                                    if (count($tags) > 3):
                                ?>
                                    <span class="skill-tag">+<?= count($tags) - 3 ?> more</span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <span class="badge bg-success me-2"><?= htmlspecialchars($c['type']) ?></span>
                                <span class="badge bg-info text-white">Ready to Start!</span>
                            </div>
                            
                            <!-- Rate Button for Completed Challenges -->
                            <?php 
                            $completed = false;
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'challenge_complete' AND target_id = ?");
                            $stmt->execute([$studentID, $c['id']]);
                            $completed = $stmt->fetchColumn() > 0;
                            
                            if ($completed && !$userRating): ?>
                                <button class="btn btn-outline-primary w-100 mb-2" onclick="openRatingModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')">
                                    <i class="fas fa-star me-2"></i>Rate this Challenge
                                </button>
                            <?php elseif ($userRating): ?>
                                <div class="alert alert-light mb-2">
                                    <i class="fas fa-star text-warning me-2"></i>
                                    You rated this: <?= $userRating['rating'] ?>/5
                                </div>
                            <?php endif; ?>
                            
                            <button class="btn start-btn w-100" 
                                    onclick="startChallenge(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>', <?= (int)$c['points'] ?>)">
                                <i class="fas fa-play-circle me-2"></i>
                                Start Challenge
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-5x mb-4 text-muted"></i>
                    <h3 class="text-muted">No challenges available</h3>
                    <p class="text-muted">Complete prerequisite challenges to unlock more!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
let currentChallenge = null;
let currentLevel = <?= $currentLevel ?>;
let currentSort = '<?= $sortBy ?>';

function startChallenge(id, title, points) {
    currentChallenge = {
        id: id,
        title: title,
        points: points
    };
    
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmPoints').textContent = points;
    document.getElementById('challengeConfirm').style.display = 'block';
}

function changeLevel(direction) {
    const newLevel = currentLevel + direction;
    
    if (newLevel >= 0 && newLevel <= 2) {
        // Store current scroll position
        const scrollPos = window.scrollY;
        window.location.href = `?level=${newLevel}&sort=${currentSort}&scroll=${scrollPos}#challengesTree`;
    }
}

function changeSort(sortType) {
    currentSort = sortType;
    // Store current scroll position
    const scrollPos = window.scrollY;
    window.location.href = `?level=${currentLevel}&sort=${sortType}&scroll=${scrollPos}#challengesTree`;
}

function openRatingModal(challengeId, challengeTitle) {
    document.getElementById('ratingChallengeId').value = challengeId;
    document.getElementById('ratingModal').style.display = 'block';
    
    // Reset stars
    document.querySelectorAll('#ratingStars i').forEach(star => {
        star.className = 'far fa-star';
    });
    document.getElementById('selectedRating').value = 0;
    document.getElementById('ratingComment').value = '';
}

function hideRatingModal() {
    document.getElementById('ratingModal').style.display = 'none';
}

function submitRating() {
    const challengeId = document.getElementById('ratingChallengeId').value;
    const rating = document.getElementById('selectedRating').value;
    const comment = document.getElementById('ratingComment').value;
    
    if (rating == 0) {
        alert('Please select a rating');
        return;
    }
    
    // Submit via AJAX for real-time update
    const formData = new FormData();
    formData.append('challenge_id', challengeId);
    formData.append('rating', rating);
    formData.append('comment', comment);
    
    fetch('../../Controllers/ChallengesController.php?action=rate', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success')) {
            hideRatingModal();
            location.reload(); // Refresh to show updated rating
        } else {
            alert('Failed to submit rating');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to submit rating');
    });
}

document.getElementById('confirmYes').addEventListener('click', function() {
    document.getElementById('challengeConfirm').style.display = 'none';
    
    window.location.href = '../../Controllers/ChallengesController.php?action=complete&id=' + currentChallenge.id;
});

document.getElementById('confirmNo').addEventListener('click', function() {
    document.getElementById('challengeConfirm').style.display = 'none';
});

document.getElementById('challengeConfirm').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// Star rating functionality
document.querySelectorAll('#ratingStars i').forEach(star => {
    star.addEventListener('click', function() {
        const rating = parseInt(this.dataset.rating);
        document.getElementById('selectedRating').value = rating;
        
        document.querySelectorAll('#ratingStars i').forEach(s => {
            if (parseInt(s.dataset.rating) <= rating) {
                s.className = 'fas fa-star';
                s.style.color = '#fbbf24';
            } else {
                s.className = 'far fa-star';
                s.style.color = '#e5e7eb';
            }
        });
    });
    
    star.addEventListener('mouseover', function() {
        const rating = parseInt(this.dataset.rating);
        document.querySelectorAll('#ratingStars i').forEach(s => {
            if (parseInt(s.dataset.rating) <= rating) {
                s.style.color = '#fbbf24';
            } else {
                s.style.color = '#e5e7eb';
            }
        });
    });
    
    star.addEventListener('mouseout', function() {
        const currentRating = parseInt(document.getElementById('selectedRating').value);
        document.querySelectorAll('#ratingStars i').forEach(s => {
            if (parseInt(s.dataset.rating) <= currentRating) {
                s.style.color = '#fbbf24';
            } else {
                s.style.color = '#e5e7eb';
            }
        });
    });
});

// Restore scroll position on page load
window.addEventListener('load', function() {
    // Check if there's a saved scroll position in session
    const scrollPos = <?= isset($_SESSION['challenges_scroll']) ? $_SESSION['challenges_scroll'] : 'null' ?>;
    if (scrollPos && scrollPos > 0) {
        setTimeout(function() {
            window.scrollTo(0, scrollPos);
            // Clear the stored position
            <?php unset($_SESSION['challenges_scroll']); ?>
        }, 100);
    }
    
    // Also scroll to tree if hash present
    if (window.location.hash === '#challengesTree') {
        setTimeout(function() {
            const element = document.getElementById('challengesTree');
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
            }
        }, 200);
    }
});

setTimeout(() => {
    const toasts = document.querySelectorAll('.message-toast');
    toasts.forEach(toast => toast.style.display = 'none');
}, 4000);
</script>
</body>
</html>
