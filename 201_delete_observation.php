<?php
// 201_delete_observation.php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$obs_id = $_GET['id'] ?? 0;
$game_id = $_GET['game_id'] ?? 0;
$evaluation_id = $_GET['evaluation_id'] ?? 0;

if (!$obs_id || !$game_id || !$evaluation_id) {
    die("Missing parameters.");
}

// Delete the observation
$stmt = $pdo->prepare("DELETE FROM review_observation WHERE id = ?");
$stmt->execute([$obs_id]);

// Redirect back
header("Location: 201_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id");
exit;
?>
