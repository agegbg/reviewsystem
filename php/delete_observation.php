<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'session.php';

// Load database connection (PDO with utf8mb4)
require_once 'db.php';

$pdo = getDatabaseConnection();

$id = $_GET['id'] ?? 0;
if (!$id) die("Missing ID");

// Hämta för redirect
$stmt = $pdo->prepare("SELECT game_id, evaluation_id FROM review_observation WHERE id = ?");
$stmt->execute([$id]);
$obs = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$obs) die("Not found");

$stmt = $pdo->prepare("DELETE FROM review_observation WHERE id = ?");
$stmt->execute([$id]);

header("Location: ../202_add_observation.php?game_id={$obs['game_id']}&evaluation_id={$obs['evaluation_id']}&deleted=1");
exit;
