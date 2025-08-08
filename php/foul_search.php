<?php
// php/foul_search.php
require_once 'db.php';
$pdo = getDatabaseConnection();

$term = $_GET['term'] ?? '';
$term = '%' . $term . '%';

$stmt = $pdo->prepare("
    SELECT shortcode, name_sv 
    FROM review_rule_key 
    WHERE shortcode LIKE ? OR name_sv LIKE ?
    ORDER BY shortcode ASC 
    LIMIT 20
");
$stmt->execute([$term, $term]);

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
