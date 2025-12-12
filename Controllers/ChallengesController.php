<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Models/Challenges.php';
require_once __DIR__ . '/../Models/Points.php';

class ChallengesController {
    public static function handle($pdo) {
        $action = $_GET['action'] ?? '';
        $studentID = $_SESSION['userID'] ?? 1;

        switch ($action) {
            case 'all': self::showAll($pdo); break;
            case 'create': self::createChallenge($pdo); break;
            case 'update': self::updateChallenge($pdo); break;
            case 'delete': self::deleteChallenge($pdo); break;
            case 'complete': self::completeChallenge($pdo, $studentID); break;
            case 'get': self::showOne($pdo); break;
            case 'leaderboard': self::showLeaderboard($pdo); break;
            case 'tree': self::showChallengeTree($pdo, $studentID); break;
            case 'available': self::showAvailable($pdo, $studentID); break;
            case 'add_reward': self::addRewardToChallenge($pdo); break;
            case 'remove_reward': self::removeRewardFromChallenge($pdo); break;
            case 'get_rewards': self::getChallengeRewards($pdo); break;
            case 'rate': self::rateChallenge($pdo, $studentID); break;
            default: 
                if (isset($_POST['action'])) {
                    $postAction = $_POST['action'];
                    switch($postAction) {
                        case 'create': self::createChallenge($pdo); break;
                        case 'update': self::updateChallenge($pdo); break;
                        default: self::redirectToDashboard('Invalid action');
                    }
                } else {
                    self::redirectToDashboard('Invalid action');
                }
        }
    }

    private static function showAll($pdo) {
        try {
            $challenges = Challenges::getAll($pdo);
            echo 'success';
        } catch (Exception $e) {
            echo 'Error loading challenges: ' . $e->getMessage();
        }
    }

    private static function showAvailable($pdo, $studentID) {
        try {
            $challenges = Challenges::getAvailableChallenges($pdo, $studentID);
            echo 'success';
        } catch (Exception $e) {
            echo 'Error loading available challenges: ' . $e->getMessage();
        }
    }

    private static function showChallengeTree($pdo, $studentID) {
        try {
            $tree = Challenges::getChallengeTree($pdo, $studentID);
            echo 'success';
        } catch (Exception $e) {
            echo 'Error loading challenge tree: ' . $e->getMessage();
        }
    }

    private static function showOne($pdo) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error_message'] = 'Invalid challenge ID';
            self::redirectToDashboard();
            exit;
        }
        
        try {
            $challenge = Challenges::getByID($pdo, $id);
            if (!$challenge) { 
                $_SESSION['error_message'] = 'Challenge not found';
                self::redirectToDashboard();
                exit;
            }
            
            // Parse dates properly
            $start_date_parts = ['', '', ''];
            $end_date_parts = ['', '', ''];
            
            if ($challenge['start_date']) {
                $start_date = date_create($challenge['start_date']);
                if ($start_date) {
                    $start_date_parts = [
                        date_format($start_date, 'Y'),
                        date_format($start_date, 'm'),
                        date_format($start_date, 'd')
                    ];
                }
            }
            
            if ($challenge['end_date']) {
                $end_date = date_create($challenge['end_date']);
                if ($end_date) {
                    $end_date_parts = [
                        date_format($end_date, 'Y'),
                        date_format($end_date, 'm'),
                        date_format($end_date, 'd')
                    ];
                }
            }
            
            $_SESSION['edit_challenge'] = [
                'id' => $challenge['id'],
                'title' => $challenge['title'],
                'description' => $challenge['description'],
                'type' => $challenge['type'],
                'points' => $challenge['points'],
                'criteria' => $challenge['criteria'],
                'status' => $challenge['status'],
                'tree_level' => $challenge['tree_level'],
                'tree_order' => $challenge['tree_order'],
                'schedule_type' => $challenge['schedule_type'],
                'time_limit_minutes' => $challenge['time_limit_minutes'],
                'recurrence_pattern' => $challenge['recurrence_pattern'],
                'category' => $challenge['category'] ?? 'General',
                'skill_tags' => $challenge['skill_tags'] ?? '',
                'start_date_day' => $start_date_parts[2] ?? '',
                'start_date_month' => $start_date_parts[1] ?? '',
                'start_date_year' => $start_date_parts[0] ?? '',
                'end_date_day' => $end_date_parts[2] ?? '',
                'end_date_month' => $end_date_parts[1] ?? '',
                'end_date_year' => $end_date_parts[0] ?? ''
            ];
            
            self::redirectToDashboard();
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error loading challenge: ' . $e->getMessage();
            self::redirectToDashboard();
            exit;
        }
    }
    
    private static function createChallenge($pdo) {
        try {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $type = $_POST['type'] ?? '';
            $points = (int)($_POST['points'] ?? 0);
            $criteria = trim($_POST['criteria'] ?? '');
            $status = $_POST['status'] ?? '';
            $createdBy = (int)($_POST['createdBy'] ?? ($_SESSION['userID'] ?? 1));
            $prerequisite_id = !empty($_POST['prerequisite_id']) ? (int)$_POST['prerequisite_id'] : null;
            $tree_level = (int)($_POST['tree_level'] ?? 0);
            $tree_order = (int)($_POST['tree_order'] ?? 0);
            $schedule_type = $_POST['schedule_type'] ?? 'none';
            $time_limit_minutes = !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null;
            $recurrence_pattern = $_POST['recurrence_pattern'] ?? null;
            $category = trim($_POST['category'] ?? 'General');
            $skill_tags = '';
            
            // Handle skill tags array
            if (isset($_POST['skill_tags']) && is_array($_POST['skill_tags'])) {
                $skill_tags = implode(',', array_filter(array_map('trim', $_POST['skill_tags'])));
            }

            // Validation
            if(empty($title)){
                throw new Exception("Title is required");
            }
            if(strlen($title) < 3){
                throw new Exception("Title must be at least 3 characters");
            }
            if(empty($description)){
                throw new Exception("Description is required");
            }
            if(strlen($description) < 3){
                throw new Exception("Description must be at least 3 characters");
            }
            if(empty($type)){
                throw new Exception("Type is required");
            }
            if(!in_array($type, ['course','time','social','weekly','monthly','timed'])){
                throw new Exception("Invalid type selected");
            }
            if($points < 1){
                throw new Exception("Points must be at least 1");
            }
            if($points > 1000){
                throw new Exception("Points must not exceed 1000");
            }
            if(empty($status)){
                throw new Exception("Status is required");
            }
            if(!in_array($status, ['Active','Inactive'])){
                throw new Exception("Invalid status selected");
            }
            if($tree_level < 0 || $tree_level > 10){
                throw new Exception("Tree level must be between 0 and 10");
            }

            $start_date_day = $_POST['start_date_day'] ?? '';
            $start_date_month = $_POST['start_date_month'] ?? '';
            $start_date_year = $_POST['start_date_year'] ?? '';
            $end_date_day = $_POST['end_date_day'] ?? '';
            $end_date_month = $_POST['end_date_month'] ?? '';
            $end_date_year = $_POST['end_date_year'] ?? '';

            $start_date = null;
            $end_date = null;

            if ($schedule_type === 'time_limited') {
                $start_date = self::validateDate($start_date_day, $start_date_month, $start_date_year, 'start date');
                if ($start_date === false) {
                    throw new Exception("Invalid start date");
                }
                
                $end_date = self::validateDate($end_date_day, $end_date_month, $end_date_year, 'end date');
                if ($end_date === false) {
                    throw new Exception("Invalid end date");
                }
                
                if ($start_date && $end_date && $end_date <= $start_date) {
                    throw new Exception("End date must be after start date");
                }
                
                if ($time_limit_minutes && ($time_limit_minutes < 1 || $time_limit_minutes > 1440)) {
                    throw new Exception("Time limit must be between 1 and 1440 minutes");
                }
            }

            $ok = Challenges::create($pdo, $title, $description, $type, $points, $criteria, $status, $createdBy, $prerequisite_id, $tree_level, $tree_order, $schedule_type, $start_date, $end_date, $recurrence_pattern, $time_limit_minutes, $category, $skill_tags);
            
            if ($ok) {
                $_SESSION['success_message'] = 'Challenge created successfully';
                self::redirectToDashboard();
                exit;
            } else {
                throw new Exception("Failed to create challenge");
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            self::redirectToDashboard();
            exit;
        }
    }

    private static function updateChallenge($pdo) {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error_message'] = 'Invalid challenge ID';
            self::redirectToDashboard();
            exit;
        }
        
        try {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $type = $_POST['type'] ?? '';
            $points = (int)($_POST['points'] ?? 0);
            $criteria = trim($_POST['criteria'] ?? '');
            $status = $_POST['status'] ?? '';
            $prerequisite_id = !empty($_POST['prerequisite_id']) ? (int)$_POST['prerequisite_id'] : null;
            $tree_level = (int)($_POST['tree_level'] ?? 0);
            $tree_order = (int)($_POST['tree_order'] ?? 0);
            $schedule_type = $_POST['schedule_type'] ?? 'none';
            $time_limit_minutes = !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null;
            $recurrence_pattern = $_POST['recurrence_pattern'] ?? null;
            $category = trim($_POST['category'] ?? 'General');
            $skill_tags = '';
            
            // Handle skill tags array
            if (isset($_POST['skill_tags']) && is_array($_POST['skill_tags'])) {
                $skill_tags = implode(',', array_filter(array_map('trim', $_POST['skill_tags'])));
            }

            // Validation
            if(empty($title)){
                throw new Exception("Title is required");
            }
            if(strlen($title) < 3){
                throw new Exception("Title must be at least 3 characters");
            }
            if(empty($description)){
                throw new Exception("Description is required");
            }
            if(strlen($description) < 3){
                throw new Exception("Description must be at least 3 characters");
            }
            if(empty($type)){
                throw new Exception("Type is required");
            }
            if(!in_array($type, ['course','time','social','weekly','monthly','timed'])){
                throw new Exception("Invalid type selected");
            }
            if($points < 1){
                throw new Exception("Points must be at least 1");
            }
            if($points > 1000){
                throw new Exception("Points must not exceed 1000");
            }
            if(empty($status)){
                throw new Exception("Status is required");
            }
            if(!in_array($status, ['Active','Inactive'])){
                throw new Exception("Invalid status selected");
            }
            if($tree_level < 0 || $tree_level > 10){
                throw new Exception("Tree level must be between 0 and 10");
            }

            $start_date_day = $_POST['start_date_day'] ?? '';
            $start_date_month = $_POST['start_date_month'] ?? '';
            $start_date_year = $_POST['start_date_year'] ?? '';
            $end_date_day = $_POST['end_date_day'] ?? '';
            $end_date_month = $_POST['end_date_month'] ?? '';
            $end_date_year = $_POST['end_date_year'] ?? '';

            $start_date = null;
            $end_date = null;

            if ($schedule_type === 'time_limited') {
                $start_date = self::validateDate($start_date_day, $start_date_month, $start_date_year, 'start date');
                if ($start_date === false) {
                    throw new Exception("Invalid start date");
                }
                
                $end_date = self::validateDate($end_date_day, $end_date_month, $end_date_year, 'end date');
                if ($end_date === false) {
                    throw new Exception("Invalid end date");
                }
                
                if ($start_date && $end_date && $end_date <= $start_date) {
                    throw new Exception("End date must be after start date");
                }
                
                if ($time_limit_minutes && ($time_limit_minutes < 1 || $time_limit_minutes > 1440)) {
                    throw new Exception("Time limit must be between 1 and 1440 minutes");
                }
            }

            $ok = Challenges::update($pdo, $id, $title, $description, $type, $points, $criteria, $status, $prerequisite_id, $tree_level, $tree_order, $schedule_type, $start_date, $end_date, $recurrence_pattern, $time_limit_minutes, $category, $skill_tags);
            
            if ($ok) {
                $_SESSION['success_message'] = 'Challenge updated successfully';
                self::redirectToDashboard();
                exit;
            } else {
                throw new Exception("Failed to update challenge");
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            self::redirectToDashboard();
            exit;
        }
    }

    private static function deleteChallenge($pdo) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error_message'] = 'Invalid challenge ID';
            self::redirectToDashboard();
            exit;
        }
        
        try {
            $ok = Challenges::delete($pdo, $id);
            if ($ok === false) {
                $_SESSION['error_message'] = 'Cannot delete challenge. It is a prerequisite for other challenges.';
                self::redirectToDashboard();
                exit;
            }
            $_SESSION['success_message'] = 'Challenge deleted successfully';
            self::redirectToDashboard();
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error deleting challenge: ' . $e->getMessage();
            self::redirectToDashboard();
            exit;
        }
    }

    private static function completeChallenge($pdo, $studentID) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error_message'] = 'Invalid challenge ID';
            header('Location: ../Views/front-office/Challenges.php');
            exit;
        }
        
        try {
            $result = Challenges::complete($pdo, $id, $studentID);
            if ($result === false) {
                $_SESSION['error_message'] = 'Cannot complete challenge. You may have already completed it or not met prerequisites.';
                header('Location: ../Views/front-office/Challenges.php');
                exit;
            }
            $_SESSION['success_message'] = 'Challenge completed successfully! Points awarded: ' . $result;
            header('Location: ../Views/front-office/Challenges.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error completing challenge: ' . $e->getMessage();
            header('Location: ../Views/front-office/Challenges.php');
            exit;
        }
    }

    private static function showLeaderboard($pdo) {
        try {
            require_once __DIR__ . '/../Models/Users.php';
            $list = Users::getAll($pdo);
            echo 'success';
        } catch (Exception $e) {
            echo 'Error loading leaderboard: ' . $e->getMessage();
        }
    }

    private static function addRewardToChallenge($pdo) {
        try {
            $challengeID = (int)($_POST['challenge_id'] ?? 0);
            $rewardID = (int)($_POST['reward_id'] ?? 0);
            $userID = $_SESSION['userID'] ?? 1;
            
            if ($challengeID <= 0 || $rewardID <= 0) {
                throw new Exception("Invalid challenge or reward ID");
            }
            
            $ok = Challenges::addRewardToChallenge($pdo, $challengeID, $rewardID, $userID);
            if ($ok) {
                $_SESSION['success_message'] = 'Reward added to challenge successfully';
                self::redirectToDashboard();
                exit;
            } else {
                throw new Exception("Reward already assigned to this challenge");
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            self::redirectToDashboard();
            exit;
        }
    }

    private static function removeRewardFromChallenge($pdo) {
        try {
            $challengeID = (int)($_POST['challenge_id'] ?? 0);
            $rewardID = (int)($_POST['reward_id'] ?? 0);
            
            if ($challengeID <= 0 || $rewardID <= 0) {
                throw new Exception("Invalid challenge or reward ID");
            }
            
            $ok = Challenges::removeRewardFromChallenge($pdo, $challengeID, $rewardID);
            if ($ok) {
                $_SESSION['success_message'] = 'Reward removed from challenge successfully';
                self::redirectToDashboard();
                exit;
            } else {
                throw new Exception("Failed to remove reward");
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            self::redirectToDashboard();
            exit;
        }
    }

    private static function getChallengeRewards($pdo) {
        try {
            $challengeID = (int)($_GET['challenge_id'] ?? 0);
            if ($challengeID <= 0) { 
                throw new Exception("Invalid challenge ID"); 
            }
            
            $rewards = Challenges::getChallengeRewards($pdo, $challengeID);
            $_SESSION['challenge_rewards'] = $rewards;
            self::redirectToDashboard('view_rewards='.$challengeID);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            self::redirectToDashboard();
            exit;
        }
    }

    private static function rateChallenge($pdo, $studentID) {
        try {
            $challengeID = (int)($_POST['challenge_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            
            if ($challengeID <= 0) {
                throw new Exception("Invalid challenge ID");
            }
            
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Rating must be between 1 and 5");
            }
            
            // Check if student has completed this challenge
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'challenge_complete' AND target_id = ?");
            $stmt->execute([$studentID, $challengeID]);
            
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("You must complete a challenge before rating it");
            }
            
            $ok = Challenges::addRating($pdo, $challengeID, $studentID, $rating, $comment);
            
            if ($ok) {
                echo 'success';
                exit;
            } else {
                throw new Exception("Failed to submit rating");
            }
            
        } catch (Exception $e) {
            echo 'error: ' . $e->getMessage();
            exit;
        }
    }

    private static function validateDate($day, $month, $year, $fieldName) {
        if (empty($day) || empty($month) || empty($year)) {
            return null;
        }
        
        $day = (int)$day;
        $month = (int)$month;
        $year = (int)$year;
        
        if (!checkdate($month, $day, $year)) {
            return false;
        }
        
        return $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    }
    
    private static function redirectToDashboard($query = '') {
        $role = $_SESSION['userRole'] ?? 'admin';
        
        if ($role == 'admin') {
            $url = '../Views/admin-back-office/Challenges.php';
        } else if ($role == 'teacher') {
            $url = '../Views/teacher-front-office/Challenges.php';
        } else {
            $url = '../Views/front-office/Challenges.php';
        }
        
        if (!empty($query)) {
            $url .= '?' . $query;
        }
        
        header('Location: ' . $url);
        exit;
    }
}

ChallengesController::handle($pdo);
?>