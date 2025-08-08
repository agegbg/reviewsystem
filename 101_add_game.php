<?php
// 101_add_game.php
// Parses pasted match info, checks teams and officials, displays editable form.

require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Get or create team ID
function getTeamId($pdo, $teamName) {
    $stmt = $pdo->prepare("SELECT id FROM review_team WHERE name = ?");
    $stmt->execute([$teamName]);
    $team = $stmt->fetch();
    if ($team) return $team['id'];

    // Insert new team
    $insert = $pdo->prepare("INSERT INTO review_team (name, create_date, update_date) VALUES (?, NOW(), NOW())");
    $insert->execute([$teamName]);
    return $pdo->lastInsertId();
}

// Check if user exists, return ID or null
function getUserIdByName($pdo, $name) {
    $stmt = $pdo->prepare("SELECT id FROM review_user WHERE name = ?");
    $stmt->execute([$name]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}

// Parse text
function parseMatchData($pdo, $text) {
    $lines = explode("\n", trim($text));
    $result = [
        'home_team' => '',
        'away_team' => '',
        'level' => '',
        'league' => '',
        'date' => '',
        'place' => '',
        'start_time' => '',
        'end_time' => '',
        'officials' => [],
    ];

    foreach ($lines as $line) {
        $line = trim($line);

        // Format 1: "Team A vs Team B" or "Team A v Team B"
        if (preg_match('/(.+?)\s+v[\.s]*\s+(.+)/i', $line, $m)) {
            $result['home_team'] = trim($m[1]);
            $result['away_team'] = trim($m[2]);
        }

        // Format 2: Date + Teams
        elseif (preg_match('/^(\d{4}-\d{2}-\d{2}) (.+?) - (.+?)( Resultat:.*)?$/', $line, $m)) {
            $result['date'] = $m[1];
            $result['home_team'] = trim($m[2]);
            $result['away_team'] = trim($m[3]);
        }

        elseif (stripos($line, 'Matchdatum:') !== false) {
            preg_match('/Matchdatum:\s*([0-9\-]+)/', $line, $m);
            if (isset($m[1])) $result['date'] = $m[1];
        }

        elseif (stripos($line, 'Kickoff:') !== false || stripos($line, 'Starttid') !== false) {
            preg_match('/(\d{1,2}:\d{2})/', $line, $m);
            if (isset($m[1])) $result['start_time'] = $m[1];
        }

        elseif (stripos($line, 'Sluttid') !== false) {
            preg_match('/(\d{1,2}:\d{2})/', $line, $m);
            if (isset($m[1])) $result['end_time'] = $m[1];
        }

        elseif (stripos($line, 'Plats:') !== false) {
            $result['place'] = trim(str_replace('Plats:', '', $line));
        }

        elseif (preg_match('/^U[0-9]+$/i', $line)) {
            $result['level'] = $line;
        }

        elseif (preg_match('/^(Huvuddomare|Umpire|Huvudlinjedomare|Linjedomare|Bakdomare|Fältdomare|Siddomare|Centerdomare)/', $line)) {
            [$role, $name] = preg_split("/\t| {2,}/", $line, 2);
            $name = trim($name);
            $id = getUserIdByName($pdo, $name);
            $result['officials'][trim($role)] = $id ? $name : '';
        }
    }

    // Team IDs
    $result['home_team_id'] = $result['home_team'] ? getTeamId($pdo, $result['home_team']) : null;
    $result['away_team_id'] = $result['away_team'] ? getTeamId($pdo, $result['away_team']) : null;

    return $result;
}

// Handle input
$data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_text'])) {
    $rawText = $_POST['match_text'];
    $sport = $_POST['sport'] ?? 'American Football';
    $data = parseMatchData($pdo, $rawText);
    $data['sport'] = $sport;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Game</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body class="p-4">

<div class="container">
    <h2 class="mb-4">Add Game</h2>

    <form method="post">
        <div class="form-group">
            <label for="sport">Sport</label>
            <select class="form-control" name="sport" id="sport" required>
                <option value="American Football">American Football</option>
                <option value="Flag Football">Flag Football</option>
            </select>
        </div>

        <div class="form-group">
            <label for="match_text">Paste match data:</label>
            <textarea class="form-control" name="match_text" id="match_text" rows="10" required><?= htmlspecialchars($_POST['match_text'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Parse</button>
    </form>

    <?php if ($data): ?>
        <hr>
        <h4 class="mt-4">Review Parsed Data</h4>
        <form method="post" action="102_confirm_game.php">
            <input type="hidden" name="sport" value="<?= htmlspecialchars($data['sport']) ?>">
            <input type="hidden" name="home_team_id" value="<?= $data['home_team_id'] ?>">
            <input type="hidden" name="away_team_id" value="<?= $data['away_team_id'] ?>">

            <div class="form-group">
                <label>Home Team</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($data['home_team']) ?>" disabled>
            </div>
            <div class="form-group">
                <label>Away Team</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($data['away_team']) ?>" disabled>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="text" class="form-control" name="date" value="<?= $data['date'] ?>">
            </div>
            <div class="form-group">
                <label>Place</label>
                <input type="text" class="form-control" name="place" value="<?= $data['place'] ?>">
            </div>
            <div class="form-group">
                <label>Level</label>
                <input type="text" class="form-control" name="level" value="<?= $data['level'] ?>">
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <input type="text" class="form-control" name="start_time" value="<?= $data['start_time'] ?>">
            </div>
            <div class="form-group">
                <label>End Time</label>
                <input type="text" class="form-control" name="end_time" value="<?= $data['end_time'] ?>">
            </div>

            <h5 class="mt-4">Officials</h5>
            <?php
            $roles = [
                'Huvuddomare' => 'referee',
                'Umpire' => 'umpire',
                'Huvudlinjedomare' => 'hl',
                'Linjedomare' => 'lj',
                'Bakdomare' => 'bj',
                'Fältdomare' => 'fj',
                'Siddomare' => 'sj',
                'Centerdomare' => 'cj',
            ];
            foreach ($roles as $sv => $key): ?>
                <div class="form-group">
                    <label><?= $sv ?></label>
                    <input type="text" class="form-control" name="<?= $key ?>" value="<?= $data['officials'][$sv] ?? '' ?>">
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-success mt-3">Save Game</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
