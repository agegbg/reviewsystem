<?php
require_once 'php/session.php';
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Add or edit crew with autocomplete and presets');

$pdo = getDatabaseConnection();
$game_id = $_GET['game_id'] ?? null;
$selected_game = null;
$existing_crew = null;

// Get list of all games if game_id not provided
$all_games = [];
if (!$game_id) {
    $stmt = $pdo->query("
        SELECT g.id, g.date, t1.name AS home_name, t2.name AS away_name
        FROM review_game g
        LEFT JOIN review_team t1 ON g.home_team_id = t1.id
        LEFT JOIN review_team t2 ON g.away_team_id = t2.id
        ORDER BY g.date DESC
    ");
    $all_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If game_id is present, get game and crew data
if ($game_id) {
    $stmt = $pdo->prepare("
        SELECT g.*, t1.name AS home_name, t2.name AS away_name
        FROM review_game g
        LEFT JOIN review_team t1 ON g.home_team_id = t1.id
        LEFT JOIN review_team t2 ON g.away_team_id = t2.id
        WHERE g.id = ?
    ");
    $stmt->execute([$game_id]);
    $selected_game = $stmt->fetch();

    $crewStmt = $pdo->prepare("
        SELECT rc.*, 
            u1.name AS referee_name, u2.name AS umpire_name, u3.name AS hl_name, u4.name AS lj_name,
            u5.name AS fj_name, u6.name AS sj_name, u7.name AS bj_name, u8.name AS cj_name
        FROM review_crew rc
        LEFT JOIN review_user u1 ON rc.referee_id = u1.id
        LEFT JOIN review_user u2 ON rc.umpire_id = u2.id
        LEFT JOIN review_user u3 ON rc.hl_id = u3.id
        LEFT JOIN review_user u4 ON rc.lj_id = u4.id
        LEFT JOIN review_user u5 ON rc.fj_id = u5.id
        LEFT JOIN review_user u6 ON rc.sj_id = u6.id
        LEFT JOIN review_user u7 ON rc.bj_id = u7.id
        LEFT JOIN review_user u8 ON rc.cj_id = u8.id
        WHERE rc.game_id = ?
    ");
    $crewStmt->execute([$game_id]);
    $existing_crew = $crewStmt->fetch(PDO::FETCH_ASSOC);
}

// Presets
$crew_presets = [
    'football' => [
        '5' => ['R', 'U', 'DJ', 'LJ', 'BJ'],
        '6C' => ['R', 'C', 'U', 'DJ', 'LJ', 'BJ'],
        '6D' => ['R', 'U', 'DJ', 'LJ', 'SJ', 'FJ'],
        '7' => ['R', 'U', 'DJ', 'LJ', 'SJ', 'FJ', 'BJ'],
        '8' => ['R', 'C', 'U', 'DJ', 'LJ', 'SJ', 'FJ', 'BJ'],
    ],
    'flag' => [
        '3_IFAF' => ['R', 'DJ', 'FJ'],
        '3_USA' => ['R', 'SJ', 'FJ'],
        '4' => ['R', 'DJ', 'SJ', 'FJ'],
        '5L' => ['R', 'DJ', 'LJ', 'SJ', 'FJ'],
        '5D' => ['R', 'DJ', 'SJ', 'FJ', 'BJ'],
    ]
];

$roleMap = [
    'R' => 'Referee',
    'U' => 'Umpire',
    'DJ' => 'Down Judge',
    'LJ' => 'Line Judge',
    'SJ' => 'Side Judge',
    'FJ' => 'Field Judge',
    'BJ' => 'Back Judge',
    'C' => 'Center Judge'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add/Edit Crew</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>label { font-weight: bold; }</style>
</head>
<body class="p-4">
<div class="container">
    <h2>Add/Edit Crew</h2>

    <?php if (!$game_id): ?>
        <div class="form-group">
            <label>Select a Game:</label>
            <select class="form-control" onchange="location = '?game_id=' + this.value">
                <option value="">-- Choose --</option>
                <?php foreach ($all_games as $g): ?>
                    <option value="<?= $g['id'] ?>">
                        <?= $g['date'] ?> - <?= htmlspecialchars($g['home_name']) ?> vs <?= htmlspecialchars($g['away_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <strong>Game:</strong> <?= htmlspecialchars($selected_game['home_name']) ?> vs <?= htmlspecialchars($selected_game['away_name']) ?> |
            <?= $selected_game['date'] ?> @ <?= $selected_game['start_time'] ?>
        </div>

        <form method="post" action="505_add_crew.php?game_id=<?= $game_id ?>">
            <input type="hidden" name="game_id" value="<?= $game_id ?>">

            <div class="form-group">
                <label for="sport">Sport</label>
                <select name="sport" id="sport" class="form-control" required>
                    <option value="football" <?= $selected_game['sport'] === 'American Football' ? 'selected' : '' ?>>American Football</option>
                    <option value="flag" <?= $selected_game['sport'] === 'Flag Football' ? 'selected' : '' ?>>Flag Football</option>
                </select>
            </div>

            <div class="form-group">
                <label for="crew_size">Crew Size</label>
                <select name="crew_size" id="crew_size" class="form-control" required></select>
            </div>

            <div id="crew_fields"></div>

            <button type="submit" class="btn btn-success mt-3">Save Crew</button>
        </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    const presets = <?= json_encode($crew_presets) ?>;
    const existingCrew = <?= json_encode($existing_crew) ?>;
    const roleMap = <?= json_encode($roleMap) ?>;

    function updateCrewSizeOptions() {
        const sport = $('#sport').val();
        const select = $('#crew_size');
        select.empty();
        for (const key in presets[sport]) {
            select.append(`<option value="${key}">${key}: ${presets[sport][key].join(', ')}</option>`);
        }
        updateCrewFields();
    }

    function updateCrewFields() {
        const sport = $('#sport').val();
        const size = $('#crew_size').val();
        const positions = presets[sport][size];
        const container = $('#crew_fields');
        container.empty();

        positions.forEach(pos => {
            const label = roleMap[pos] || pos;
            const value = existingCrew ? (existingCrew[pos.toLowerCase() + '_name'] || '') : '';
            container.append(`
                <div class="form-group">
                    <label>${label} (${pos})</label>
                    <input type="text" name="positions[${pos}]" class="form-control referee-input" value="${value}">
                </div>
            `);
        });

        $('.referee-input').autocomplete({
            source: function (request, response) {
                $.getJSON('php/search_user.php', { term: request.term }, response);
            },
            minLength: 3
        });
    }

    $(document).ready(function () {
        if ($('#sport').length) {
            updateCrewSizeOptions();
            $('#sport').on('change', updateCrewSizeOptions);
            $('#crew_size').on('change', updateCrewFields);
        }
    });
</script>
</body>
</html>
