<?php
// php/search_user.php
// Returns a JSON list of referee names matching search term (min 3 chars)

require_once 'db.php';
$pdo = getDatabaseConnection();

$term = $_GET['term'] ?? '';
$term = trim($term);

if (strlen($term) < 3) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT name FROM review_user WHERE name LIKE ? ORDER BY name LIMIT 15");
$stmt->execute(["%" . $term . "%"]);
$names = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($names);
