<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Models/Points.php';

class PointsController {
    public static function handle($pdo) {
        $action = $_GET['action'] ?? '';
        $studentID = $_SESSION['userID'] ?? 1;

        switch ($action) {
            case 'balance':
                self::showBalance($pdo, $studentID);
                break;
            case 'history':
                self::showHistory($pdo, $studentID);
                break;
            case 'award':
                self::awardPoints($pdo, $studentID);
                break;
            default:
                echo "Invalid action.";
        }
    }

    private static function showBalance($pdo, $studentID) {
        $balance = Points::getBalance($pdo, $studentID);
        echo $balance;
    }

    private static function showHistory($pdo, $studentID) {
        $history = Points::getHistory($pdo, $studentID);
        if (!$history) {
            echo "No point history available.";
            return;
        }
        foreach ($history as $h) {
            echo $h['points'] . " points on " . $h['created_at'] . "<br>";
        }
    }

    private static function awardPoints($pdo, $studentID) {
        $points = (int)($_GET['points'] ?? 0);
        if ($points === 0) {
            echo "Invalid points.";
            return;
        }
        $ok = Points::addPoints($pdo, $studentID, $points);
        if ($ok) {
            echo "Awarded $points points successfully.";
        } else {
            echo "Failed to award points.";
        }
    }
}

PointsController::handle($pdo);
?>
