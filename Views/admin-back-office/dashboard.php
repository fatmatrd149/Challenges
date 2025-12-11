<?php
if(session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Models/Challenges.php';
require_once __DIR__ . '/../../Models/Rewards.php';
require_once __DIR__ . '/../../Models/Points.php';

$adminID = $_SESSION['userID'] ?? 1;

// Get user counts
$stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt->execute();
$roleCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$studentCount = 0;
$teacherCount = 0;
foreach ($roleCounts as $role) {
    if ($role['role'] == 'student') $studentCount = $role['count'];
    if ($role['role'] == 'teacher') $teacherCount = $role['count'];
}

// Get challenges and rewards data
$totalChallenges = Challenges::getAll($pdo);
$totalRewards = Rewards::getAll($pdo);
$popularRewards = [];
$timeLimitedChallenges = Challenges::getTimeLimitedChallenges($pdo);
$recentAchievements = RewardTiers::getRecentAchievements($pdo, 5);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard | EduMind+</title>
  <link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
    }
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      border-left: 5px solid #2563eb;
      transition: all 0.3s ease;
      height: 100%;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .feature-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      border-left: 5px solid #059669;
      height: 100%;
    }
    .quick-action-btn {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 12px 20px;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block;
      text-align: center;
      margin-bottom: 10px;
    }
    .quick-action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
      color: white;
    }
    .achievement-item {
      display: flex;
      align-items: center;
      padding: 12px;
      background: #f8fafc;
      border-radius: 8px;
      margin-bottom: 10px;
      border-left: 4px solid #2563eb;
    }
    .challenge-mini-card {
      background: #f8fafc;
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 8px;
      border-left: 3px solid #10b981;
    }
    .navbar {
      background: #2563eb !important;
    }
    .navbar-brand {
      color: white !important;
      font-weight: 600;
    }
    .nav-link {
      color: rgba(255,255,255,0.9) !important;
    }
    .nav-link:hover, .nav-link.active {
      color: white !important;
      background: rgba(255,255,255,0.1);
      border-radius: 4px;
    }
    .bg-primary {
      background-color: #2563eb !important;
    }
    .btn-outline-light {
      border-color: rgba(255,255,255,0.5);
      color: white;
    }
    .btn-outline-light:hover {
      background: white;
      color: #2563eb;
      border-color: white;
    }
    .text-primary {
      color: #2563eb !important;
    }
    .text-success {
      color: #059669 !important;
    }
    .text-warning {
      color: #f59e0b !important;
    }
    .text-info {
      color: #3b82f6 !important;
    }
    .bg-success {
      background-color: #059669 !important;
    }
    .bg-warning {
      background-color: #f59e0b !important;
    }
    .bg-info {
      background-color: #3b82f6 !important;
    }
    .border-primary {
      border-color: #2563eb !important;
    }
    .btn-primary {
      background-color: #2563eb;
      border-color: #2563eb;
    }
    .btn-primary:hover {
      background-color: #1d4ed8;
      border-color: #1d4ed8;
    }
  </style>
</head>
<body data-page="admin-dashboard">
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">EduMind+ Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
          <li class="nav-item"><a class="nav-link" href="roles.php">Roles</a></li>
          <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
          <li class="nav-item"><a class="nav-link" href="Challenges.php">Challenges</a></li>
          <li class="nav-item"><a class="nav-link" href="Rewards.php">Rewards</a></li>
          <li class="nav-item"><a class="nav-link" href="quiz-reports.php">Quiz Reports</a></li>
          <li class="nav-item"><a class="nav-link" href="logs.php">Logs</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
          <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
        </ul>
        <button id="logoutBtn" class="btn btn-outline-light btn-sm">Logout</button>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <!-- Quick Stats Row -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="text-muted small">Students</div>
          <div id="sCount" class="h4 mb-0"><?= $studentCount ?></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="text-muted small">Teachers</div>
          <div id="tCount" class="h4 mb-0"><?= $teacherCount ?></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="text-muted small">Challenges</div>
          <div id="cCount" class="h4 mb-0"><?= count($totalChallenges) ?></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="text-muted small">Rewards</div>
          <div id="pCount" class="h4 mb-0"><?= count($totalRewards) ?></div>
        </div>
      </div>
    </div>

    <!-- Challenges & Rewards Integration -->
    <div class="row g-4">
      <!-- Left Column -->
      <div class="col-lg-8">
        <!-- Challenges Overview -->
        <div class="feature-card">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h5 mb-0">
              <i class="fas fa-list-check me-2 text-success"></i>
              Challenges Overview
            </h3>
            <a href="Challenges.php" class="btn btn-sm btn-outline-primary">Manage All</a>
          </div>
          
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="text-center p-3 bg-light rounded">
                <div class="h4 text-primary mb-1"><?= count($totalChallenges) ?></div>
                <small class="text-muted">Total Challenges</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center p-3 bg-light rounded">
                <div class="h4 text-warning mb-1"><?= count($timeLimitedChallenges) ?></div>
                <small class="text-muted">Time-Limited</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-center p-3 bg-light rounded">
                <div class="h4 text-info mb-1"><?= count(array_filter($totalChallenges, fn($c) => $c['schedule_type'] == 'recurring')) ?></div>
                <small class="text-muted">Recurring</small>
              </div>
            </div>
          </div>

          <?php if (!empty($totalChallenges)): ?>
          <div class="mt-3">
            <h6 class="text-muted mb-2">Recent Challenges</h6>
            <?php $recentChallenges = array_slice($totalChallenges, 0, 3); ?>
            <?php foreach ($recentChallenges as $challenge): ?>
              <div class="challenge-mini-card">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <strong class="small"><?= htmlspecialchars($challenge['title']) ?></strong>
                    <div class="text-muted smaller"><?= $challenge['points'] ?> pts • <?= $challenge['type'] ?></div>
                  </div>
                  <span class="badge bg-<?= $challenge['status'] == 'Active' ? 'success' : 'secondary' ?>">
                    <?= $challenge['status'] ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Recent Achievements -->
        <?php if (!empty($recentAchievements)): ?>
        <div class="feature-card">
          <h3 class="h5 mb-3">
            <i class="fas fa-trophy me-2 text-warning"></i>
            Recent Tier Achievements
          </h3>
          <?php foreach ($recentAchievements as $achievement): ?>
            <div class="achievement-item">
              <div class="me-3" style="font-size: 1.5rem;">
                <?= htmlspecialchars($achievement['badge_name']) ?>
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold small"><?= htmlspecialchars($achievement['student_name']) ?></div>
                <div class="text-muted smaller">
                  Reached <?= $achievement['tier_name'] ?> Tier • <?= date('M j', strtotime($achievement['created_at'])) ?>
                </div>
              </div>
              <div class="text-end">
                <div class="fw-bold text-primary"><?= $achievement['student_points'] ?> pts</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right Column -->
      <div class="col-lg-4">
        <!-- Rewards Overview -->
        <div class="feature-card">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h5 mb-0">
              <i class="fas fa-coins me-2 text-warning"></i>
              Rewards Overview
            </h3>
            <a href="Rewards.php" class="btn btn-sm btn-outline-primary">Manage All</a>
          </div>
          
          <div class="text-center p-3 bg-light rounded mb-3">
            <div class="h4 text-warning mb-1"><?= count($totalRewards) ?></div>
            <small class="text-muted">Total Rewards</small>
          </div>

          <?php if (!empty($popularRewards)): ?>
          <div class="mt-3">
            <h6 class="text-muted mb-2">Popular Rewards</h6>
            <?php foreach ($popularRewards as $reward): ?>
              <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                <div>
                  <strong class="small"><?= htmlspecialchars($reward['title']) ?></strong>
                  <div class="text-muted smaller"><?= $reward['pointsCost'] ?> pts</div>
                </div>
                <span class="badge bg-primary">
                  <?= $reward['redemption_count'] ?> redeemed
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="feature-card">
          <h3 class="h5 mb-3">
            <i class="fas fa-bolt me-2 text-primary"></i>
            Quick Actions
          </h3>
          <a href="Challenges.php" class="quick-action-btn">
            <i class="fas fa-plus me-2"></i>Create Challenge
          </a>
          <a href="Rewards.php" class="quick-action-btn" style="background: #f59e0b;">
            <i class="fas fa-gift me-2"></i>Create Reward
          </a>
          <a href="Challenges.php" class="quick-action-btn" style="background: #059669;">
            <i class="fas fa-list-check me-2"></i>Manage Challenges
          </a>
          <a href="Rewards.php" class="quick-action-btn" style="background: #8b5cf6;">
            <i class="fas fa-coins me-2"></i>Manage Rewards
          </a>
        </div>

        <!-- System Status -->
        <div class="feature-card">
          <h3 class="h5 mb-3">
            <i class="fas fa-server me-2 text-muted"></i>
            System Status
          </h3>
          <div class="d-flex justify-content-between align-items-center p-2">
            <span class="small">Challenges System</span>
            <span class="badge bg-success">Active</span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-2">
            <span class="small">Rewards System</span>
            <span class="badge bg-success">Active</span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-2">
            <span class="small">Points Tracking</span>
            <span class="badge bg-success">Active</span>
          </div>
          <div class="d-flex justify-content-between align-items-center p-2">
            <span class="small">Tier Progression</span>
            <span class="badge bg-success">Active</span>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="../../shared-assets/vendor/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('logoutBtn').addEventListener('click', function() {
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = '../../index.php?logout=true';
      }
    });
  </script>
</body>
</html>