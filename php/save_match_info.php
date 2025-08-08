<?php
require_once 'db.php';
$pdo = getDatabaseConnection();

$game_id = $_POST['game_id'] ?? 0;
if (!$game_id) {
    die("Game ID missing");
}

// Alla möjliga fält
$positions = [
    'referee_id', 'umpire_id', 'dj_id', 'lj_id',
    'fj_id', 'sj_id', 'bj_id', 'cj_id'
];

$data = [];
foreach ($positions as $pos) {
    $data[$pos] = isset($_POST[$pos]) && $_POST[$pos] !== '' ? intval($_POST[$pos]) : null;
}

// Kontrollera om crew-rad redan finns
$stmt = $pdo->prepare("SELECT id FROM review_crew WHERE game_id = ?");
$stmt->execute([$game_id]);
$crew_id = $stmt->fetchColumn();

if ($crew_id) {
    // Uppdatera
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
    $sql = "UPDATE review_crew SET $set, update_date = NOW() WHERE game_id = :game_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($data, ['game_id' => $game_id]));
} else {
    // Skapa ny
    $fields = implode(', ', array_merge(['game_id'], array_keys($data)));
    $placeholders = implode(', ', array_map(fn($k) => ":$k", array_merge(['game_id'], array_keys($data))));
    $sql = "INSERT INTO review_crew ($fields, create_date, update_date) VALUES ($placeholders, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(['game_id' => $game_id], $data));
}

// Lägg till utvärderare om det finns angiven
$evaluator_id = isset($_POST['evaluator_id']) && $_POST['evaluator_id'] !== '' ? intval($_POST['evaluator_id']) : 0;

if ($evaluator_id) {
    // Kontrollera om det redan finns en evaluation för denna utvärderare + match
    $stmt = $pdo->prepare("SELECT id FROM review_evaluation WHERE game_id = ? AND user_id = ?");
    $stmt->execute([$game_id, $evaluator_id]);
    if (!$stmt->fetchColumn()) {
        // Skapa ny
        $stmt = $pdo->prepare("
            INSERT INTO review_evaluation (game_id, user_id, is_head_evaluator, evaluation_type, create_date, update_date)
            VALUES (?, ?, 0, 'official', NOW(), NOW())
        ");
        $stmt->execute([$game_id, $evaluator_id]);
    }
}

// Skicka tillbaka till tidigare sida
header("Location: ../504_add_info.php?game_id=$game_id");
exit;
