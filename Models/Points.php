<?php
class Points {
    public static function getBalance($pdo, $userID) {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$userID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['points'] : 0;
    }

    public static function getHistory($pdo, $userID) {
        $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addPoints($pdo, $userID, $points, $activity_type = 'points_award', $details = 'Points awarded', $target_id = null) {
        try {
            // Update user points
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->execute([$points, $userID]);
            
            // Log the activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, target_id, points_amount, details) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userID, $activity_type, $target_id, $points, $details]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error adding points: " . $e->getMessage());
            return false;
        }
    }

    public static function deductPoints($pdo, $userID, $points, $target_id = null, $details = 'Points deducted') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->execute([$points, $userID]);
            
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, target_id, points_amount, details) VALUES (?, 'redeem_reward', ?, ?, ?)");
            $stmt->execute([$userID, $target_id, -$points, $details]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error deducting points: " . $e->getMessage());
            return false;
        }
    }

    public static function getLeaderboard($pdo, $limit = 10) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.points, 
                   rt.name as tier_name, rt.badge_name
            FROM users u
            LEFT JOIN reward_tiers rt ON u.points BETWEEN rt.min_points AND COALESCE(rt.max_points, 999999)
            WHERE u.role = 'student' AND rt.status = 'Active'
            ORDER BY u.points DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// USERS CLASS
class Users {
    public static function getAll($pdo, $role = null) {
        if ($role) {
            $stmt = $pdo->prepare("SELECT id, name, email, points, role, created_at FROM users WHERE role = ? ORDER BY points DESC");
            $stmt->execute([$role]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email, points, role, created_at FROM users ORDER BY role, points DESC");
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByID($pdo, $id) {
        $stmt = $pdo->prepare("SELECT id, name, email, points, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getStudents($pdo) {
        return self::getAll($pdo, 'student');
    }

    public static function getTeachers($pdo) {
        return self::getAll($pdo, 'teacher');
    }

    public static function getAdmins($pdo) {
        return self::getAll($pdo, 'admin');
    }
}
?>