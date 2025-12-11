<?php
class Rewards {
    public static function getAll($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM rewards ORDER BY FIELD(category, 'Bonus Points', 'Badge', 'Certificate', 'Perk', 'Discount'), pointsCost ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByCategory($pdo, $category) {
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE category = ? AND status = 'Active' ORDER BY pointsCost ASC");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByID($pdo, $id) {
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    public static function create($pdo, $title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier = null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO rewards (title, description, category, type, pointsCost, availability, status, min_tier) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier]);
        } catch (Exception $e) {
            error_log("Error creating reward: " . $e->getMessage());
            return false;
        }
    }

    public static function update($pdo, $id, $title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier = null) {
        try {
            $stmt = $pdo->prepare("UPDATE rewards SET title = ?, description = ?, category = ?, type = ?, pointsCost = ?, availability = ?, status = ?, min_tier = ? WHERE id = ?");
            return $stmt->execute([$title, $description, $category, $type, $pointsCost, $availability, $status, $min_tier, $id]);
        } catch (Exception $e) {
            error_log("Error updating reward: " . $e->getMessage());
            return false;
        }
    }

    public static function delete($pdo, $id) {
        try {
            // Remove from challenge_rewards first
            $stmt = $pdo->prepare("DELETE FROM challenge_rewards WHERE reward_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Error deleting reward: " . $e->getMessage());
            return false;
        }
    }

    // SIMPLE REDEEM METHOD - NO TRANSACTIONS, NO LOCKS
    public static function redeem($pdo, $rewardID, $studentID) {
        try {
            // DEBUG: Log what's being received
            error_log("DEBUG Rewards::redeem(): Student ID: $studentID, Reward ID: $rewardID");
            
            // 1. Get student current points
            $stmt = $pdo->prepare("SELECT points, name FROM users WHERE id = ?");
            $stmt->execute([$studentID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // DEBUG: Log student data
            if (!$student) {
                error_log("ERROR Rewards::redeem(): Student not found with ID: $studentID");
            } else {
                error_log("DEBUG Rewards::redeem(): Student found - Name: {$student['name']}, Points: {$student['points']}");
            }
            
            if (!$student) {
                return ['success' => false, 'message' => 'Student not found'];
            }
            
            $studentPoints = (int)$student['points'];
            error_log("DEBUG Rewards::redeem(): Student points: $studentPoints");
            
            // 2. Get reward
            $stmt = $pdo->prepare("SELECT title, pointsCost, availability, status, min_tier FROM rewards WHERE id = ?");
            $stmt->execute([$rewardID]);
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reward) {
                error_log("ERROR Rewards::redeem(): Reward not found with ID: $rewardID");
                return ['success' => false, 'message' => 'Reward not found'];
            }
            
            $rewardCost = (int)$reward['pointsCost'];
            error_log("DEBUG Rewards::redeem(): Reward - Title: {$reward['title']}, Cost: $rewardCost, Availability: {$reward['availability']}, Status: {$reward['status']}");
            
            // 3. Check basic conditions
            if ($reward['status'] != 'Active') {
                error_log("DEBUG Rewards::redeem(): Reward is not active");
                return ['success' => false, 'message' => 'Reward is not active'];
            }
            
            if ($reward['availability'] <= 0) {
                error_log("DEBUG Rewards::redeem(): Reward is no longer available (availability: {$reward['availability']})");
                return ['success' => false, 'message' => 'Reward is no longer available'];
            }
            
            if ($studentPoints < $rewardCost) {
                $needed = $rewardCost - $studentPoints;
                error_log("DEBUG Rewards::redeem(): Insufficient points. Student: $studentPoints, Needed: $rewardCost, Short by: $needed");
                return [
                    'success' => false, 
                    'message' => "Insufficient points. You need $needed more points. (You have $studentPoints, needed: $rewardCost)"
                ];
            }
            
            // 4. Check tier requirement
            if ($reward['min_tier']) {
                // Get student's current tier
                $stmt = $pdo->prepare("
                    SELECT rt.name, rt.min_points 
                    FROM reward_tiers rt 
                    WHERE rt.min_points <= ? 
                    AND (rt.max_points >= ? OR rt.max_points IS NULL) 
                    AND rt.status = 'Active' 
                    ORDER BY rt.min_points DESC LIMIT 1
                ");
                $stmt->execute([$studentPoints, $studentPoints]);
                $studentTier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$studentTier) {
                    error_log("DEBUG Rewards::redeem(): Student doesn't have a tier yet with points: $studentPoints");
                    return ['success' => false, 'message' => 'You don\'t have a tier yet'];
                }
                
                // Get required tier
                $stmt = $pdo->prepare("SELECT min_points FROM reward_tiers WHERE name = ? AND status = 'Active'");
                $stmt->execute([$reward['min_tier']]);
                $requiredTier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$requiredTier) {
                    error_log("ERROR Rewards::redeem(): Invalid tier requirement: {$reward['min_tier']}");
                    return ['success' => false, 'message' => 'Invalid tier requirement'];
                }
                
                // Check if student's tier is sufficient
                if ($studentTier['min_points'] < $requiredTier['min_points']) {
                    error_log("DEBUG Rewards::redeem(): Tier requirement not met. Student tier: {$studentTier['name']} ({$studentTier['min_points']} pts), Required: {$reward['min_tier']} ({$requiredTier['min_points']} pts)");
                    return [
                        'success' => false, 
                        'message' => "This reward requires " . $reward['min_tier'] . " tier or higher. Your current tier is " . $studentTier['name']
                    ];
                }
            }
            
            // 5. DEDUCT POINTS - SIMPLE UPDATE
            $newPoints = $studentPoints - $rewardCost;
            error_log("DEBUG Rewards::redeem(): Deducting points. Old: $studentPoints, New: $newPoints, Cost: $rewardCost");
            
            $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
            $updateResult = $stmt->execute([$newPoints, $studentID]);
            
            if (!$updateResult) {
                error_log("ERROR Rewards::redeem(): Failed to update points for student ID: $studentID");
                return ['success' => false, 'message' => 'Failed to update points'];
            }
            
            // 6. Update reward availability
            $newAvailability = $reward['availability'] - 1;
            $stmt = $pdo->prepare("UPDATE rewards SET availability = availability - 1 WHERE id = ?");
            $stmt->execute([$rewardID]);
            error_log("DEBUG Rewards::redeem(): Updated reward availability. Old: {$reward['availability']}, New: $newAvailability");
            
            // 7. Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, target_id, points_amount, details) VALUES (?, 'redeem_reward', ?, ?, ?)");
            $stmt->execute([$studentID, $rewardID, -$rewardCost, "Redeemed: {$reward['title']}"]);
            
            error_log("SUCCESS Rewards::redeem(): Successfully redeemed '{$reward['title']}' for $rewardCost points");
            
            return [
                'success' => true,
                'points' => $rewardCost,
                'title' => $reward['title']
            ];
            
        } catch (Exception $e) {
            error_log("CRITICAL Rewards::redeem() error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    // NEW: REDEEM BUNDLE METHOD
    public static function redeemBundle($pdo, $bundleID, $studentID) {
        try {
            // 1. Get bundle details
            $stmt = $pdo->prepare("SELECT * FROM reward_bundles WHERE id = ? AND status = 'active'");
            $stmt->execute([$bundleID]);
            $bundle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bundle) {
                return ['success' => false, 'message' => 'Bundle not found or not active'];
            }
            
            // Check if bundle is available (limited quantity)
            if ($bundle['limited_quantity'] !== null) {
                // Count how many times this bundle has been redeemed
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as redeemed_count 
                    FROM activity_log 
                    WHERE details LIKE ? AND activity_type = 'redeem_reward'
                ");
                $stmt->execute(["%Bundle: " . $bundle['name'] . "%"]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['redeemed_count'] >= $bundle['limited_quantity']) {
                    return ['success' => false, 'message' => 'This bundle is out of stock'];
                }
            }
            
            // 2. Get student points
            $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->execute([$studentID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                return ['success' => false, 'message' => 'Student not found'];
            }
            
            $studentPoints = (int)$student['points'];
            $bundleCost = (int)$bundle['total_cost'];
            
            // 3. Check if student can afford
            if ($studentPoints < $bundleCost) {
                $needed = $bundleCost - $studentPoints;
                return [
                    'success' => false, 
                    'message' => "Insufficient points for bundle. You need $needed more points. (You have $studentPoints, needed: $bundleCost)"
                ];
            }
            
            // 4. Get bundle items
            $stmt = $pdo->prepare("
                SELECT bi.*, r.title, r.pointsCost as individual_cost 
                FROM bundle_items bi
                JOIN rewards r ON bi.reward_id = r.id
                WHERE bi.bundle_id = ?
            ");
            $stmt->execute([$bundleID]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                return ['success' => false, 'message' => 'Bundle contains no items'];
            }
            
            // 5. Calculate savings
            $totalIndividualCost = 0;
            foreach ($items as $item) {
                $totalIndividualCost += ($item['individual_cost'] * $item['quantity']);
            }
            $savings = $totalIndividualCost - $bundleCost;
            
            // 6. Deduct points
            $newPoints = $studentPoints - $bundleCost;
            $stmt = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
            $stmt->execute([$newPoints, $studentID]);
            
            // 7. Redeem each item in the bundle
            $redeemedItems = [];
            foreach ($items as $item) {
                for ($i = 0; $i < $item['quantity']; $i++) {
                    // Redeem the reward
                    self::redeem($pdo, $item['reward_id'], $studentID);
                    $redeemedItems[] = $item['title'];
                }
            }
            
            // 8. Log bundle redemption
            $itemList = implode(', ', array_unique($redeemedItems));
            $stmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, activity_type, points_amount, details, created_at) 
                VALUES (?, 'redeem_reward', ?, ?, NOW())
            ");
            $details = "Bundle: " . $bundle['name'] . " - Includes: " . $itemList . " (Saved " . $savings . " points!)";
            $stmt->execute([$studentID, -$bundleCost, $details]);
            
            return [
                'success' => true,
                'message' => "Bundle '{$bundle['name']}' redeemed successfully! Saved $savings points on individual items.",
                'savings' => $savings,
                'items' => $redeemedItems
            ];
            
        } catch (Exception $e) {
            error_log("Bundle redemption error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error redeeming bundle: ' . $e->getMessage()
            ];
        }
    }
    
    // NEW: GET ALL BUNDLES
    public static function getAllBundles($pdo) {
        $stmt = $pdo->prepare("
            SELECT rb.*, 
                   COUNT(bi.id) as item_count,
                   (SELECT GROUP_CONCAT(r.title SEPARATOR ', ') 
                    FROM bundle_items bi2 
                    JOIN rewards r ON bi2.reward_id = r.id 
                    WHERE bi2.bundle_id = rb.id 
                    LIMIT 3) as sample_items
            FROM reward_bundles rb
            LEFT JOIN bundle_items bi ON rb.id = bi.bundle_id
            WHERE rb.status = 'active'
            GROUP BY rb.id
            ORDER BY rb.total_cost ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // NEW: GET BUNDLE BY ID
    public static function getBundleByID($pdo, $id) {
        $stmt = $pdo->prepare("
            SELECT rb.*, 
                   GROUP_CONCAT(CONCAT(bi.quantity, 'x ', r.title) SEPARATOR '; ') as items_detail,
                   SUM(r.pointsCost * bi.quantity) as total_individual_cost
            FROM reward_bundles rb
            LEFT JOIN bundle_items bi ON rb.id = bi.bundle_id
            LEFT JOIN rewards r ON bi.reward_id = r.id
            WHERE rb.id = ?
            GROUP BY rb.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // NEW: ANALYTICS METHODS
    public static function getRedemptionStats($pdo, $startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                r.category,
                COUNT(al.id) as redemption_count,
                SUM(ABS(al.points_amount)) as total_points_spent,
                AVG(ABS(al.points_amount)) as avg_points_per_redemption
            FROM activity_log al
            JOIN rewards r ON al.target_id = r.id
            WHERE al.activity_type = 'redeem_reward'
        ";
        
        $params = [];
        
        if ($startDate && $endDate) {
            $sql .= " AND al.created_at BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY r.category ORDER BY redemption_count DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getPopularRewards($pdo, $limit = 5) {
        $stmt = $pdo->prepare("
            SELECT r.title, r.category, r.pointsCost,
                   COUNT(al.id) as redemption_count,
                   SUM(ABS(al.points_amount)) as total_points_spent
            FROM activity_log al
            JOIN rewards r ON al.target_id = r.id
            WHERE al.activity_type = 'redeem_reward'
            GROUP BY r.id
            ORDER BY redemption_count DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getLowStockRewards($pdo, $threshold = 10) {
        $stmt = $pdo->prepare("
            SELECT * FROM rewards 
            WHERE availability <= ? 
            AND status = 'Active'
            ORDER BY availability ASC
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // NEW: TEACHER APPROVAL METHODS
    public static function getPendingRequests($pdo, $teacherID = null) {
        $sql = "
            SELECT rr.*, 
                   s.name as student_name, 
                   s.points as student_points,
                   r.title as reward_title,
                   r.pointsCost as reward_cost,
                   r.description as reward_description,
                   r.category as reward_category
            FROM reward_requests rr
            JOIN users s ON rr.student_id = s.id
            JOIN rewards r ON rr.reward_id = r.id
            WHERE rr.status = 'pending'
        ";
        
        $params = [];
        
        if ($teacherID) {
            $sql .= " AND (rr.teacher_id = ? OR rr.teacher_id IS NULL)";
            $params[] = $teacherID;
        }
        
        $sql .= " ORDER BY rr.requested_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getStudentRequests($pdo, $studentID) {
        $stmt = $pdo->prepare("
            SELECT rr.*, r.title as reward_title, r.pointsCost, 
                   u.name as teacher_name, rr.teacher_response
            FROM reward_requests rr
            JOIN rewards r ON rr.reward_id = r.id
            LEFT JOIN users u ON rr.teacher_id = u.id
            WHERE rr.student_id = ?
            ORDER BY rr.requested_at DESC
        ");
        $stmt->execute([$studentID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // NEW: REWARD RECOMMENDER (Simple version)
    public static function recommendRewards($pdo, $studentID) {
        try {
            // Get student info
            $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
            $stmt->execute([$studentID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $studentPoints = $student['points'] ?? 0;
            
            // Get student's past redemptions
            $stmt = $pdo->prepare("
                SELECT r.category, COUNT(*) as count
                FROM activity_log al
                JOIN rewards r ON al.target_id = r.id
                WHERE al.user_id = ? AND al.activity_type = 'redeem_reward'
                GROUP BY r.category
                ORDER BY count DESC
                LIMIT 3
            ");
            $stmt->execute([$studentID]);
            $preferredCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build recommendation query
            $recommendations = [];
            
            // Recommendation 1: Affordable rewards (within student's budget)
            $stmt = $pdo->prepare("
                SELECT * FROM rewards 
                WHERE pointsCost <= ? 
                AND status = 'Active' 
                AND availability > 0
                ORDER BY pointsCost ASC
                LIMIT 3
            ");
            $stmt->execute([$studentPoints]);
            $affordable = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $recommendations['affordable'] = $affordable;
            
            // Recommendation 2: From preferred categories
            if (!empty($preferredCategories)) {
                $categories = array_column($preferredCategories, 'category');
                $placeholders = implode(',', array_fill(0, count($categories), '?'));
                
                $stmt = $pdo->prepare("
                    SELECT * FROM rewards 
                    WHERE category IN ($placeholders) 
                    AND status = 'Active' 
                    AND availability > 0
                    ORDER BY pointsCost ASC
                    LIMIT 3
                ");
                $stmt->execute($categories);
                $preferred = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $recommendations['preferred'] = $preferred;
            }
            
            // Recommendation 3: Popular rewards (overall)
            $stmt = $pdo->prepare("
                SELECT r.*, COUNT(al.id) as popularity
                FROM rewards r
                LEFT JOIN activity_log al ON r.id = al.target_id AND al.activity_type = 'redeem_reward'
                WHERE r.status = 'Active' AND r.availability > 0
                GROUP BY r.id
                ORDER BY popularity DESC, r.pointsCost ASC
                LIMIT 3
            ");
            $stmt->execute();
            $popular = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $recommendations['popular'] = $popular;
            
            // Recommendation 4: Bundles
            $stmt = $pdo->prepare("
                SELECT * FROM reward_bundles 
                WHERE status = 'active' 
                AND total_cost <= ?
                ORDER BY total_cost ASC
                LIMIT 2
            ");
            $stmt->execute([$studentPoints * 1.5]); // Slightly above current points for motivation
            $bundles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $recommendations['bundles'] = $bundles;
            
            return $recommendations;
            
        } catch (Exception $e) {
            error_log("Reward recommendation error: " . $e->getMessage());
            return [];
        }
    }
    
    public static function getAllWithRedemptions($pdo) {
        $stmt = $pdo->prepare("
            SELECT r.*, COUNT(al.id) as redemption_count 
            FROM rewards r 
            LEFT JOIN activity_log al ON r.id = al.target_id AND al.activity_type = 'redeem_reward' 
            GROUP BY r.id 
            ORDER BY FIELD(r.category, 'Bonus Points', 'Badge', 'Certificate', 'Perk', 'Discount'), r.pointsCost ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRewardsByTier($pdo, $minPoints, $maxPoints = null) {
        if ($maxPoints) {
            $stmt = $pdo->prepare("
                SELECT * FROM rewards 
                WHERE pointsCost BETWEEN ? AND ? 
                AND status = 'Active' 
                ORDER BY pointsCost ASC
            ");
            $stmt->execute([$minPoints, $maxPoints]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM rewards 
                WHERE pointsCost >= ? 
                AND status = 'Active' 
                ORDER BY pointsCost ASC
            ");
            $stmt->execute([$minPoints]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class RewardTiers {
    public static function getAllTiers($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM reward_tiers WHERE status = 'Active' ORDER BY min_points ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTierByPoints($pdo, $points) {
        $stmt = $pdo->prepare("SELECT * FROM reward_tiers WHERE min_points <= ? AND (max_points >= ? OR max_points IS NULL) AND status = 'Active' ORDER BY min_points DESC LIMIT 1");
        $stmt->execute([$points, $points]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getTierDistribution($pdo) {
        $stmt = $pdo->prepare("
            SELECT rt.name as tier_name, rt.min_points, rt.max_points, 
                   COUNT(u.id) as student_count
            FROM reward_tiers rt 
            LEFT JOIN users u ON u.points BETWEEN rt.min_points AND COALESCE(rt.max_points, 999999) 
                AND u.role = 'student'
            WHERE rt.status = 'Active'
            GROUP BY rt.id, rt.name, rt.min_points, rt.max_points
            ORDER BY rt.min_points ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRecentAchievements($pdo, $limit = 10) {
        $stmt = $pdo->prepare("
            SELECT al.*, rt.name as tier_name, rt.badge_name, rt.min_points,
                   u.name as student_name, u.points as student_points
            FROM activity_log al 
            JOIN reward_tiers rt ON al.target_id = rt.id 
            JOIN users u ON al.user_id = u.id 
            WHERE al.activity_type = 'tier_achievement'
            ORDER BY al.created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getStudentAchievements($pdo, $student_id) {
        $stmt = $pdo->prepare("
            SELECT rt.*, al.created_at as achieved_at 
            FROM activity_log al 
            JOIN reward_tiers rt ON al.target_id = rt.id 
            WHERE al.user_id = ? AND al.activity_type = 'tier_achievement'
            ORDER BY rt.min_points DESC
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getStudentCurrentTier($pdo, $student_id) {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) return null;
        
        return self::getTierByPoints($pdo, $student['points']);
    }

    public static function getTierProgress($pdo, $student_id) {
        $current_tier = self::getStudentCurrentTier($pdo, $student_id);
        
        if (!$current_tier) {
            return ['current_tier' => null, 'next_tier' => null, 'progress' => 0];
        }
        
        $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $current_points = $student['points'];
        
        $stmt = $pdo->prepare("SELECT * FROM reward_tiers WHERE min_points > ? AND status = 'Active' ORDER BY min_points ASC LIMIT 1");
        $stmt->execute([$current_points]);
        $next_tier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($next_tier) {
            $progress = (($current_points - $current_tier['min_points']) / ($next_tier['min_points'] - $current_tier['min_points'])) * 100;
            $progress = min(100, max(0, round($progress, 1)));
        } else {
            $progress = 100;
        }
        
        return [
            'current_tier' => $current_tier,
            'next_tier' => $next_tier,
            'progress' => $progress,
            'points_to_next' => $next_tier ? ($next_tier['min_points'] - $current_points) : 0
        ];
    }
}
?>