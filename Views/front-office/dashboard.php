<?php
if(session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Models/Challenges.php';
require_once __DIR__ . '/../../Models/Rewards.php';
require_once __DIR__ . '/../../Models/Points.php';

$studentID = $_SESSION['userID'] ?? 1;
$balance = Points::getBalance($pdo, $studentID);
$availableChallenges = Challenges::getAvailableChallenges($pdo, $studentID);

// Get recent completions
$stmt = $pdo->prepare("SELECT c.title as challenge_title, al.points_amount as pointsAwarded, al.created_at as completed_at FROM activity_log al JOIN challenges c ON al.target_id = c.id WHERE al.user_id = ? AND al.activity_type = 'challenge_complete' ORDER BY al.created_at DESC LIMIT 5");
$stmt->execute([$studentID]);
$recentCompletions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tierProgress = RewardTiers::getTierProgress($pdo, $studentID);
$currentTier = $tierProgress['current_tier'];
$myAchievements = RewardTiers::getStudentAchievements($pdo, $studentID);
$timeLimitedChallenges = Challenges::getTimeLimitedChallenges($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EduMind+ | Student Dashboard</title>
  <link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
    }
    .points-card {
      background: #2563eb;
      color: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .challenge-mini-card {
      background: white;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid #10b981;
      transition: all 0.3s ease;
    }
    .challenge-mini-card:hover {
      transform: translateX(5px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .tier-progress {
      background: white;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .progress-bar-custom {
      background: #e5e7eb;
      border-radius: 10px;
      height: 12px;
      overflow: hidden;
    }
    .progress-fill {
      background: #2563eb;
      height: 100%;
      border-radius: 10px;
      transition: width 0.5s ease;
    }
    .achievement-badge {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }
    .quick-action-btn {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 10px 15px;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block;
      text-align: center;
      margin-bottom: 8px;
    }
    .quick-action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
      color: white;
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
    .btn-warning {
      background-color: #f59e0b;
      border-color: #f59e0b;
    }
    .btn-warning:hover {
      background-color: #d97706;
      border-color: #d97706;
    }
  </style>
</head>
<body data-page="front-dashboard">
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">EduMind+</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample07"
        aria-controls="navbarsExample07" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarsExample07">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="Challenges.php">Challenges</a></li>
          <li class="nav-item"><a class="nav-link" href="Rewards.php">Rewards</a></li>
          <li class="nav-item"><a class="nav-link" href="projects.php">Projects</a></li>
          <li class="nav-item"><a class="nav-link" href="courses.php">Courses</a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        </ul>
        <div class="d-flex"><button id="logoutBtn" class="btn btn-outline-light btn-sm">Logout</button></div>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <!-- Points & Tier Progress Row -->
    <div class="row g-4 mb-4">
      <div class="col-lg-8">
        <div class="points-card">
          <div class="row align-items-center">
            <div class="col-md-8">
              <h3 class="h4 mb-2">
                <i class="fas fa-coins me-2"></i>
                Your Learning Journey
              </h3>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <div class="h2 mb-1"><?= $balance ?> Points</div>
                  <p class="mb-0 opacity-90">Available for rewards</p>
                </div>
                <?php if ($currentTier): ?>
                  <div class="text-center">
                    <div class="achievement-badge"><?= $currentTier['badge_name'] ?></div>
                    <div class="fw-bold"><?= $currentTier['name'] ?> Tier</div>
                  </div>
                <?php endif; ?>
              </div>
              
              <?php if ($currentTier): ?>
                <div class="tier-progress" style="background: rgba(255,255,255,0.1);">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <small>Progress to next tier</small>
                    <small><?= round($tierProgress['progress'], 1) ?>%</small>
                  </div>
                  <div class="progress-bar-custom">
                    <div class="progress-fill" style="width: <?= $tierProgress['progress'] ?>%"></div>
                  </div>
                  <?php if ($tierProgress['next_tier']): ?>
                    <small class="opacity-90"><?= $tierProgress['points_to_next'] ?> points to <?= $tierProgress['next_tier']['name'] ?> Tier</small>
                  <?php else: ?>
                    <small class="opacity-90">🏆 Highest tier achieved!</small>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="tier-progress" style="background: rgba(255,255,255,0.1);">
                  <small>Complete challenges to unlock reward tiers!</small>
                  <div class="progress-bar-custom mt-2">
                    <div class="progress-fill" style="width: <?= min(($balance / 25) * 100, 100) ?>%"></div>
                  </div>
                  <small class="opacity-90"><?= $balance ?>/25 points to Bronze Tier</small>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-md-4 text-center">
              <div class="mb-3">
                <div class="h5 mb-1">Available Challenges</div>
                <div class="h2 text-warning"><?= count($availableChallenges) ?></div>
              </div>
              <a href="Challenges.php" class="btn btn-warning btn-lg w-100">
                <i class="fas fa-play-circle me-2"></i>Start Learning
              </a>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h3 class="h5 mb-3">
              <i class="fas fa-bolt me-2 text-primary"></i>
              Quick Actions
            </h3>
            <a href="Challenges.php" class="quick-action-btn">
              <i class="fas fa-list-check me-2"></i>View Challenges
            </a>
            <a href="Rewards.php" class="quick-action-btn" style="background: #f59e0b;">
              <i class="fas fa-gift me-2"></i>Redeem Rewards
            </a>
            <a href="courses.php" class="quick-action-btn" style="background: #059669;">
              <i class="fas fa-play me-2"></i>Take a Quiz
            </a>
            
            <?php if (!empty($timeLimitedChallenges)): ?>
              <div class="mt-3 p-3 rounded" style="background: rgba(245, 158, 11, 0.1);">
                <small class="text-warning">
                  <i class="fas fa-clock me-1"></i>
                  <strong><?= count($timeLimitedChallenges) ?> time-limited challenges</strong> available!
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Left Column -->
      <div class="col-12 col-lg-8">
        <!-- Available Challenges -->
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="h5 mb-0">
                <i class="fas fa-list-check me-2 text-success"></i>
                Available Challenges
              </h2>
              <a href="Challenges.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            
            <?php if (!empty($availableChallenges)): ?>
              <?php $recentChallenges = array_slice($availableChallenges, 0, 4); ?>
              <?php foreach ($recentChallenges as $challenge): ?>
                <div class="challenge-mini-card">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <strong class="small"><?= htmlspecialchars($challenge['title']) ?></strong>
                      <div class="text-muted smaller">
                        <?= $challenge['points'] ?> pts • <?= $challenge['type'] ?>
                      </div>
                    </div>
                    <a href="Challenges.php" class="btn btn-sm btn-primary">Start</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted text-center py-3">No challenges available. Complete prerequisites to unlock more!</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Completions -->
        <?php if (!empty($recentCompletions)): ?>
        <div class="card shadow-sm">
          <div class="card-body">
            <h2 class="h5 mb-3">
              <i class="fas fa-history me-2 text-info"></i>
              Recent Completions
            </h2>
            <?php foreach ($recentCompletions as $completion): ?>
              <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                <div>
                  <strong class="small"><?= htmlspecialchars($completion['challenge_title']) ?></strong>
                  <div class="text-muted smaller">
                    Completed <?= date('M j', strtotime($completion['completed_at'])) ?>
                  </div>
                </div>
                <span class="badge bg-success">+<?= $completion['pointsAwarded'] ?> pts</span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right Column -->
      <div class="col-12 col-lg-4">
        <!-- Your Achievements -->
        <?php if (!empty($myAchievements)): ?>
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h2 class="h5 mb-3">
              <i class="fas fa-trophy me-2 text-warning"></i>
              Your Achievements
            </h2>
            <?php foreach ($myAchievements as $achievement): ?>
              <div class="d-flex align-items-center p-2 bg-light rounded mb-2">
                <div class="me-3" style="font-size: 1.5rem;">
                  <?= $achievement['badge_name'] ?>
                </div>
                <div>
                  <strong class="small"><?= $achievement['name'] ?> Tier</strong>
                  <div class="text-muted smaller">
                    Achieved <?= date('M j', strtotime($achievement['achieved_at'])) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Performance Chart Area -->
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="h5 mb-0">📊 My Performance</h2>
              <div class="d-flex gap-2">
                <a href="courses.php" class="btn btn-sm btn-primary">Take Quiz</a>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scoresModal">View Scores</button>
              </div>
            </div>
            <div class="text-center py-4">
              <div class="display-4 text-primary"><?= $balance ?></div>
              <small class="text-muted">Total Points Earned</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Results -->
    <div class="row g-4 mt-1">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h5 mb-0">Recent Activity</h2>
              <a href="Challenges.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Details</th>
                    <th>Points</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $stmt = $pdo->prepare("SELECT activity_type, details, points_amount, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                  $stmt->execute([$studentID]);
                  $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
                  ?>
                  <?php foreach ($recentActivity as $activity): ?>
                  <tr>
                    <td>
                      <?php 
                      $icons = [
                        'challenge_complete' => '🏆',
                        'points_award' => '⭐',
                        'redeem_reward' => '🎁',
                        'tier_achievement' => '🏅'
                      ];
                      echo $icons[$activity['activity_type']] ?? '📝';
                      ?>
                    </td>
                    <td><?= htmlspecialchars(substr($activity['details'], 0, 30)) ?><?= strlen($activity['details']) > 30 ? '...' : '' ?></td>
                    <td>
                      <?php if ($activity['points_amount'] > 0): ?>
                        <span class="text-success">+<?= $activity['points_amount'] ?></span>
                      <?php elseif ($activity['points_amount'] < 0): ?>
                        <span class="text-danger"><?= $activity['points_amount'] ?></span>
                      <?php else: ?>
                        <span class="text-muted">0</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M j', strtotime($activity['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal -->
  <div class="modal fade" id="scoresModal" tabindex="-1" aria-hidden="true" aria-labelledby="scoresModalLabel">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="scoresModalLabel">📈 My Progress</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>Points Balance</span>
              <strong><?= $balance ?> points</strong>
            </div>
            <div class="progress mt-1" style="height: 8px;">
              <div class="progress-bar bg-primary" style="width: <?= min(($balance / 500) * 100, 100) ?>%"></div>
            </div>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>Challenges Completed</span>
              <strong><?= count($recentCompletions) ?></strong>
            </div>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>Current Tier</span>
              <strong><?= $currentTier ? $currentTier['name'] : 'Starter' ?></strong>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

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