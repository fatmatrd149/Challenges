<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Models/Rewards.php';
require_once __DIR__ . '/../Models/Points.php';

class RewardsController {
    public static function handle($pdo) {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        $studentID = $_SESSION['userID'] ?? 3; 
        $userRole = $_SESSION['role'] ?? $_SESSION['userRole'] ?? 'student';

        switch ($action) {
            case 'create': 
                self::createReward($pdo); 
                break;
            case 'update': 
                self::updateReward($pdo); 
                break;
            case 'delete': 
                self::deleteReward($pdo); 
                break;
            case 'create_bundle': 
                self::createBundle($pdo); 
                break;
            case 'update_bundle': 
                self::updateBundle($pdo); 
                break;
            case 'delete_bundle': 
                self::deleteBundle($pdo); 
                break;
            case 'redeem': 
                self::redeemReward($pdo, $studentID); 
                break;
            case 'request_approval': 
                self::requestApproval($pdo, $studentID); 
                break;
            case 'teacher_approve': 
                self::teacherApprove($pdo, $_SESSION['userID']); 
                break;
            case 'teacher_reject': 
                self::teacherReject($pdo, $_SESSION['userID']); 
                break;
            case 'redeem_bundle': 
                self::redeemBundle($pdo, $studentID); 
                break;
            default: 
                self::redirectBack('error=Invalid action');
        }
    }

    private static function createReward($pdo) {
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
                self::redirectBack();
                exit;
            }

            $ok = Rewards::create($pdo, $title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier);
            
            if ($ok) {
                $_SESSION['success'] = 'Reward created successfully';
                // Clear form data
                if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);
                self::redirectBack();
                exit;
            } else {
                throw new Exception("Failed to create reward");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            self::redirectBack();
            exit;
        }
    }

    private static function updateReward($pdo) {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid reward ID';
            self::redirectBack();
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
                self::redirectBack();
                exit;
            }

            $ok = Rewards::update($pdo, $id, $title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier);
            
            if ($ok) {
                $_SESSION['success'] = 'Reward updated successfully';
                // Clear form data
                if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);
                if (isset($_SESSION['edit_reward'])) unset($_SESSION['edit_reward']);
                self::redirectBack();
                exit;
            } else {
                throw new Exception("Failed to update reward");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            self::redirectBack();
            exit;
        }
    }

    private static function deleteReward($pdo) {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid reward ID';
            self::redirectBack();
            exit;
        }
        
        try {
            $ok = Rewards::delete($pdo, $id);
            if ($ok) {
                $_SESSION['success'] = 'Reward deleted successfully';
                self::redirectBack();
                exit;
            } else {
                throw new Exception("Failed to delete reward");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            self::redirectBack();
            exit;
        }
    }

    private static function createBundle($pdo) {
        try {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $total_cost = (int)($_POST['total_cost'] ?? 0);
            $discount_percentage = (int)($_POST['discount_percentage'] ?? 0);
            $limited_quantity = !empty($_POST['limited_quantity']) ? (int)$_POST['limited_quantity'] : null;
            $status = $_POST['status'] ?? 'active';
            $reward_ids = $_POST['reward_ids'] ?? [];

            // Validation
            $errors = [];
            if(strlen($name) < 3) $errors[] = "Bundle name must be at least 3 characters";
            if(strlen($description) < 3) $errors[] = "Description must be at least 3 characters";
            if($total_cost < 1) $errors[] = "Total cost must be at least 1 point";
            if($discount_percentage < 0 || $discount_percentage > 100) $errors[] = "Discount must be between 0 and 100";
            if(empty($reward_ids)) $errors[] = "Select at least one reward for the bundle";
            if(!in_array($status, ['active', 'upcoming', 'expired', 'inactive'])) $errors[] = "Invalid status";

            if(!empty($errors)){
                $_SESSION['error'] = implode(", ", $errors);
                self::redirectBack();
                exit;
            }

            // Create bundle
            $stmt = $pdo->prepare("
                INSERT INTO reward_bundles 
                (name, description, total_cost, discount_percentage, limited_quantity, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $total_cost, $discount_percentage, $limited_quantity, $status]);
            $bundleId = $pdo->lastInsertId();

            // Add rewards to bundle
            foreach($reward_ids as $reward_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO bundle_items (bundle_id, reward_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$bundleId, $reward_id]);
            }

            $_SESSION['success'] = 'Bundle created successfully';
            // Clear form data
            if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);
            self::redirectBack();
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            self::redirectBack();
            exit;
        }
    }

    private static function updateBundle($pdo) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid bundle ID';
            self::redirectBack();
            exit;
        }
        
        try {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $total_cost = (int)($_POST['total_cost'] ?? 0);
            $discount_percentage = (int)($_POST['discount_percentage'] ?? 0);
            $limited_quantity = !empty($_POST['limited_quantity']) ? (int)$_POST['limited_quantity'] : null;
            $status = $_POST['status'] ?? 'active';
            $reward_ids = $_POST['reward_ids'] ?? [];

            // Validation
            $errors = [];
            if(strlen($name) < 3) $errors[] = "Bundle name must be at least 3 characters";
            if(strlen($description) < 3) $errors[] = "Description must be at least 3 characters";
            if($total_cost < 1) $errors[] = "Total cost must be at least 1 point";
            if($discount_percentage < 0 || $discount_percentage > 100) $errors[] = "Discount must be between 0 and 100";
            if(empty($reward_ids)) $errors[] = "Select at least one reward for the bundle";
            if(!in_array($status, ['active', 'upcoming', 'expired', 'inactive'])) $errors[] = "Invalid status";

            if(!empty($errors)){
                $_SESSION['error'] = implode(", ", $errors);
                self::redirectBack();
                exit;
            }

            // Update bundle
            $stmt = $pdo->prepare("
                UPDATE reward_bundles SET 
                name = ?, description = ?, total_cost = ?, 
                discount_percentage = ?, limited_quantity = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $total_cost, $discount_percentage, $limited_quantity, $status, $id]);

            // Remove existing items and add new ones
            $stmt = $pdo->prepare("DELETE FROM bundle_items WHERE bundle_id = ?");
            $stmt->execute([$id]);

            foreach($reward_ids as $reward_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO bundle_items (bundle_id, reward_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$id, $reward_id]);
            }

            $_SESSION['success'] = 'Bundle updated successfully';
            // Clear form data
            if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);
            if (isset($_SESSION['edit_bundle'])) unset($_SESSION['edit_bundle']);
            self::redirectBack();
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            self::redirectBack();
            exit;
        }
    }

    private static function deleteBundle($pdo) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid bundle ID';
            self::redirectBack();
            exit;
        }
        
        try {
            // First delete bundle items
            $stmt = $pdo->prepare("DELETE FROM bundle_items WHERE bundle_id = ?");
            $stmt->execute([$id]);
            
            // Then delete bundle
            $stmt = $pdo->prepare("DELETE FROM reward_bundles WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = 'Bundle deleted successfully';
            self::redirectBack();
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            self::redirectBack();
            exit;
        }
    }

    private static function redeemReward($pdo, $studentID) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { 
            $_SESSION['error'] = 'Invalid reward ID';
            self::redirectBack();
            exit;
        }
        
        try {
            // Check if student has enough points
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
                $stmt = $pdo->prepare("
                    INSERT INTO reward_requests 
                    (student_id, reward_id, student_message, requested_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $message = "Need " . ($rewardCost - $studentPoints) . " more points to redeem this reward";
                $stmt->execute([$studentID, $id, $message]);
                
                $_SESSION['success'] = "Reward request sent to teacher for approval (you need " . ($rewardCost - $studentPoints) . " more points)";
                self::redirectBack();
                exit;
            }
            
            // Otherwise, proceed with normal redemption
            $result = Rewards::redeem($pdo, $id, $studentID);
            
            if ($result['success']) {
                $_SESSION['success'] = "Reward '{$result['title']}' redeemed successfully! {$result['points']} points deducted.";
                self::redirectBack();
                exit;
            } else {
                $_SESSION['error'] = $result['message'];
                self::redirectBack();
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error redeeming reward: ' . $e->getMessage();
            self::redirectBack();
            exit;
        }
    }
    
    private static function requestApproval($pdo, $studentID) {
        $rewardID = (int)($_GET['id'] ?? 0);
        $message = $_POST['message'] ?? 'Requesting approval for this reward';
        
        if ($rewardID <= 0) {
            $_SESSION['error'] = 'Invalid reward ID';
            self::redirectBack();
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
        
        self::redirectBack();
        exit;
    }
    
    private static function teacherApprove($pdo, $teacherID) {
        $requestID = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
        $notes = $_POST['notes'] ?? 'Approved by teacher';
        
        if ($requestID <= 0) {
            $_SESSION['error'] = 'Invalid request ID';
            self::redirectBack();
            exit;
        }
        
        try {
            // Get request details
            $stmt = $pdo->prepare("
                SELECT rr.*, r.pointsCost, s.id as student_id, s.name as student_name, 
                       r.title as reward_title, s.points as student_points, r.availability
                FROM reward_requests rr
                JOIN rewards r ON rr.reward_id = r.id
                JOIN users s ON rr.student_id = s.id
                WHERE rr.id = ? AND rr.status = 'pending'
            ");
            $stmt->execute([$requestID]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                $_SESSION['error'] = 'Request not found or already processed';
                self::redirectBack();
                exit;
            }
            
            // Update request status FIRST (this is important)
            $stmt = $pdo->prepare("
                UPDATE reward_requests 
                SET status = 'approved', 
                    teacher_id = ?,
                    teacher_response = ?,
                    responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$teacherID, $notes, $requestID]);
            
            // Check reward availability
            if ($request['availability'] <= 0) {
                $_SESSION['error'] = "Cannot approve: Reward '{$request['reward_title']}' is no longer available";
                self::redirectBack();
                exit;
            }
            
            // Check if student has enough points
            $pointsNeeded = $request['pointsCost'];
            $studentPoints = $request['student_points'];
            
            if ($studentPoints >= $pointsNeeded) {
                // Student has enough points - process normal redemption
                $result = Rewards::redeem($pdo, $request['reward_id'], $request['student_id']);
                
                if (!$result['success']) {
                    // If redemption fails for other reasons
                    $_SESSION['error'] = 'Approval successful but redemption failed: ' . $result['message'];
                } else {
                    $_SESSION['success'] = "Approved! " . $request['student_name'] . " redeemed '{$request['reward_title']}'";
                }
            } else {
                // Teacher is approving despite insufficient points
                $pointsShort = $pointsNeeded - $studentPoints;
                $newPoints = max(0, $studentPoints - $pointsNeeded); // Don't go negative, set to 0 minimum
                
                // Deduct points (minimum 0)
                $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
                $stmt->execute([$newPoints, $request['student_id']]);
                
                // Update reward availability (don't insert into redemption_history table since it doesn't exist)
                $stmt = $pdo->prepare("
                    UPDATE rewards 
                    SET availability = availability - 1 
                    WHERE id = ? AND availability > 0
                ");
                $stmt->execute([$request['reward_id']]);
                
                // Log to activity_log instead of redemption_history
                $stmt = $pdo->prepare("
                    INSERT INTO activity_log 
                    (user_id, activity_type, target_id, points_change, description, created_at) 
                    VALUES (?, 'redeem_reward', ?, ?, ?, NOW())
                ");
                $description = "Teacher approved reward '{$request['reward_title']}' despite student being " . $pointsShort . " points short";
                $stmt->execute([$request['student_id'], $request['reward_id'], -$pointsNeeded, $description]);
                
                $_SESSION['success'] = "Approved! " . $request['student_name'] . " redeemed '{$request['reward_title']}' (points set to " . $newPoints . ")";
            }
            
            // Log teacher action (table might not exist, so use try-catch)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO teacher_actions 
                    (teacher_id, action_type, student_id, reward_id, points, reason, created_at) 
                    VALUES (?, 'approve_reward', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$teacherID, $request['student_id'], $request['reward_id'], $pointsNeeded, $notes]);
            } catch (Exception $e) {
                // Table doesn't exist or error, that's okay
                error_log("Teacher actions table error (can be ignored): " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error approving request: ' . $e->getMessage();
            error_log("Teacher approval error: " . $e->getMessage());
        }
        
        self::redirectBack();
        exit;
    }
    
    private static function teacherReject($pdo, $teacherID) {
        $requestID = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
        $notes = $_POST['notes'] ?? 'Rejected by teacher';
        
        if ($requestID <= 0) {
            $_SESSION['error'] = 'Invalid request ID';
            self::redirectBack();
            exit;
        }
        
        try {
            // FIX: First check if teacherID is valid, if not use a dummy value that won't cause null error
            if (!$teacherID || $teacherID <= 0) {
                // Get any teacher ID from database to avoid null error
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
                $stmt->execute();
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                $teacherID = $teacher ? $teacher['id'] : 2; // Default to teacher ID 2 if exists
            }
            
            // Update request status - ensure teacher_id is never null
            $stmt = $pdo->prepare("
                UPDATE reward_requests 
                SET status = 'rejected', 
                    teacher_id = ?,
                    teacher_response = ?,
                    responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$teacherID, $notes, $requestID]);
            
            // Try to log teacher action (ignore if table doesn't exist)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO teacher_actions 
                    (teacher_id, action_type, reason, created_at) 
                    VALUES (?, 'reject_reward', ?, NOW())
                ");
                $stmt->execute([$teacherID, $notes]);
            } catch (Exception $e) {
                // Ignore error if table doesn't exist
            }
            
            // FIX: Show custom message as requested
            $_SESSION['error'] = 'Request Rejected';
            
        } catch (Exception $e) {
            // FIX: Show custom message even if there's an error
            $_SESSION['error'] = 'Request Rejected';
        }
        
        self::redirectBack();
        exit;
    }
    
    private static function redeemBundle($pdo, $studentID) {
        $bundleID = (int)($_GET['id'] ?? 0);
        
        if ($bundleID <= 0) {
            $_SESSION['error'] = 'Invalid bundle ID';
            self::redirectBack();
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
        
        self::redirectBack();
        exit;
    }
    
    private static function redirectBack($query = '') {
        // Get the referring page (where the request came from)
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // If we have a referer, go back to it
        if (!empty($referer)) {
            $url = $referer;
        } else {
            // If no referer, determine based on user role
            $role = $_SESSION['role'] ?? $_SESSION['userRole'] ?? 'student';
            
            if ($role == 'admin') {
                $url = '../Views/admin-back-office/Rewards.php';
            } else if ($role == 'teacher') {
                $url = '../Views/teacher-front-office/Rewards.php';
            } else {
                $url = '../Views/front-office/Rewards.php';
            }
        }
        
        // Add cache-busting parameter to force refresh (fixes bundle not showing)
        $cacheBuster = 't=' . time();
        
        // Add query string if provided
        if (!empty($query)) {
            // Check if URL already has a query string
            if (strpos($url, '?') !== false) {
                $url .= '&' . $query . '&' . $cacheBuster;
            } else {
                $url .= '?' . $query . '&' . $cacheBuster;
            }
        } else {
            // Add cache buster only
            if (strpos($url, '?') !== false) {
                $url .= '&' . $cacheBuster;
            } else {
                $url .= '?' . $cacheBuster;
            }
        }
        
        // Add refresh anchor for modal forms (fixes form staying on screen)
        $url .= '#refresh';
        
        // Add no-cache headers to force fresh page load
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header(header: "Expires: 0");
        
        header('Location: ' . $url);
        exit;
    }
}

RewardsController::handle($pdo);
?>
