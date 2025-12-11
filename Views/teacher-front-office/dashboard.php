<?php
if(session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Models/Challenges.php';

$teacherID = $_SESSION['userID'] ?? 1;
$teacherChallenges = Challenges::getByCreator($pdo, $teacherID);
$activeChallenges = array_filter($teacherChallenges, fn($c) => $c['status'] === 'Active');
$timeLimitedChallenges = Challenges::getTimeLimitedChallenges($pdo);
$teacherTimeLimited = array_filter($timeLimitedChallenges, fn($c) => $c['createdBy'] == $teacherID);

// Get student count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$stmt->execute();
$studentCount = $stmt->fetchColumn();

// Get recent completions
$stmt = $pdo->prepare("SELECT u.name as student_name, c.title as challenge_title, al.points_amount as pointsAwarded, al.created_at as completed_at FROM activity_log al JOIN users u ON al.user_id = u.id JOIN challenges c ON al.target_id = c.id WHERE al.activity_type = 'challenge_complete' AND c.createdBy = ? ORDER BY al.created_at DESC LIMIT 5");
$stmt->execute([$teacherID]);
$recentCompletions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Teacher Dashboard | EduMind+</title>
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
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border-left: 4px solid #2563eb;
      height: 100%;
    }
    .compact-card {
      background: white;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border-left: 4px solid #059669;
      height: 100%;
    }
    .quick-action-btn {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 8px 12px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block;
      text-align: center;
      margin-bottom: 8px;
    }
    .quick-action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
      color: white;
    }
    .mini-item {
      display: flex;
      align-items: center;
      padding: 8px;
      background: #f8fafc;
      border-radius: 6px;
      margin-bottom: 6px;
      font-size: 0.85rem;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
      margin-bottom: 10px;
    }
    .stat-mini {
      background: #f8fafc;
      border-radius: 6px;
      padding: 10px;
      text-align: center;
    }
    .stat-mini .number {
      font-size: 1.2rem;
      font-weight: bold;
      margin-bottom: 2px;
    }
    .stat-mini .label {
      font-size: 0.75rem;
      color: #6b7280;
    }
    .completion-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 6px;
      background: #f8fafc;
      border-radius: 6px;
      margin-bottom: 5px;
      font-size: 0.8rem;
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
  </style>
</head>
<body data-page="teacher-dashboard">
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">EduMind+ Teacher</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample07"
        aria-controls="navbarsExample07" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarsExample07">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="Challenges.php">My Challenges</a></li>
          <li class="nav-item"><a class="nav-link" href="Rewards.php">Rewards</a></li>
          <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
          <li class="nav-item"><a class="nav-link" href="students.php">Students</a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        </ul>
        <div class="d-flex"><button id="logoutBtn" class="btn btn-outline-light btn-sm">Logout</button></div>
      </div>
    </div>
  </nav>

  <main class="container py-3">
    <!-- Quick Stats Row -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="text-muted small">My Challenges</div>
          <div class="h5 mb-0 text-primary"><?= count($teacherChallenges) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="text-muted small">Active</div>
          <div class="h5 mb-0 text-success"><?= count($activeChallenges) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="text-muted small">Time-Limited</div>
          <div class="h5 mb-0 text-warning"><?= count($teacherTimeLimited) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="text-muted small">Students</div>
          <div class="h5 mb-0 text-info"><?= $studentCount ?></div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="row g-3">
      <!-- Left Column -->
      <div class="col-lg-7">
        <!-- My Challenges Overview -->
        <div class="compact-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">
              <i class="fas fa-list-check me-1 text-success"></i>
              My Challenges
            </h5>
            <a href="Challenges.php" class="btn btn-sm btn-outline-primary">Manage All</a>
          </div>
          
          <div class="stats-grid mb-2">
            <div class="stat-mini">
              <div class="number text-primary"><?= count($teacherChallenges) ?></div>
              <div class="label">Total</div>
            </div>
            <div class="stat-mini">
              <div class="number text-success"><?= count($activeChallenges) ?></div>
              <div class="label">Active</div>
            </div>
            <div class="stat-mini">
              <div class="number text-warning"><?= count($teacherTimeLimited) ?></div>
              <div class="label">Time-Limited</div>
            </div>
            <div class="stat-mini">
              <div class="number text-info"><?= count(array_filter($teacherChallenges, fn($c) => $c['type'] == 'timed')) ?></div>
              <div class="label">Timed</div>
            </div>
          </div>

          <?php if (!empty($teacherChallenges)): ?>
          <div class="mt-2">
            <small class="text-muted">Recent Challenges:</small>
            <?php $recentChallenges = array_slice($teacherChallenges, 0, 3); ?>
            <?php foreach ($recentChallenges as $challenge): ?>
              <div class="mini-item">
                <div class="flex-grow-1">
                  <div class="fw-bold small"><?= htmlspecialchars(substr($challenge['title'], 0, 25)) ?><?= strlen($challenge['title']) > 25 ? '...' : '' ?></div>
                  <small class="text-muted"><?= $challenge['points'] ?> pts • <?= $challenge['type'] ?></small>
                </div>
                <span class="badge bg-<?= $challenge['status'] == 'Active' ? 'success' : 'secondary' ?>">
                  <?= $challenge['status'] ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
            <div class="text-center py-2">
              <small class="text-muted">No challenges created yet</small>
            </div>
          <?php endif; ?>
        </div>

        <!-- Recent Completions -->
        <?php if (!empty($recentCompletions)): ?>
        <div class="compact-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">
              <i class="fas fa-history me-1 text-info"></i>
              Recent Completions
            </h5>
            <small class="text-muted">All Students</small>
          </div>
          <?php foreach ($recentCompletions as $completion): ?>
            <div class="completion-item">
              <div class="flex-grow-1">
                <div class="fw-bold small"><?= htmlspecialchars($completion['student_name']) ?></div>
                <small class="text-muted"><?= htmlspecialchars(substr($completion['challenge_title'], 0, 20)) ?><?= strlen($completion['challenge_title']) > 20 ? '...' : '' ?></small>
              </div>
              <div class="text-end">
                <div class="fw-bold text-success">+<?= $completion['pointsAwarded'] ?></div>
                <small class="text-muted"><?= date('M j', strtotime($completion['completed_at'])) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right Column -->
      <div class="col-lg-5">
        <!-- Quick Actions -->
        <div class="compact-card">
          <h5 class="mb-2">
            <i class="fas fa-bolt me-1 text-primary"></i>
            Quick Actions
          </h5>
          <a href="Challenges.php" class="quick-action-btn">
            <i class="fas fa-plus me-1"></i>Create New Challenge
          </a>
          <a href="Challenges.php" class="quick-action-btn" style="background: #059669;">
            <i class="fas fa-list-check me-1"></i>Manage Challenges
          </a>
          <a href="Rewards.php" class="quick-action-btn" style="background: #f59e0b;">
            <i class="fas fa-gift me-1"></i>Manage Rewards
          </a>
          <a href="students.php" class="quick-action-btn" style="background: #8b5cf6;">
            <i class="fas fa-users me-1"></i>Student Progress
          </a>
        </div>

        <!-- Time-Limited Challenges -->
        <?php if (!empty($teacherTimeLimited)): ?>
        <div class="compact-card">
          <h5 class="mb-2">
            <i class="fas fa-clock me-1 text-warning"></i>
            Time-Limited Challenges
          </h5>
          <?php foreach ($teacherTimeLimited as $challenge): ?>
            <div class="mini-item">
              <div class="flex-grow-1">
                <div class="fw-bold small"><?= htmlspecialchars(substr($challenge['title'], 0, 20)) ?><?= strlen($challenge['title']) > 20 ? '...' : '' ?></div>
                <small class="text-muted">
                  <?= $challenge['time_limit_minutes'] ? $challenge['time_limit_minutes'] . ' min' : 'Limited time' ?>
                </small>
              </div>
              <span class="badge bg-warning">Active</span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Challenge Types -->
        <div class="compact-card">
          <h5 class="mb-2">
            <i class="fas fa-chart-pie me-1 text-info"></i>
            Challenge Types
          </h5>
          <?php
          $typeCounts = [];
          foreach ($teacherChallenges as $challenge) {
              $type = $challenge['type'];
              $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
          }
          ?>
          <?php foreach ($typeCounts as $type => $count): ?>
            <div class="d-flex justify-content-between align-items-center p-1">
              <span class="small"><?= ucfirst($type) ?></span>
              <span class="badge bg-primary"><?= $count ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (empty($typeCounts)): ?>
            <small class="text-muted">No challenges yet</small>
          <?php endif; ?>
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