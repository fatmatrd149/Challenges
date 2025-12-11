-- 🚨 DROP AND RECREATE (Cleans everything)
DROP DATABASE IF EXISTS edumind;
CREATE DATABASE edumind CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE edumind;

-- 👥 USERS TABLE (Combines students and teachers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
    points INT DEFAULT 100, -- All users start with 100 points
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email),
    INDEX idx_points (points)
);

-- 🎯 CHALLENGES TABLE (UPDATED WITH CATEGORY AND TAGS)
CREATE TABLE challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('course', 'time', 'social', 'weekly', 'monthly', 'timed') NOT NULL DEFAULT 'course',
    points INT DEFAULT 0,
    criteria TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    createdBy INT,
    prerequisite_id INT DEFAULT NULL,
    tree_level INT DEFAULT 0,
    tree_order INT DEFAULT 0,
    
    -- Scheduling fields
    schedule_type ENUM('none', 'time_limited', 'recurring') DEFAULT 'none',
    start_date DATETIME,
    end_date DATETIME,
    recurrence_pattern VARCHAR(50),
    time_limit_minutes INT DEFAULT NULL,
    
    -- New fields for categories and tags (store as comma-separated values to minimize tables)
    category VARCHAR(100) DEFAULT 'General',
    skill_tags VARCHAR(255) DEFAULT '',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (prerequisite_id) REFERENCES challenges(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_createdBy (createdBy),
    INDEX idx_tree_level (tree_level),
    INDEX idx_category (category),
    INDEX idx_points (points)
);

-- 🏆 REWARDS TABLE - UPDATED WITH CATEGORY
CREATE TABLE rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('Badge', 'Bonus Points', 'Certificate', 'Perk', 'Discount', 'Special') DEFAULT 'Badge',
    type VARCHAR(50), -- More specific type within category
    pointsCost INT NOT NULL DEFAULT 25,
    availability INT NOT NULL DEFAULT 100,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    min_tier VARCHAR(50) DEFAULT NULL, -- Optional tier requirement
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_pointsCost (pointsCost)
);

-- 🏅 REWARD TIERS TABLE
CREATE TABLE reward_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    min_points INT NOT NULL,
    max_points INT,
    badge_name VARCHAR(100),
    description TEXT,
    benefits TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_min_points (min_points)
);

-- 📊 ACTIVITY TABLE
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('challenge_complete', 'points_award', 'redeem_reward', 'tier_achievement') NOT NULL,
    target_id INT, -- challenge_id, reward_id, or tier_id
    points_amount INT DEFAULT 0,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
);

-- 🔗 CHALLENGE_REWARDS TABLE (Connects challenges with rewards)
CREATE TABLE challenge_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    reward_id INT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_challenge_reward (challenge_id, reward_id)
);

-- 📈 CHALLENGE RATINGS TABLE (NEW - Minimal table for student ratings)
CREATE TABLE challenge_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    student_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_challenge_rating (student_id, challenge_id),
    INDEX idx_challenge_id (challenge_id),
    INDEX idx_rating (rating)
);

-- 🔥 SAMPLE DATA - UPDATED WITH MORE REWARDS AND FIXED ACHIEVEMENTS

-- Users (admins, teachers, students)
INSERT INTO users (name, email, password, role, points) VALUES 
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0),
('Demo Teacher', 'teacher@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 0),
('Test Student', 'student@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 100), -- CHANGED TO 100 POINTS FOR TESTING
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 75),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 220),
('Mike Johnson', 'mike@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 350);

-- Reward Tiers
INSERT INTO reward_tiers (name, min_points, max_points, badge_name, description, benefits, status) VALUES 
('Bronze', 25, 100, '🥉', 'Beginner achievement tier', 'Basic rewards access, Bronze badges', 'Active'),
('Silver', 101, 250, '🥈', 'Intermediate achievement tier', 'Enhanced rewards, Priority support, Silver badges', 'Active'),
('Gold', 251, 500, '🥇', 'Advanced achievement tier', 'Premium rewards, Early access, Gold badges', 'Active'),
('Platinum', 501, NULL, '💎', 'Elite achievement tier', 'All rewards unlocked, VIP status, Platinum badges', 'Active');

-- Rewards - ORGANIZED BY CATEGORY (16 total rewards)
INSERT INTO rewards (title, description, category, type, pointsCost, availability, status, min_tier) VALUES 
-- Badges (5 items)
('Gold Star Badge', 'Exclusive gold achievement badge', 'Badge', 'Gold Badge', 25, 100, 'Active', 'Bronze'),
('Study Group Leader', 'Lead a study session badge', 'Badge', 'Leadership Badge', 30, 100, 'Active', 'Bronze'),
('Perfect Attendance', 'Perfect attendance for a month', 'Badge', 'Attendance Badge', 40, 100, 'Active', 'Bronze'),
('Speed Learner', 'Complete 5 challenges in one week', 'Badge', 'Speed Badge', 50, 75, 'Active', 'Silver'),
('Master Scholar', 'Achieve top scores in all courses', 'Badge', 'Master Badge', 100, 50, 'Active', 'Gold'),

-- Bonus Points (3 items)
('10 Bonus Points', 'Instant 10 point boost', 'Bonus Points', 'Small Boost', 10, 500, 'Active', NULL),
('25 Bonus Points', 'Instant 25 point boost', 'Bonus Points', 'Medium Boost', 20, 300, 'Active', 'Bronze'),
('50 Bonus Points', 'Instant 50 point boost', 'Bonus Points', 'Large Boost', 40, 200, 'Active', 'Silver'),

-- Certificates (3 items)
('Excellence Certificate', 'Official printed certificate of excellence', 'Certificate', 'Excellence', 100, 10, 'Active', 'Silver'),
('Completion Certificate', 'Course completion certificate', 'Certificate', 'Completion', 75, 25, 'Active', 'Bronze'),
('Honor Roll Certificate', 'Honor roll recognition certificate', 'Certificate', 'Honor', 150, 5, 'Active', 'Gold'),

-- Perks (3 items)
('Homework Pass', 'Skip one homework assignment', 'Perk', 'Homework Skip', 50, 50, 'Active', 'Bronze'),
('Extra Credit', '+20 bonus points instantly', 'Perk', 'Extra Credit', 75, 25, 'Active', 'Silver'),
('Early Exam Access', 'Get 24hr early access to exams', 'Perk', 'Early Access', 200, 20, 'Active', 'Gold'),

-- Discounts (2 items)
('10% Bookstore Discount', '10% discount on all bookstore items', 'Discount', 'Store Discount', 80, 100, 'Active', 'Silver'),
('Free Print Credit', '$5 printing credit', 'Discount', 'Print Credit', 30, 200, 'Active', 'Bronze');

-- Challenges WITH CATEGORIES AND TAGS
INSERT INTO challenges (title, description, type, points, createdBy, criteria, status, schedule_type, time_limit_minutes, tree_level, tree_order, category, skill_tags) VALUES 
('Weekly Reading Challenge', 'Read for 30 minutes every day for a week', 'weekly', 50, 2, 'Complete 7 days of reading', 'Active', 'recurring', NULL, 0, 1, 'Language', 'Critical Thinking,Perseverance'),
('Group Study Session', 'Join a 1-hour study group', 'social', 30, 2, 'Attend full session and participate', 'Active', 'none', 60, 0, 2, 'Social', 'Collaboration,Communication'),
('Basic Math Practice', 'Complete basic math exercises', 'course', 40, 2, 'Score 80% or higher', 'Active', 'none', NULL, 0, 3, 'Mathematics', 'Problem Solving,Analytical Skills'),
('Monthly Project', 'Complete a monthly research project', 'monthly', 100, 2, 'Submit project report and presentation', 'Active', 'recurring', NULL, 1, 1, 'Science', 'Research,Critical Thinking,Creativity'),
('Course Completion', 'Finish the advanced course modules', 'course', 150, 2, 'Complete all 5 modules with 85%+', 'Active', 'none', NULL, 1, 2, 'Technology', 'Problem Solving,Perseverance,Time Management'),
('Time Management Challenge', 'Manage your study time effectively', 'time', 60, 2, 'Track and optimize 20 hours of study', 'Active', 'none', NULL, 1, 3, 'General', 'Time Management,Analytical Skills'),
('Time-Limited Math Challenge', 'Complete advanced math exercises in 50 minutes', 'timed', 75, 2, 'Finish before time runs out', 'Active', 'time_limited', 50, 2, 1, 'Mathematics', 'Problem Solving,Time Management'),
('Monthly Writing Challenge', 'Write 1000 words research essay', 'monthly', 80, 2, 'Submit before deadline with citations', 'Active', 'time_limited', 120, 2, 2, 'Language', 'Research,Communication,Creativity'),
('Research Paper', 'Write a full research paper with references', 'course', 200, 2, 'Submit 3000+ word paper with proper format', 'Active', 'none', NULL, 2, 3, 'Science', 'Research,Critical Thinking,Writing');

-- Connect some challenges with rewards
INSERT INTO challenge_rewards (challenge_id, reward_id, created_by) VALUES 
(1, 1, 2),
(4, 8, 2),
(7, 5, 2),
(8, 9, 2);

-- Add sample challenge ratings
INSERT INTO challenge_ratings (challenge_id, student_id, rating, comment) VALUES 
(1, 3, 5, 'Great for building reading habits!'),
(1, 4, 4, 'Enjoyed the daily reading challenge'),
(2, 3, 3, 'Good but would like more variety'),
(3, 5, 5, 'Perfect for math practice'),
(4, 6, 4, 'Challenging but rewarding'),
(7, 3, 5, 'Exciting time pressure!'),
(8, 4, 4, 'Improved my writing skills');

-- Create SINGLE achievement entries for tier progression (NO DUPLICATES)
INSERT INTO activity_log (user_id, activity_type, target_id, points_amount, details) VALUES 
(3, 'tier_achievement', 1, 0, 'Reached Bronze Tier at 100 points'), -- Student 3: ONLY Bronze
(4, 'tier_achievement', 1, 0, 'Reached Bronze Tier at 75 points'), -- John: Bronze
(5, 'tier_achievement', 2, 0, 'Reached Silver Tier at 220 points'), -- Jane: Silver
(6, 'tier_achievement', 3, 0, 'Reached Gold Tier at 350 points'); -- Mike: Gold

-- Create some reward redemptions for testing
INSERT INTO activity_log (user_id, activity_type, target_id, points_amount, details) VALUES 
(3, 'redeem_reward', 1, 25, 'Redeemed Gold Star Badge'),
(3, 'redeem_reward', 6, 10, 'Redeemed 10 Bonus Points'),
(5, 'redeem_reward', 2, 50, 'Redeemed Homework Pass'),
(5, 'redeem_reward', 11, 20, 'Redeemed 25 Bonus Points');

-- Update user points based on activities
UPDATE users u 
SET u.points = 100 + COALESCE((
    SELECT SUM(points_amount) 
    FROM activity_log al 
    WHERE al.user_id = u.id 
    AND al.activity_type IN ('challenge_complete', 'points_award')
), 0) - COALESCE((
    SELECT ABS(SUM(points_amount))
    FROM activity_log al 
    WHERE al.user_id = u.id 
    AND al.activity_type = 'redeem_reward'
), 0)
WHERE u.role = 'student';

-- Create indexes for better performance
CREATE INDEX idx_challenge_tree ON challenges(tree_level, tree_order);
CREATE INDEX idx_user_points ON users(points);
CREATE INDEX idx_reward_cost ON rewards(pointsCost);
CREATE INDEX idx_activity_user ON activity_log(user_id, created_at);

-- 🎁 REWARD BUNDLES TABLE
CREATE TABLE reward_bundles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    total_cost INT NOT NULL DEFAULT 100,
    discount_percentage INT DEFAULT 0,
    limited_quantity INT,
    start_date DATETIME,
    end_date DATETIME,
    status ENUM('active', 'upcoming', 'expired', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_start_date (start_date),
    INDEX idx_end_date (end_date)
);

CREATE TABLE bundle_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bundle_id INT NOT NULL,
    reward_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (bundle_id) REFERENCES reward_bundles(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bundle_reward (bundle_id, reward_id)
);

-- 📋 REWARD REQUESTS TABLE (Teacher Approval System)
CREATE TABLE reward_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reward_id INT NOT NULL,
    teacher_id INT, -- NULL until assigned
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    student_message TEXT,
    teacher_response TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (reward_id) REFERENCES rewards(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_student_id (student_id)
);

-- 📊 TEACHER ACTION LOG
CREATE TABLE teacher_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    action_type VARCHAR(50),
    student_id INT,
    reward_id INT,
    points INT DEFAULT 0,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (reward_id) REFERENCES rewards(id)
);

-- Add some sample bundles
INSERT INTO reward_bundles (name, description, total_cost, discount_percentage, limited_quantity, status) VALUES 
('Study Starter Pack', 'Perfect bundle for new students', 150, 25, 50, 'active'),
('Exam Prep Bundle', 'Everything you need for exam season', 300, 15, 30, 'active'),
('Achiever''s Package', 'Premium rewards for top performers', 500, 20, 20, 'active');

-- Add items to Study Starter Pack
INSERT INTO bundle_items (bundle_id, reward_id, quantity) VALUES 
(1, 1, 1), -- Gold Star Badge
(1, 6, 2), -- 10 Bonus Points (x2)
(1, 16, 1); -- Free Print Credit

-- Add items to Exam Prep Bundle
INSERT INTO bundle_items (bundle_id, reward_id, quantity) VALUES 
(2, 11, 1), -- Homework Pass
(2, 12, 1), -- Extra Credit
(2, 13, 1); -- Early Exam Access

-- Add items to Achiever's Package
INSERT INTO bundle_items (bundle_id, reward_id, quantity) VALUES 
(3, 5, 1),  -- Master Scholar Badge
(3, 10, 1), -- Honor Roll Certificate
(3, 13, 1); -- Early Exam Access