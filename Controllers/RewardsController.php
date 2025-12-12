<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Models/Rewards.php';
require_once __DIR__ . '/../Models/Points.php';

class RewardsController {
    public static function handle($pdo) {
        $action = $_GET['action'] ?? '';
        
        error_log("RewardsController Session userID: " . ($_SESSION['userID'] ?? 'NOT SET'));
        
        $studentID = $_SESSION['userID'] ?? 3; 
        $userRole = $_SESSION['role'] ?? 'student';
        
        switch ($userRole) {
            case 'teacher':
                $redirectPage = '../Views/teacher-front-office/Rewards.php';
                break;
            case 'admin':
                $redirectPage = '../Views/admin-back-office/Rewards.php';
                break;
            default:
                $redirectPage = '../Views/front-office/Rewards.php';
        }

        switch ($action) {
            case 'create': 
                self::createReward($pdo, $redirectPage); 
                break;
            case 'update': 
                self::updateReward($pdo, $redirectPage); 
                break;
            case 'delete': 
                self::deleteReward($pdo, $redirectPage); 
                break;
            case 'redeem': 
                self::redeemReward($pdo, $studentID); 
                break;
            case 'request_approval': 
                self::requestApproval($pdo, $studentID); 
                break;
            case 'teacher_approve': 
                self::teacherApprove($pdo, $_SESSION['userID'], $redirectPage); 
                break;
            case 'teacher_reject': 
                self::teacherReject($pdo, $_SESSION['userID'], $redirectPage); 
                break;
            case 'redeem_bundle': 
                self::redeemBundle($pdo, $studentID); 
                break;
            default: 
                header("Location: $redirectPage?error=Invalid action"); 
                exit;
        }
    }

    private static function createReward($pdo, $redirectPage) {
        try {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? '';
            $type = $_POST['type'] ?? '';
            $pointsCost = (int)($_POST['pointsCost'] ?? 0);
            $availability = (int)($_POST['availability'] ?? 0);
            $status = $_POST['status'] ?? '';
            $min_tier = $_POST['min_tier'] ?? '';

            // Custom validation
            $errors = [];
            if(strlen($title) < 3){
                $errors[] = "Title must be at least 3 characters";
            }
            if(strlen($description) < 3){
                $errors[] = "Description must be at least 3 characters";
            }
            if(!in_array($category, ['Badge','Bonus Points','Certificate','Perk','Discount','Special'])){
                $errors[] = "Invalid category selected";
            }
            if($pointsCost < 1){
                $errors[] = "Points cost must be at least 1";
            }
            if($pointsCost > 1000){
                $errors[] = "Points cost cannot exceed 1000";
            }
            if($availability < 0){
                $errors[] = "Availability cannot be negative";
            }
            if($availability > 10000){
                $errors[] = "Availability cannot exceed 10000";
            }
            if(!in_array($status, ['Active','Inactive'])){
                $errors[] = "Invalid status selected";
            }

            if(!empty($errors)){
                $_SESSION['error'] = implode(", ", $errors);
                header("Location: $redirectPage");
                exit;
            }

            $ok = Rewards::create($pdo, $title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier);
            
            if ($ok) {
                $_SESSION['success'] = 'Reward created successfully';
                header("Location: $redirectPage");
                exit;
            } else {
                throw new Exception("Failed to create reward");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: $redirectPage");
            exit;
        }
    }

    private static function updateReward($pdo, $redirectPage) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid reward ID';
            header("Location: $redirectPage");
            exit;
        }
        
        try {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? '';
            $type = $_POST['type'] ?? '';
            $pointsCost = (int)($_POST['pointsCost'] ?? 0);
            $availability = (int)($_POST['availability'] ?? 0);
            $status = $_POST['status'] ?? '';
            $min_tier = $_POST['min_tier'] ?? '';

            // Custom validation
            $errors = [];
            if(strlen($title) < 3){
                $errors[] = "Title must be at least 3 characters";
            }
            if(strlen($description) < 3){
                $errors[] = "Description must be at least 3 characters";
            }
            if(!in_array($category, ['Badge','Bonus Points','Certificate','Perk','Discount','Special'])){
                $errors[] = "Invalid category selected";
            }
            if($pointsCost < 1){
                $errors[] = "Points cost must be at least 1";
            }
            if($pointsCost > 1000){
                $errors[] = "Points cost cannot exceed 1000";
            }
            if($availability < 0){
                $errors[] = "Availability cannot be negative";
            }
            if($availability > 10000){
                $errors[] = "Availability cannot exceed 10000";
            }
            if(!in_array($status, ['Active','Inactive'])){
                $errors[] = "Invalid status selected";
            }

            if(!empty($errors)){
                $_SESSION['error'] = implode(", ", $errors);
                header("Location: $redirectPage");
                exit;
            }

            $ok = Rewards::update($pdo, $id, $title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier);
            
            if ($ok) {
                $_SESSION['success'] = 'Reward updated successfully';
                header("Location: $redirectPage");
                exit;
            } else {
                throw new Exception("Failed to update reward");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: $redirectPage");
            exit;
        }
    }

    private static function deleteReward($pdo, $redirectPage) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid reward ID';
            header("Location: $redirectPage");
            exit;
        }
        
        try {
            $ok = Rewards::delete($pdo, $id);
            if ($ok) {
                $_SESSION['success'] = 'Reward deleted successfully';
                header("Location: $redirectPage");
                exit;
            } else {
                throw new Exception("Failed to delete reward");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: $redirectPage");
            exit;
        }
    }

    private static function redeemReward($pdo, $studentID) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid reward ID';
            header("Location: ../Views/front-office/Rewards.php");
            exit;
        }
        
        try {
            // DEBUG: Log redemption attempt
            error_log("Redeem attempt: Student ID=$studentID, Reward ID=$id");
            
            // Check if student has enough points for direct redemption
            $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->execute([$studentID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $studentPoints = $student['points'] ?? 0;
            
            $stmt = $pdo->prepare("SELECT pointsCost FROM rewards WHERE id = ?");
            $stmt->execute([$id]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            $rewardCost = $reward['pointsCost'] ?? 0;
            
            // If student is missing less than 10 points, require teacher approval
            if ($studentPoints < $rewardCost && ($rewardCost - $studentPoints) <= 10) {
                // Request teacher approval instead of direct redemption
                $stmt = $pdo->prepare("
                    INSERT INTO reward_requests 
                    (student_id, reward_id, student_message, requested_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $message = "Need " . ($rewardCost - $studentPoints) . " more points to redeem this reward";
                $stmt->execute([$studentID, $id, $message]);
                
                $_SESSION['success'] = "Reward request sent to teacher for approval (you need " . ($rewardCost - $studentPoints) . " more points)";
                header("Location: ../Views/front-office/Rewards.php");
                exit;
            }
            
            // Otherwise, proceed with normal redemption
            $result = Rewards::redeem($pdo, $id, $studentID);
            
            if ($result['success']) {
                $_SESSION['success'] = "Reward '{$result['title']}' redeemed successfully! {$result['points']} points deducted.";
                header("Location: ../Views/front-office/Rewards.php");
                exit;
            } else {
                $_SESSION['error'] = $result['message'];
                header("Location: ../Views/front-office/Rewards.php");
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error redeeming reward: ' . $e->getMessage();
            header("Location: ../Views/front-office/Rewards.php");
            exit;
        }
    }
    
    private static function requestApproval($pdo, $studentID) {
        $rewardID = (int)($_GET['id'] ?? 0);
        $message = $_POST['message'] ?? 'Requesting approval for this reward';
        
        if ($rewardID <= 0) {
            $_SESSION['error'] = 'Invalid reward ID';
            header("Location: ../Views/front-office/Rewards.php");
            exit;
        }
        
        try {
            // Check if request already exists
            $stmt = $pdo->prepare("SELECT id FROM reward_requests WHERE student_id = ? AND reward_id = ? AND status = 'pending'");
            $stmt->execute([$studentID, $rewardID]);
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'You already have a pending request for this reward';
            } else {
                // Create new request
                $stmt = $pdo->prepare("
                    INSERT INTO reward_requests 
                    (student_id, reward_id, student_message, requested_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$studentID, $rewardID, $message]);
                
                $_SESSION['success'] = 'Reward request sent to teacher for approval!';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error submitting request: ' . $e->getMessage();
        }
        
        header("Location: ../Views/front-office/Rewards.php");
        exit;
    }
    
    private static function teacherApprove($pdo, $teacherID, $redirectPage) {
        $requestID = (int)($_GET['request_id'] ?? 0);
        $notes = $_POST['notes'] ?? 'Approved by teacher';
        
        if ($requestID <= 0) {
            $_SESSION['error'] = 'Invalid request ID';
            header("Location: $redirectPage");
            exit;
        }
        
        try {
            // Get request details
            $stmt = $pdo->prepare("
                SELECT rr.*, r.pointsCost, s.id as student_id, s.name as student_name, r.title as reward_title
                FROM reward_requests rr
                JOIN rewards r ON rr.reward_id = r.id
                JOIN users s ON rr.student_id = s.id
                WHERE rr.id = ? AND rr.status = 'pending'
            ");
            $stmt->execute([$requestID]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                $_SESSION['error'] = 'Request not found or already processed';
                header("Location: $redirectPage");
                exit;
            }
            
            // Update request status
            $stmt = $pdo->prepare("
                UPDATE reward_requests 
                SET status = 'approved', 
                    teacher_id = ?,
                    teacher_response = ?,
                    responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$teacherID, $notes, $requestID]);
            
            // Deduct points and process redemption
            $result = Rewards::redeem($pdo, $request['reward_id'], $request['student_id']);
            
            if ($result['success']) {
                // Log teacher action
                $stmt = $pdo->prepare("
                    INSERT INTO teacher_actions 
                    (teacher_id, action_type, student_id, reward_id, points, reason, created_at) 
                    VALUES (?, 'approve_reward', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$teacherID, $request['student_id'], $request['reward_id'], $request['pointsCost'], $notes]);
                
                $_SESSION['success'] = "Approved! " . $request['student_name'] . " redeemed '{$request['reward_title']}'";
            } else {
                $_SESSION['error'] = 'Failed to process redemption: ' . $result['message'];
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error approving request: ' . $e->getMessage();
        }
        
        header("Location: $redirectPage");
        exit;
    }
    
    private static function teacherReject($pdo, $teacherID, $redirectPage) {
        $requestID = (int)($_GET['request_id'] ?? 0);
        $notes = $_POST['notes'] ?? 'Rejected by teacher';
        
        if ($requestID <= 0) {
            $_SESSION['error'] = 'Invalid request ID';
            header("Location: $redirectPage");
            exit;
        }
        
        try {
            // Update request status
            $stmt = $pdo->prepare("
                UPDATE reward_requests 
                SET status = 'rejected', 
                    teacher_id = ?,
                    teacher_response = ?,
                    responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$teacherID, $notes, $requestID]);
            
            // Log teacher action
            $stmt = $pdo->prepare("
                INSERT INTO teacher_actions 
                (teacher_id, action_type, reason, created_at) 
                VALUES (?, 'reject_reward', ?, NOW())
            ");
            $stmt->execute([$teacherID, $notes]);
            
            $_SESSION['success'] = 'Reward request rejected';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error rejecting request: ' . $e->getMessage();
        }
        
        header("Location: $redirectPage");
        exit;
    }
    
    private static function redeemBundle($pdo, $studentID) {
        $bundleID = (int)($_GET['id'] ?? 0);
        
        if ($bundleID <= 0) {
            $_SESSION['error'] = 'Invalid bundle ID';
            header("Location: ../Views/front-office/Rewards.php");
            exit;
        }
        
        try {
            $result = Rewards::redeemBundle($pdo, $bundleID, $studentID);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error redeeming bundle: ' . $e->getMessage();
        }
        
        header("Location: ../Views/front-office/Rewards.php");
        exit;
    }
}

RewardsController::handle($pdo);
?>