<?php
// 102_confirm_game.php
// Saves game and crew into review_game and review_crew using team_id and official names

require_once 'php/db.php';
$pdo = getDatabaseConnection();

function getUserIdByName($pdo, $name) {
    $stmt = $pdo->prepare("SELECT id FROM review_user WHERE name = ?");
    $stmt->execute([$name]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}

// Get POST data
$sport = $_POST['sport'] ?? 'American Football';
$home_team_id = $_POST['home_team_id'] ?? null;
$away_team_id = $_POST['away_team_id'] ?? null;
$date = $_POST['date'] ?? null;
$place = $_POST['place'] ?? null;
$level = $_POST['level'] ?? null;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;

// Insert into review_game
$insertGame = $pdo->prepare("
    INSERT INTO review_game (
        date, time, start_time, end_time,
        home_team_id, away_team_id, level, game_type,
        place, sport, create_date, update_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$insertGame->execute([
    $date,
    $start_time,
    $start_time,
    $end_time,
    $home_team_id,
    $away_team_id,
    $level,
    null,
    $place,
    $sport
]);

$game_id = $pdo->lastInsertId();

// Prepare officials
$positions = [
    'referee', 'umpire', 'hl', 'lj', 'fj', 'sj', 'bj', 'cj'
];

$crew = [];
foreach ($positions as $key) {
    $name = trim($_POST[$key] ?? '');
    $crew[$key . '_id'] = $name !== '' ? getUserIdByName($pdo, $name) : null;
}

// Insert into review_crew
$insertCrew = $pdo->prepare("
    INSERT INTO review_crew (
        game_id, crew_size, referee_id, umpire_id,
        hl_id, lj_id, fj_id, sj_id, bj_id, cj_id,
        create_date, update_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$insertCrew->execute([
    $game_id,
    count(array_filter($crew)), // crew_size = number of filled positions
    $crew['referee_id'] ?? null,
    $crew['umpire_id'] ?? null,
    $crew['hl_id'] ?? null,
    $crew['lj_id'] ?? null,
    $crew['fj_id'] ?? null,
    $crew['sj_id'] ?? null,
    $crew['bj_id'] ?? null,
    $crew['cj_id'] ?? null,
]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Game Saved</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>Game Saved Successfully</h2>
    <p><strong>Game ID:</strong> <?= $game_id ?></p>
    <a href="101_add_game.php" class="btn btn-secondary">Add Another Game</a>
</div>
</body>
</html>
