<?php 
if(session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Models/Challenges.php';
require_once __DIR__ . '/../../Models/Rewards.php';

$adminID = $_SESSION['userID'] ?? 1;
$challenges = Challenges::getAll($pdo); 
$rewards = Rewards::getAll($pdo);

$timeLimitedChallenges = Challenges::getTimeLimitedChallenges($pdo);
$recurringChallenges = Challenges::getRecurringChallenges($pdo);

$challengeTree = [];
foreach ($challenges as $challenge) {
    $level = (int)($challenge['tree_level'] ?? 0);
    if (!isset($challengeTree[$level])) {
        $challengeTree[$level] = [];
    }
    $challengeTree[$level][] = $challenge;
}
ksort($challengeTree);

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
$edit_challenge = $_SESSION['edit_challenge'] ?? null;
$form_data = $_SESSION['form_data'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
unset($_SESSION['edit_challenge']);
unset($_SESSION['form_data']);

// Get available categories and skills for suggestions
$availableCategories = Challenges::getAvailableCategories($pdo);
$availableSkills = Challenges::getAvailableSkills($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Challenge Management - Admin</title>
<link href="../shared-assets/vendor/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body {
    background: #f8fafc;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #2c3e50;
    min-height: 100vh;
}
.container {
    max-width: 1400px;
}
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
.challenge-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 5px solid #059669;
    transition: all 0.3s ease;
    height: 100%;
}
.challenge-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.12);
}
.create-btn,
.edit-btn {
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.create-btn:hover,
.edit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}
.delete-btn {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.delete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
}
.reward-btn {
    background: #f59e0b;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.reward-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
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
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.9rem;
}
.form-modal {
    display: none;
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
    max-width: 800px;
    width: 95%;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    max-height: 90vh;
    overflow-y: auto;
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
.tree-view {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.tree-path {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px dashed #e5e7eb;
}
.tree-path:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
.path-header {
    background: #dbeafe;
    color: #1e40af;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
}
.challenge-node {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 10px;
    margin-bottom: 0.8rem;
    border-left: 4px solid #3b82f6;
    transition: all 0.2s ease;
}
.challenge-node:hover {
    background: #f1f5f9;
    transform: translateX(5px);
}
.schedule-badge {
    background: #c7d2fe;
    color: #3730a3;
    padding: 4px 8px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
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
.reward-select-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 10000;
    backdrop-filter: blur(5px);
}
.reward-select-container {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 16px;
    max-width: 500px;
    width: 95%;
    max-height: 80vh;
    overflow-y: auto;
}
.reward-option {
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.reward-option:hover {
    border-color: #2563eb;
    background: #eff6ff;
}
.reward-option.selected {
    border-color: #2563eb;
    background: #dbeafe;
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
.error-message {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 4px;
}
.date-input-group input {
    margin-right: 5px;
    display: inline-block;
    width: 70px;
}
.required-field::after {
    content: " *";
    color: #dc2626;
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
.tag-suggestions {
    position: absolute;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    max-height: 150px;
    overflow-y: auto;
    z-index: 1000;
    width: 100%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: none;
}
.tag-suggestion-item {
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.2s;
}
.tag-suggestion-item:hover {
    background: #f3f4f6;
}
.tag-pill {
    display: inline-flex;
    align-items: center;
    background: #2563eb;
    color: white;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 0.85rem;
    margin: 2px;
}
.tag-pill button {
    background: none;
    border: none;
    color: white;
    margin-left: 6px;
    cursor: pointer;
    font-size: 0.9rem;
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

<main class="container py-5">

    <div class="dashboard-header text-center">
        <h1 class="mb-3">
            <i class="fas fa-list-check me-3"></i>Challenge Management
        </h1>
        <p class="mb-0 opacity-90">Admin panel for managing challenges</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <i class="fas fa-clock mb-3" style="font-size: 2.5rem; color: #2563eb;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= count($timeLimitedChallenges) ?>
                </div>
                <small class="text-muted">Time-Limited Challenges</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <i class="fas fa-sync-alt mb-3" style="font-size: 2.5rem; color: #059669;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= count($recurringChallenges) ?>
                </div>
                <small class="text-muted">Recurring Challenges</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <i class="fas fa-fire mb-3" style="font-size: 2.5rem; color: #dc2626;"></i>
                <div style="font-size: 2.2rem; font-weight: 700; color: #111827;">
                    <?= count($challenges) ?>
                </div>
                <small class="text-muted">Total Challenges</small>
            </div>
        </div>
    </div>

    <?php if (!empty($timeLimitedChallenges)): ?>
    <div class="feature-section">
        <h4><i class="fas fa-clock me-2"></i>Time-Limited Challenges</h4>
        <div class="row g-3">
            <?php foreach ($timeLimitedChallenges as $challenge): ?>
                <div class="col-md-6">
                    <div class="challenge-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($challenge['title']) ?></h6>
                            <span class="points-badge"><?= $challenge['points'] ?> pts</span>
                        </div>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($challenge['description']) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php if ($challenge['category']): ?>
                                <span class="category-badge">
                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($challenge['category']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($challenge['skill_tags']): 
                                $tags = explode(',', $challenge['skill_tags']);
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
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($challenge['time_limit_minutes']): ?>
                                <span class="schedule-badge">
                                    <i class="fas fa-hourglass-end me-1"></i>
                                    <?= $challenge['time_limit_minutes'] ?> min limit
                                </span>
                            <?php endif; ?>
                            <span class="badge-status badge-active">Active</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($recurringChallenges)): ?>
    <div class="feature-section">
        <h4><i class="fas fa-sync-alt me-2"></i>Recurring Challenges</h4>
        <div class="row g-3">
            <?php foreach ($recurringChallenges as $challenge): ?>
                <div class="col-md-6">
                    <div class="challenge-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($challenge['title']) ?></h6>
                            <span class="points-badge"><?= $challenge['points'] ?> pts</span>
                        </div>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($challenge['description']) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php if ($challenge['category']): ?>
                                <span class="category-badge">
                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($challenge['category']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($challenge['skill_tags']): 
                                $tags = explode(',', $challenge['skill_tags']);
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
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($challenge['recurrence_pattern']): ?>
                                <span class="schedule-badge">
                                    <i class="fas fa-repeat me-1"></i>
                                    <?= htmlspecialchars(ucfirst($challenge['recurrence_pattern'])) ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge-status badge-active">Active</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12 text-center">
            <button id="createChallengeBtn" class="btn create-btn fs-5 px-5 py-3">
                <i class="fas fa-plus me-2"></i>Create New Challenge
            </button>
        </div>
    </div>

    <div class="tree-view">
        <h3 class="mb-4">
            <i class="fas fa-sitemap me-2" style="color: #059669;"></i>
            Challenge Progression Tree
        </h3>
        
        <?php if (!empty($challengeTree)): ?>
            <?php foreach ($challengeTree as $level => $challenges): ?>
                <div class="tree-path">
                    <div class="path-header">
                        <h4 class="mb-0">
                            <i class="fas fa-layer-group me-2"></i>
                            Level <?= $level ?> Challenges
                        </h4>
                    </div>
                    
                    <div class="row g-3">
                        <?php foreach ($challenges as $c): ?>
                            <div class="col-lg-6">
                                <div class="challenge-node">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($c['title']) ?></h6>
                                            <span class="points-badge"><?= $c['points'] ?> pts</span>
                                        </div>
                                        <p class="text-secondary mb-2 small"><?= htmlspecialchars($c['description'] ?? 'No description') ?></p>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
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
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="badge-status bg-light border text-dark"><?= htmlspecialchars($c['type']) ?></span>
                                            <span class="badge-status <?= $c['status']=='Active' ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= htmlspecialchars($c['status']) ?>
                                            </span>
                                            <?php if ($c['schedule_type'] != 'none'): ?>
                                                <span class="schedule-badge"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $c['schedule_type']))) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ms-3 d-flex gap-2">
                                        <button class="btn reward-btn" onclick="showRewardModal(<?= $c['id'] ?>)">
                                            <i class="fas fa-gift me-1"></i>Reward
                                        </button>
                                        <a href="../../Controllers/ChallengesController.php?action=get&id=<?= $c['id'] ?>" class="btn edit-btn">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="../../Controllers/ChallengesController.php?action=delete&id=<?= $c['id'] ?>" class="btn delete-btn" onclick="return confirm('Are you sure you want to delete this challenge?')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No challenges organized in tree</h4>
                <p class="text-muted">Create challenges and organize them into progression levels</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<div id="challengeForm" class="form-modal" <?= ($edit_challenge || $form_data) ? 'style="display: block;"' : '' ?>>
    <div class="form-container">
        <h3 class="form-title">
            <i class="fas fa-plus me-2"></i>
            <?= $edit_challenge ? 'Edit Challenge' : 'Create New Challenge' ?>
        </h3>
        <form id="challengeFormData" action="../../Controllers/ChallengesController.php?<?= $edit_challenge ? 'action=update&id=' . $edit_challenge['id'] : 'action=create' ?>" method="POST">
            <?php if ($edit_challenge): ?>
                <input type="hidden" name="id" value="<?= $edit_challenge['id'] ?>" />
            <?php endif; ?>
            
            <input type="hidden" name="createdBy" value="<?= $adminID ?>" />
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="title" class="form-label required-field">Challenge Title</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?= 
                            $edit_challenge ? htmlspecialchars($edit_challenge['title']) : 
                            ($form_data ? htmlspecialchars($form_data['title'] ?? '') : '') 
                        ?>" />
                        <div class="error-message" id="titleError"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="points" class="form-label required-field">Points</label>
                        <input type="text" id="points" name="points" class="form-control" value="<?= 
                            $edit_challenge ? $edit_challenge['points'] : 
                            ($form_data ? ($form_data['points'] ?? '') : '') 
                        ?>" />
                        <div class="error-message" id="pointsError"></div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label required-field">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= 
                    $edit_challenge ? htmlspecialchars($edit_challenge['description']) : 
                    ($form_data ? htmlspecialchars($form_data['description'] ?? '') : '') 
                ?></textarea>
                <div class="error-message" id="descriptionError"></div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="type" class="form-label required-field">Type</label>
                        <select id="type" name="type" class="form-control">
                            <option value="">Select Type</option>
                            <option value="course" <?= ($edit_challenge && $edit_challenge['type'] == 'course') || ($form_data && ($form_data['type'] ?? '') == 'course') ? 'selected' : '' ?>>Course</option>
                            <option value="time" <?= ($edit_challenge && $edit_challenge['type'] == 'time') || ($form_data && ($form_data['type'] ?? '') == 'time') ? 'selected' : '' ?>>Time-Based</option>
                            <option value="social" <?= ($edit_challenge && $edit_challenge['type'] == 'social') || ($form_data && ($form_data['type'] ?? '') == 'social') ? 'selected' : '' ?>>Social</option>
                            <option value="weekly" <?= ($edit_challenge && $edit_challenge['type'] == 'weekly') || ($form_data && ($form_data['type'] ?? '') == 'weekly') ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= ($edit_challenge && $edit_challenge['type'] == 'monthly') || ($form_data && ($form_data['type'] ?? '') == 'monthly') ? 'selected' : '' ?>>Monthly</option>
                            <option value="timed" <?= ($edit_challenge && $edit_challenge['type'] == 'timed') || ($form_data && ($form_data['type'] ?? '') == 'timed') ? 'selected' : '' ?>>Timed Challenge</option>
                        </select>
                        <div class="error-message" id="typeError"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="status" class="form-label required-field">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Select Status</option>
                            <option value="Active" <?= ($edit_challenge && $edit_challenge['status'] == 'Active') || ($form_data && ($form_data['status'] ?? '') == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($edit_challenge && $edit_challenge['status'] == 'Inactive') || ($form_data && ($form_data['status'] ?? '') == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <div class="error-message" id="statusError"></div>
                    </div>
                </div>
            </div>
            
            <!-- NEW: Category and Skill Tags Fields -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-tag me-1"></i>Category
                        </label>
                        <input type="text" id="category" name="category" class="form-control" 
                               placeholder="e.g., Mathematics, Science, Language" 
                               value="<?= 
                                   $edit_challenge ? htmlspecialchars($edit_challenge['category'] ?? 'General') : 
                                   ($form_data ? htmlspecialchars($form_data['category'] ?? 'General') : 'General') 
                               ?>"
                               list="categorySuggestions" />
                        <datalist id="categorySuggestions">
                            <?php foreach ($availableCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-tags me-1"></i>Skill Tags
                        </label>
                        <div class="position-relative">
                            <input type="text" id="skillTagInput" class="form-control" 
                                   placeholder="Type a skill and press Enter" />
                            <div id="skillTagSuggestions" class="tag-suggestions"></div>
                        </div>
                        <div id="skillTagsContainer" class="mt-2">
                            <?php 
                            $existingTags = [];
                            if ($edit_challenge && !empty($edit_challenge['skill_tags'])) {
                                $existingTags = explode(',', $edit_challenge['skill_tags']);
                            } elseif ($form_data && !empty($form_data['skill_tags'])) {
                                $existingTags = explode(',', $form_data['skill_tags']);
                            }
                            
                            foreach ($existingTags as $tag):
                                $trimmedTag = trim($tag);
                                if (!empty($trimmedTag)):
                            ?>
                                <span class="tag-pill">
                                    <?= htmlspecialchars($trimmedTag) ?>
                                    <button type="button" onclick="removeSkillTag(this)">&times;</button>
                                    <input type="hidden" name="skill_tags[]" value="<?= htmlspecialchars($trimmedTag) ?>">
                                </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="criteria" class="form-label">Completion Criteria</label>
                <input type="text" id="criteria" name="criteria" class="form-control" placeholder="e.g., Submit assignment, Attend session, etc." value="<?= 
                    $edit_challenge ? htmlspecialchars($edit_challenge['criteria']) : 
                    ($form_data ? htmlspecialchars($form_data['criteria'] ?? '') : '') 
                ?>" />
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="schedule_type" class="form-label">Schedule Type</label>
                        <select id="schedule_type" name="schedule_type" class="form-control">
                            <option value="none" <?= ($edit_challenge && $edit_challenge['schedule_type'] == 'none') || ($form_data && ($form_data['schedule_type'] ?? '') == 'none') ? 'selected' : '' ?>>No special scheduling</option>
                            <option value="time_limited" <?= ($edit_challenge && $edit_challenge['schedule_type'] == 'time_limited') || ($form_data && ($form_data['schedule_type'] ?? '') == 'time_limited') ? 'selected' : '' ?>>Time-Limited</option>
                            <option value="recurring" <?= ($edit_challenge && $edit_challenge['schedule_type'] == 'recurring') || ($form_data && ($form_data['schedule_type'] ?? '') == 'recurring') ? 'selected' : '' ?>>Recurring</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="time_limit_minutes" class="form-label">Time Limit (minutes)</label>
                        <input type="text" id="time_limit_minutes" name="time_limit_minutes" class="form-control" placeholder="e.g., 50 for 50 minutes" value="<?= 
                            $edit_challenge ? $edit_challenge['time_limit_minutes'] : 
                            ($form_data ? ($form_data['time_limit_minutes'] ?? '') : '') 
                        ?>" />
                    </div>
                </div>
            </div>
            
            <div class="row" id="dateFields" style="<?= 
                ($edit_challenge && $edit_challenge['schedule_type'] == 'time_limited') || 
                ($form_data && ($form_data['schedule_type'] ?? '') == 'time_limited') ? 'display: block;' : 'display: none;' 
            ?>">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <div class="date-input-group">
                            <input type="text" id="start_date_day" name="start_date_day" class="form-control" placeholder="DD" value="<?= 
                                $edit_challenge ? $edit_challenge['start_date_day'] : 
                                ($form_data ? ($form_data['start_date_day'] ?? '') : '') 
                            ?>" />
                            <input type="text" id="start_date_month" name="start_date_month" class="form-control" placeholder="MM" value="<?= 
                                $edit_challenge ? $edit_challenge['start_date_month'] : 
                                ($form_data ? ($form_data['start_date_month'] ?? '') : '') 
                            ?>" />
                            <input type="text" id="start_date_year" name="start_date_year" class="form-control" placeholder="YYYY" value="<?= 
                                $edit_challenge ? $edit_challenge['start_date_year'] : 
                                ($form_data ? ($form_data['start_date_year'] ?? '') : '') 
                            ?>" />
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <div class="date-input-group">
                            <input type="text" id="end_date_day" name="end_date_day" class="form-control" placeholder="DD" value="<?= 
                                $edit_challenge ? $edit_challenge['end_date_day'] : 
                                ($form_data ? ($form_data['end_date_day'] ?? '') : '') 
                            ?>" />
                            <input type="text" id="end_date_month" name="end_date_month" class="form-control" placeholder="MM" value="<?= 
                                $edit_challenge ? $edit_challenge['end_date_month'] : 
                                ($form_data ? ($form_data['end_date_month'] ?? '') : '') 
                            ?>" />
                            <input type="text" id="end_date_year" name="end_date_year" class="form-control" placeholder="YYYY" value="<?= 
                                $edit_challenge ? $edit_challenge['end_date_year'] : 
                                ($form_data ? ($form_data['end_date_year'] ?? '') : '') 
                            ?>" />
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3" id="recurrenceField" style="<?= 
                ($edit_challenge && $edit_challenge['schedule_type'] == 'recurring') || 
                ($form_data && ($form_data['schedule_type'] ?? '') == 'recurring') ? 'display: block;' : 'display: none;' 
            ?>">
                <label for="recurrence_pattern" class="form-label">Recurrence Pattern</label>
                <select id="recurrence_pattern" name="recurrence_pattern" class="form-control">
                    <option value="">Select Pattern</option>
                    <option value="weekly" <?= ($edit_challenge && $edit_challenge['recurrence_pattern'] == 'weekly') || ($form_data && ($form_data['recurrence_pattern'] ?? '') == 'weekly') ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= ($edit_challenge && $edit_challenge['recurrence_pattern'] == 'monthly') || ($form_data && ($form_data['recurrence_pattern'] ?? '') == 'monthly') ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tree_level" class="form-label">Tree Level</label>
                        <select id="tree_level" name="tree_level" class="form-control">
                            <option value="0" <?= ($edit_challenge && $edit_challenge['tree_level'] == 0) || ($form_data && ($form_data['tree_level'] ?? '0') == '0') ? 'selected' : '' ?>>Level 0 - Foundation</option>
                            <option value="1" <?= ($edit_challenge && $edit_challenge['tree_level'] == 1) || ($form_data && ($form_data['tree_level'] ?? '') == '1') ? 'selected' : '' ?>>Level 1 - Beginner</option>
                            <option value="2" <?= ($edit_challenge && $edit_challenge['tree_level'] == 2) || ($form_data && ($form_data['tree_level'] ?? '') == '2') ? 'selected' : '' ?>>Level 2 - Intermediate</option>
                            <option value="3" <?= ($edit_challenge && $edit_challenge['tree_level'] == 3) || ($form_data && ($form_data['tree_level'] ?? '') == '3') ? 'selected' : '' ?>>Level 3 - Advanced</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tree_order" class="form-label">Tree Order</label>
                        <input type="number" id="tree_order" name="tree_order" class="form-control" min="0" value="<?= 
                            $edit_challenge ? $edit_challenge['tree_order'] : 
                            ($form_data ? ($form_data['tree_order'] ?? '0') : '0') 
                        ?>" />
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-3">
                <button type="button" onclick="hideForm()" class="btn btn-outline-secondary px-4">Cancel</button>
                <button type="submit" class="btn create-btn px-4">
                    <i class="fas fa-save me-2"></i>Save Challenge
                </button>
            </div>
        </form>
    </div>
</div>

<div id="rewardSelectModal" class="reward-select-modal">
    <div class="reward-select-container">
        <h3 class="form-title">
            <i class="fas fa-gift me-2"></i>
            Select Reward for Challenge
        </h3>
        <form id="rewardForm" action="../../Controllers/ChallengesController.php?action=add_reward" method="POST">
            <input type="hidden" id="selectedChallengeId" name="challenge_id" />
            <div id="rewardsList" class="mb-4" style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($rewards as $reward): ?>
                    <div class="reward-option" data-id="<?= $reward['id'] ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($reward['title']) ?></strong>
                                <div class="text-muted small"><?= $reward['pointsCost'] ?> points • <?= $reward['type'] ?></div>
                            </div>
                            <span class="badge bg-light text-dark"><?= $reward['availability'] ?> available</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-end gap-3">
                <button type="button" onclick="hideRewardModal()" class="btn btn-outline-secondary px-4">Cancel</button>
                <button type="submit" class="btn create-btn px-4">
                    <i class="fas fa-link me-2"></i>Assign Reward
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRewardModal(challengeId) {
    document.getElementById('selectedChallengeId').value = challengeId;
    document.getElementById('rewardSelectModal').style.display = 'block';
}

function hideRewardModal() {
    document.getElementById('rewardSelectModal').style.display = 'none';
}

document.getElementById('createChallengeBtn').addEventListener('click', function() {
    document.getElementById('challengeForm').style.display = 'block';
});

document.getElementById('schedule_type').addEventListener('change', function() {
    const dateFields = document.getElementById('dateFields');
    const recurrenceField = document.getElementById('recurrenceField');
    
    if (this.value === 'time_limited') {
        dateFields.style.display = 'block';
        recurrenceField.style.display = 'none';
    } else if (this.value === 'recurring') {
        dateFields.style.display = 'none';
        recurrenceField.style.display = 'block';
    } else {
        dateFields.style.display = 'none';
        recurrenceField.style.display = 'none';
    }
});

function hideForm() {
    document.getElementById('challengeForm').style.display = 'none';
    window.location.href = 'Challenges.php';
}

document.getElementById('challengeFormData').addEventListener('submit', function(e) {
    let valid = true;
    
    document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
    
    const title = document.getElementById('title').value.trim();
    if (title.length === 0) {
        document.getElementById('titleError').textContent = 'Title is required';
        valid = false;
    } else if (title.length < 3) {
        document.getElementById('titleError').textContent = 'Title must be at least 3 characters';
        valid = false;
    }
    
    const points = parseInt(document.getElementById('points').value);
    if (isNaN(points)) {
        document.getElementById('pointsError').textContent = 'Points must be a number';
        valid = false;
    } else if (points < 1) {
        document.getElementById('pointsError').textContent = 'Points must be at least 1';
        valid = false;
    } else if (points > 1000) {
        document.getElementById('pointsError').textContent = 'Points must not exceed 1000';
        valid = false;
    }
    
    const description = document.getElementById('description').value.trim();
    if (description.length === 0) {
        document.getElementById('descriptionError').textContent = 'Description is required';
        valid = false;
    } else if (description.length < 3) {
        document.getElementById('descriptionError').textContent = 'Description must be at least 3 characters';
        valid = false;
    }
    
    const type = document.getElementById('type').value;
    if (!type) {
        document.getElementById('typeError').textContent = 'Please select a type';
        valid = false;
    }
    
    const status = document.getElementById('status').value;
    if (!status) {
        document.getElementById('statusError').textContent = 'Please select a status';
        valid = false;
    }
    
    if (!valid) {
        e.preventDefault();
    }
});

document.querySelectorAll('.reward-option').forEach(option => {
    option.addEventListener('click', function() {
        this.classList.toggle('selected');
        const rewardId = this.dataset.id;
        
        let input = this.querySelector('input[name="reward_id"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reward_id';
            input.value = rewardId;
            this.appendChild(input);
        } else {
            this.removeChild(input);
        }
    });
});

// Skill Tags functionality
const skillTagInput = document.getElementById('skillTagInput');
const skillTagsContainer = document.getElementById('skillTagsContainer');
const skillTagSuggestions = document.getElementById('skillTagSuggestions');
const availableSkills = <?= json_encode($availableSkills) ?>;

skillTagInput.addEventListener('input', function() {
    const value = this.value.trim().toLowerCase();
    skillTagSuggestions.innerHTML = '';
    
    if (value.length > 0) {
        const filteredSkills = availableSkills.filter(skill => 
            skill.toLowerCase().includes(value) && 
            !Array.from(skillTagsContainer.querySelectorAll('input[type="hidden"]'))
                .some(input => input.value.toLowerCase() === skill.toLowerCase())
        );
        
        if (filteredSkills.length > 0) {
            skillTagSuggestions.style.display = 'block';
            filteredSkills.forEach(skill => {
                const div = document.createElement('div');
                div.className = 'tag-suggestion-item';
                div.textContent = skill;
                div.addEventListener('click', () => {
                    addSkillTag(skill);
                    skillTagInput.value = '';
                    skillTagSuggestions.style.display = 'none';
                });
                skillTagSuggestions.appendChild(div);
            });
        } else {
            skillTagSuggestions.style.display = 'none';
        }
    } else {
        skillTagSuggestions.style.display = 'none';
    }
});

skillTagInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const value = this.value.trim();
        if (value.length > 0) {
            addSkillTag(value);
            this.value = '';
            skillTagSuggestions.style.display = 'none';
        }
    }
});

function addSkillTag(tag) {
    const trimmedTag = tag.trim();
    if (!trimmedTag) return;
    
    // Check if tag already exists
    const existingTags = Array.from(skillTagsContainer.querySelectorAll('input[type="hidden"]'))
        .map(input => input.value.toLowerCase());
    
    if (existingTags.includes(trimmedTag.toLowerCase())) return;
    
    const tagPill = document.createElement('span');
    tagPill.className = 'tag-pill';
    tagPill.innerHTML = `
        ${trimmedTag}
        <button type="button" onclick="removeSkillTag(this)">&times;</button>
        <input type="hidden" name="skill_tags[]" value="${trimmedTag.replace(/"/g, '&quot;')}">
    `;
    
    skillTagsContainer.appendChild(tagPill);
}

function removeSkillTag(button) {
    const tagPill = button.parentElement;
    skillTagsContainer.removeChild(tagPill);
}

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!skillTagInput.contains(e.target) && !skillTagSuggestions.contains(e.target)) {
        skillTagSuggestions.style.display = 'none';
    }
});

setTimeout(() => {
    const toasts = document.querySelectorAll('.message-toast');
    toasts.forEach(toast => toast.style.display = 'none');
}, 4000);
</script>
</body>
</html>