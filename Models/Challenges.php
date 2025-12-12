<?php
class Challenges {
    public static function getByCreator($pdo, $teacherID) {
        $stmt = $pdo->prepare("SELECT id, title, description, type, points, criteria, status, createdBy, prerequisite_id, tree_level, tree_order, schedule_type, time_limit_minutes, category, skill_tags FROM challenges WHERE createdBy = ? ORDER BY tree_level, tree_order, id DESC");
        $stmt->execute([$teacherID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAll($pdo) {
        $stmt = $pdo->prepare("SELECT id, title, description, type, points, criteria, status, createdBy, prerequisite_id, tree_level, tree_order, schedule_type, time_limit_minutes, category, skill_tags FROM challenges ORDER BY tree_level, tree_order, id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByID($pdo, $id) {
        $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    public static function create($pdo, $title, $description, $type, $points, $criteria, $status, $createdBy, $prerequisite_id = null, $tree_level = 0, $tree_order = 0, $schedule_type = 'none', $start_date = null, $end_date = null, $recurrence_pattern = null, $time_limit_minutes = null, $category = 'General', $skill_tags = '') {
        $stmt = $pdo->prepare("INSERT INTO challenges (title, description, type, points, criteria, status, createdBy, prerequisite_id, tree_level, tree_order, schedule_type, start_date, end_date, recurrence_pattern, time_limit_minutes, category, skill_tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $type, $points, $criteria, $status, $createdBy, $prerequisite_id, $tree_level, $tree_order, $schedule_type, $start_date, $end_date, $recurrence_pattern, $time_limit_minutes, $category, $skill_tags]);
    }

    public static function update($pdo, $id, $title, $description, $type, $points, $criteria, $status, $prerequisite_id = null, $tree_level = 0, $tree_order = 0, $schedule_type = 'none', $start_date = null, $end_date = null, $recurrence_pattern = null, $time_limit_minutes = null, $category = 'General', $skill_tags = '') {
        $stmt = $pdo->prepare("UPDATE challenges SET title = ?, description = ?, type = ?, points = ?, criteria = ?, status = ?, prerequisite_id = ?, tree_level = ?, tree_order = ?, schedule_type = ?, start_date = ?, end_date = ?, recurrence_pattern = ?, time_limit_minutes = ?, category = ?, skill_tags = ? WHERE id = ?");
        return $stmt->execute([$title, $description, $type, $points, $criteria, $status, $prerequisite_id, $tree_level, $tree_order, $schedule_type, $start_date, $end_date, $recurrence_pattern, $time_limit_minutes, $category, $skill_tags, $id]);
    }

    public static function delete($pdo, $id) {
        // Check if this challenge is a prerequisite for others
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM challenges WHERE prerequisite_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return false;
        }
        
        // Also delete from challenge_rewards
        $stmt = $pdo->prepare("DELETE FROM challenge_rewards WHERE challenge_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM challenges WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function complete($pdo, $challengeID, $studentID) {
        // Check if already completed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'challenge_complete' AND target_id = ?");
        $stmt->execute([$studentID, $challengeID]);
        if ($stmt->fetchColumn() > 0) return false;
        
        // Check prerequisites
        $stmt = $pdo->prepare("SELECT prerequisite_id, tree_level FROM challenges WHERE id = ?");
        $stmt->execute([$challengeID]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($challenge['prerequisite_id']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'challenge_complete' AND target_id = ?");
            $stmt->execute([$studentID, $challenge['prerequisite_id']]);
            if ($stmt->fetchColumn() == 0) {
                return false; // Prerequisite not completed
            }
        }
        
        // Get challenge points
        $stmt = $pdo->prepare("SELECT points FROM challenges WHERE id = ?");
        $stmt->execute([$challengeID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        
        $pointsAwarded = $row['points'];
        
        $pdo->beginTransaction();
        try {
            // Award points
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->execute([$pointsAwarded, $studentID]);
            
            // Log completion
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, target_id, points_amount, details) VALUES (?, 'challenge_complete', ?, ?, ?)");
            $stmt->execute([$studentID, $challengeID, $pointsAwarded, 'Challenge completed']);
            
            // Check for tier achievements
            self::checkTierAchievements($pdo, $studentID);
            
            $pdo->commit();
            return $pointsAwarded;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    private static function checkTierAchievements($pdo, $studentID) {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$studentID]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) return;
        
        $points = $student['points'];
        
        // Get all tiers
        $stmt = $pdo->prepare("SELECT * FROM reward_tiers WHERE status = 'Active' ORDER BY min_points ASC");
        $stmt->execute();
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tiers as $tier) {
            if ($points >= $tier['min_points'] && ($tier['max_points'] === null || $points <= $tier['max_points'])) {
                // Check if already achieved this tier
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND activity_type = 'tier_achievement' AND target_id = ?");
                $stmt->execute([$studentID, $tier['id']]);
                if ($stmt->fetchColumn() == 0) {
                    // Log tier achievement
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, target_id, details) VALUES (?, 'tier_achievement', ?, ?)");
                    $stmt->execute([$studentID, $tier['id'], "Reached {$tier['name']} Tier at {$points} points"]);
                }
            }
        }
    }

    public static function getChallengeTree($pdo, $studentID = null) {
        $query = "
            SELECT c.*, 
                   CASE 
                       WHEN al.id IS NOT NULL THEN 1 
                       ELSE 0 
                   END as completed,
                   CASE
                       WHEN c.prerequisite_id IS NULL THEN 1
                       WHEN EXISTS (
                           SELECT 1 FROM activity_log 
                           WHERE user_id = ? AND activity_type = 'challenge_complete' 
                           AND target_id = c.prerequisite_id
                       ) THEN 1
                       ELSE 0
                   END as unlocked
            FROM challenges c 
            LEFT JOIN activity_log al ON c.id = al.target_id 
                AND al.user_id = ? 
                AND al.activity_type = 'challenge_complete'
            ORDER BY c.tree_level, c.tree_order, c.id
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentID, $studentID]);
        $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tree = [];
        foreach ($challenges as $challenge) {
            $level = $challenge['tree_level'];
            if (!isset($tree[$level])) {
                $tree[$level] = [];
            }
            $tree[$level][] = $challenge;
        }
        
        // Sort by levels
        ksort($tree);
        
        return $tree;
    }

    public static function getAvailableChallenges($pdo, $studentID) {
        $query = "
            SELECT c.*
            FROM challenges c
            WHERE c.status = 'Active'
            AND c.id NOT IN (
                SELECT target_id FROM activity_log 
                WHERE user_id = ? AND activity_type = 'challenge_complete'
            )
            AND (
                c.prerequisite_id IS NULL 
                OR c.prerequisite_id IN (
                    SELECT target_id FROM activity_log 
                    WHERE user_id = ? AND activity_type = 'challenge_complete'
                )
            )
            ORDER BY c.tree_level, c.tree_order, c.id
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentID, $studentID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTimeLimitedChallenges($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM challenges 
            WHERE schedule_type = 'time_limited' 
            AND status = 'Active' 
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY end_date ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecurringChallenges($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM challenges 
            WHERE schedule_type = 'recurring' 
            AND status = 'Active'
            ORDER BY tree_level, tree_order
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getChallengeRewards($pdo, $challengeID) {
        $stmt = $pdo->prepare("
            SELECT r.* 
            FROM rewards r 
            JOIN challenge_rewards cr ON r.id = cr.reward_id 
            WHERE cr.challenge_id = ? 
            AND r.status = 'Active'
        ");
        $stmt->execute([$challengeID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addRewardToChallenge($pdo, $challengeID, $rewardID, $userID) {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM challenge_rewards WHERE challenge_id = ? AND reward_id = ?");
        $stmt->execute([$challengeID, $rewardID]);
        if ($stmt->fetchColumn() > 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("INSERT INTO challenge_rewards (challenge_id, reward_id, created_by) VALUES (?, ?, ?)");
        return $stmt->execute([$challengeID, $rewardID, $userID]);
    }

    public static function removeRewardFromChallenge($pdo, $challengeID, $rewardID) {
        $stmt = $pdo->prepare("DELETE FROM challenge_rewards WHERE challenge_id = ? AND reward_id = ?");
        return $stmt->execute([$challengeID, $rewardID]);
    }

    public static function removeAllRewardsFromChallenge($pdo, $challengeID) {
        $stmt = $pdo->prepare("DELETE FROM challenge_rewards WHERE challenge_id = ?");
        return $stmt->execute([$challengeID]);
    }

    public static function getChallengesByLevel($pdo, $level) {
        $stmt = $pdo->prepare("SELECT * FROM challenges WHERE tree_level = ? AND status = 'Active' ORDER BY tree_order, id");
        $stmt->execute([$level]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canAccessLevel($pdo, $studentID, $level) {
        if ($level == 0) return true;
        
        // Check if all challenges from previous level are completed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN al.id IS NOT NULL THEN 1 ELSE 0 END) as completed
            FROM challenges c
            LEFT JOIN activity_log al ON c.id = al.target_id 
                AND al.user_id = ? 
                AND al.activity_type = 'challenge_complete'
            WHERE c.tree_level = ?
        ");
        $stmt->execute([$studentID, $level - 1]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result['total'] > 0 && $result['completed'] == $result['total']);
    }

    public static function getTierProgress($pdo, $studentID) {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$studentID]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            return [
                'current_tier' => null,
                'next_tier' => null,
                'progress' => 0,
                'points_to_next' => 0
            ];
        }
        
        $points = $student['points'];
        
        $stmt = $pdo->prepare("SELECT * FROM reward_tiers WHERE status = 'Active' ORDER BY min_points ASC");
        $stmt->execute();
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $currentTier = null;
        $nextTier = null;
        
        foreach ($tiers as $tier) {
            if ($points >= $tier['min_points'] && 
                ($tier['max_points'] === null || $points <= $tier['max_points'])) {
                $currentTier = $tier;
                break;
            }
        }
        
        if ($currentTier) {
            $currentIndex = array_search($currentTier, array_values($tiers));
            if (isset($tiers[$currentIndex + 1])) {
                $nextTier = $tiers[$currentIndex + 1];
            }
        } else {
            if (!empty($tiers)) {
                $nextTier = $tiers[0];
            }
        }
        
        $progress = 0;
        $pointsToNext = 0;
        
        if ($currentTier && $nextTier) {
            $currentRange = $currentTier['max_points'] - $currentTier['min_points'];
            $pointsInCurrent = $points - $currentTier['min_points'];
            $progress = ($currentRange > 0) ? min(100, ($pointsInCurrent / $currentRange) * 100) : 100;
            $pointsToNext = $nextTier['min_points'] - $points;
        } elseif ($currentTier && !$nextTier) {
            $progress = 100;
            $pointsToNext = 0;
        } elseif (!$currentTier && $nextTier) {
            $progress = ($points / $nextTier['min_points']) * 100;
            $pointsToNext = $nextTier['min_points'] - $points;
        }
        
        return [
            'current_tier' => $currentTier,
            'next_tier' => $nextTier,
            'progress' => $progress,
            'points_to_next' => $pointsToNext
        ];
    }

    // NEW METHODS FOR CATEGORIES, TAGS, AND RATINGS

    public static function getAverageRating($pdo, $challengeId) {
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM challenge_ratings WHERE challenge_id = ?");
        $stmt->execute([$challengeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'average' => $result['avg_rating'] ? round($result['avg_rating'], 1) : 0,
            'count' => $result['total_ratings'] ? (int)$result['total_ratings'] : 0
        ];
    }
    
    public static function getUserRating($pdo, $challengeId, $studentId) {
        $stmt = $pdo->prepare("SELECT rating, comment FROM challenge_ratings WHERE challenge_id = ? AND student_id = ?");
        $stmt->execute([$challengeId, $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function addRating($pdo, $challengeId, $studentId, $rating, $comment = '') {
        // Check if already rated
        $stmt = $pdo->prepare("SELECT id FROM challenge_ratings WHERE challenge_id = ? AND student_id = ?");
        $stmt->execute([$challengeId, $studentId]);
        
        if ($stmt->fetch()) {
            // Update existing rating
            $stmt = $pdo->prepare("UPDATE challenge_ratings SET rating = ?, comment = ? WHERE challenge_id = ? AND student_id = ?");
            return $stmt->execute([$rating, $comment, $challengeId, $studentId]);
        } else {
            // Insert new rating
            $stmt = $pdo->prepare("INSERT INTO challenge_ratings (challenge_id, student_id, rating, comment) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$challengeId, $studentId, $rating, $comment]);
        }
    }
    
    public static function getChallengesByLevelSorted($pdo, $level, $studentID, $sortBy = 'default') {
        $query = "
            SELECT c.*, 
                   CASE 
                       WHEN al.id IS NOT NULL THEN 1 
                       ELSE 0 
                   END as completed,
                   CASE
                       WHEN c.prerequisite_id IS NULL THEN 1
                       WHEN EXISTS (
                           SELECT 1 FROM activity_log 
                           WHERE user_id = ? AND activity_type = 'challenge_complete' 
                           AND target_id = c.prerequisite_id
                       ) THEN 1
                       ELSE 0
                   END as unlocked
            FROM challenges c 
            LEFT JOIN activity_log al ON c.id = al.target_id 
                AND al.user_id = ? 
                AND al.activity_type = 'challenge_complete'
            WHERE c.tree_level = ? AND c.status = 'Active'
        ";
        
        // Add sorting
        switch($sortBy) {
            case 'points_high':
                $query .= " ORDER BY c.points DESC, c.tree_order, c.id";
                break;
            case 'points_low':
                $query .= " ORDER BY c.points ASC, c.tree_order, c.id";
                break;
            case 'newest':
                $query .= " ORDER BY c.created_at DESC, c.tree_order, c.id";
                break;
            default:
                $query .= " ORDER BY c.tree_order, c.id";
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentID, $studentID, $level]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getAvailableCategories($pdo) {
        $stmt = $pdo->prepare("SELECT DISTINCT category FROM challenges WHERE category IS NOT NULL AND category != '' AND status = 'Active' ORDER BY category");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public static function getAvailableSkills($pdo) {
        $stmt = $pdo->prepare("SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(skill_tags, ',', numbers.n), ',', -1)) as skill
            FROM challenges
            JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) numbers
            ON CHAR_LENGTH(skill_tags) - CHAR_LENGTH(REPLACE(skill_tags, ',', '')) >= numbers.n - 1
            WHERE skill_tags IS NOT NULL AND skill_tags != '' AND status = 'Active'
            ORDER BY skill");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public static function getChallengesByCategory($pdo, $category, $studentID) {
        $query = "
            SELECT c.*, 
                   CASE 
                       WHEN al.id IS NOT NULL THEN 1 
                       ELSE 0 
                   END as completed,
                   CASE
                       WHEN c.prerequisite_id IS NULL THEN 1
                       WHEN EXISTS (
                           SELECT 1 FROM activity_log 
                           WHERE user_id = ? AND activity_type = 'challenge_complete' 
                           AND target_id = c.prerequisite_id
                       ) THEN 1
                       ELSE 0
                   END as unlocked
            FROM challenges c 
            LEFT JOIN activity_log al ON c.id = al.target_id 
                AND al.user_id = ? 
                AND al.activity_type = 'challenge_complete'
            WHERE c.category = ? AND c.status = 'Active'
            ORDER BY c.points DESC, c.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentID, $studentID, $category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
 }
?>