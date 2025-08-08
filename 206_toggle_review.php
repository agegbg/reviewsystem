<?php
// 206_toggle_review.php
// Toggle is_closed flag for review_game

require_once 'php/session.php';
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Toggle review lock for a game');

$pdo = getDatabaseConnection();
$game_id = $_GET['game_id'] ?? 0;

if (!$game_id) die("Missing game ID");

// Endast admin (just nu ID 1)
if ($_SESSION['user_id'] != 1) {
    die("Access denied");
}

// Hämta nuvarande status
$stmt = $pdo->prepare("SELECT is_finished FROM review_game WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) die("Game not found");

$newValue = $game['is_finished'] ? 0 : 1;

$stmt = $pdo->prepare("UPDATE review_game SET is_finished = ?, update_date = NOW() WHERE id = ?");
$stmt->execute([$newValue, $game_id]);

header("Location: 202_add_observation.php?game_id=$game_id&evaluation_id=1");
exit;
