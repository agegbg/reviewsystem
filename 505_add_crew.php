<?php
// File: 505_add_crew.php
// Description: Add or edit crew for a game with autocomplete and presets. Handles loading by game_id,
//              shows correct positions for the chosen sport/crew size, and saves to review_crew.
// Calls: php/session.php, php/db.php, php/file_register.php
// Called from: menu or links like 505_add_crew.php?game_id=1

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Add or edit crew with autocomplete and presets');

$pdo = getDatabaseConnection();

$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
$selected_game = null;
$existing_crew  = null;
$message = '';

// -------------------------------
// 1) Role <-> DB column mapping
// -------------------------------
// UI roles may say "DJ" but DB column is "hl_id". We normalize here.
$roleToColumn = [
    'R'  => 'referee',
    'U'  => 'umpire',
    'HL' => 'hl',
    'DJ' => 'hl',    // UI "Down Judge" maps to DB "hl"
    'LJ' => 'lj',
    'SJ' => 'sj',
    'FJ' => 'fj',
    'BJ' => 'bj',
    'C'  => 'cj',    // Center Judge
];

// Human labels for fields
$roleLabel = [
    'R'  => 'Referee',
    'U'  => 'Umpire',
    'DJ' => 'Down Judge',
    'HL' => 'Head Line Judge', // kept in case a preset uses HL
    'LJ' => 'Line Judge',
    'SJ' => 'Side Judge',
    'FJ' => 'Field Judge',
    'BJ' => 'Back Judge',
    'C'  => 'Center Judge',
];

// -------------------------------
// 2) Presets per sport
// -------------------------------
$crew_presets = [
    'American Football' => [
        '5'  => ['R', 'U', 'DJ', 'LJ', 'BJ'],
        '6C' => ['R', 'C', 'U', 'DJ', 'LJ', 'BJ'],
        '6D' => ['R', 'U', 'DJ', 'LJ', 'SJ', 'FJ'],
        '7'  => ['R', 'U', 'DJ', 'LJ', 'SJ', 'FJ', 'BJ'],
        '8'  => ['R', 'C', 'U', 'DJ', 'LJ', 'SJ', 'FJ', 'BJ'],
    ],
    'Flag Football' => [
        '3_IFAF' => ['R', 'DJ', 'FJ'],
        '3_USA'  => ['R', 'SJ', 'FJ'],
        '4'      => ['R', 'DJ', 'SJ', 'FJ'],
        '5L'     => ['R', 'DJ', 'LJ', 'SJ', 'FJ'],
        '5D'     => ['R', 'DJ', 'SJ', 'FJ', 'BJ'],
    ]
];

// -------------------------------
// 3) Save handler (POST)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = (int)($_POST['game_id'] ?? 0);
    $sport   = trim($_POST['sport'] ?? 'American Football');
    $crewKey = trim($_POST['crew_size'] ?? '');

    // Positions array: positions[DJ] => "Name or ID", etc.
    $positionsInput = $_POST['positions'] ?? [];

    // Resolve each entered value to a user_id (int or exact name match)
    $resolved = [];
    foreach ($positionsInput as $role => $value) {
        $value = trim($value);
        if ($value === '') { $resolved[$role] = null; continue; }

        if (ctype_digit($value)) {
            $resolved[$role] = (int)$value;
        } else {
            // Try exact name match in review_user
            $st = $pdo->prepare("SELECT id FROM review_user WHERE name = ? LIMIT 1");
            $st->execute([$value]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $resolved[$role] = $row ? (int)$row['id'] : null;
        }
    }

    // Build INSERT/UPDATE payload with proper columns
    $cols = [
        'game_id'  => $game_id,
        'crew_size'=> $crewKey,
    ];
    foreach ($resolved as $role => $userId) {
        if (!isset($roleToColumn[$role])) continue;
        $colBase = $roleToColumn[$role];          // e.g. 'hl'
        $cols[$colBase . '_id'] = $userId;        // e.g. 'hl_id' => 123
    }

    // Check if a row exists
    $st = $pdo->prepare("SELECT id FROM review_crew WHERE game_id = ? LIMIT 1");
    $st->execute([$game_id]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update
        $set = [];
        $params = [];
        foreach ($cols as $c => $v) {
            $set[]   = "$c = ?";
            $params[] = $v;
        }
        $params[] = (int)$existing['id'];
        $sql = "UPDATE review_crew SET " . implode(', ', $set) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);
        $message = "✅ Crew updated.";
    } else {
        // Insert
        $fields = implode(', ', array_keys($cols));
        $qs     = rtrim(str_repeat('?,', count($cols)), ',');
        $sql    = "INSERT INTO review_crew ($fields) VALUES ($qs)";
        $pdo->prepare($sql)->execute(array_values($cols));
        $message = "✅ Crew saved.";
    }
}

// -------------------------------
// 4) Load game + crew
// -------------------------------

// List games if no game_id
$all_games = [];
if ($game_id === 0) {
    $stmt = $pdo->query("
        SELECT g.id, g.date, g.start_time, t1.name AS home_name, t2.name AS away_name
        FROM review_game g
        LEFT JOIN review_team t1 ON g.home_team_id = t1.id
        LEFT JOIN review_team t2 ON g.away_team_id = t2.id
        ORDER BY g.date DESC, g.start_time ASC
    ");
    $all_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($game_id > 0) {
    // Game row
    $stmt = $pdo->prepare("
        SELECT g.*, t1.name AS home_name, t2.name AS away_name
        FROM review_game g
        LEFT JOIN review_team t1 ON g.home_team_id = t1.id
        LEFT JOIN review_team t2 ON g.away_team_id = t2.id
        WHERE g.id = ?
        LIMIT 1
    ");
    $stmt->execute([$game_id]);
    $selected_game = $stmt->fetch(PDO::FETCH_ASSOC);

    // Crew row with aliases matching UI roles
    // We alias to <col>_name but make sure DJ -> hl_name, C -> cj_name, etc.
    $crewSql = "
        SELECT rc.*,
               ur.name AS referee_name,
               uu.name AS umpire_name,
               uhl.name AS hl_name,
               ulj.name AS lj_name,
               usj.name AS sj_name,
               ufj.name AS fj_name,
               ubj.name AS bj_name,
               ucj.name AS cj_name
        FROM review_crew rc
        LEFT JOIN review_user ur  ON rc.referee_id = ur.id
        LEFT JOIN review_user uu  ON rc.umpire_id  = uu.id
        LEFT JOIN review_user uhl ON rc.hl_id      = uhl.id
        LEFT JOIN review_user ulj ON rc.lj_id      = ulj.id
        LEFT JOIN review_user usj ON rc.sj_id      = usj.id
        LEFT JOIN review_user ufj ON rc.fj_id      = ufj.id
        LEFT JOIN review_user ubj ON rc.bj_id      = ubj.id
        LEFT JOIN review_user ucj ON rc.cj_id      = ucj.id
        WHERE rc.game_id = ?
        LIMIT 1
    ";
    $crewStmt = $pdo->prepare($crewSql);
    $crewStmt->execute([$game_id]);
    $existing_crew = $crewStmt->fetch(PDO::FETCH_ASSOC);
}

// Helper: default sport
$currentSport = $selected_game['sport'] ?? 'American Football';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add/Edit Crew</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        label { font-weight: bold; }
        .role-tag { font-weight: 600; opacity: .7; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Add/Edit Crew</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($game_id === 0): ?>
        <div class="form-group">
            <label>Select a Game:</label>
            <select class="form-control" onchange="location = '?game_id=' + this.value">
                <option value="">-- Choose --</option>
                <?php foreach ($all_games as $g): ?>
                    <option value="<?= (int)$g['id'] ?>">
                        <?= htmlspecialchars($g['date']) ?> <?= $g['start_time'] ? ' @ '.htmlspecialchars($g['start_time']).' ' : '' ?>- 
                        <?= htmlspecialchars($g['home_name']) ?> vs <?= htmlspecialchars($g['away_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <strong>Game:</strong>
            <?= htmlspecialchars($selected_game['home_name'] ?? '') ?> vs <?= htmlspecialchars($selected_game['away_name'] ?? '') ?>
            | <?= htmlspecialchars($selected_game['date'] ?? '') ?>
            <?= !empty($selected_game['start_time']) ? '@ '.htmlspecialchars($selected_game['start_time']) : '' ?>
        </div>

        <form method="post" action="505_add_crew.php?game_id=<?= (int)$game_id ?>">
            <input type="hidden" name="game_id" value="<?= (int)$game_id ?>">

            <!-- Sport (values match DB exactly) -->
            <div class="form-group">
                <label for="sport">Sport</label>
                <select name="sport" id="sport" class="form-control" required>
                    <option value="American Football" <?= $currentSport === 'American Football' ? 'selected' : '' ?>>American Football</option>
                    <option value="Flag Football" <?= $currentSport === 'Flag Football' ? 'selected' : '' ?>>Flag Football</option>
                </select>
            </div>

            <div class="form-group">
                <label for="crew_size">Crew Size</label>
                <select name="crew_size" id="crew_size" class="form-control" required></select>
                <small class="form-text text-muted">Format examples: 5, 6C, 6D, 7, 8 …</small>
            </div>

            <div id="crew_fields"></div>

            <button type="submit" class="btn btn-success mt-3">Save Crew</button>
        </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
// Presets keyed by DB sport names
const presets = <?= json_encode($crew_presets, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
// Map UI role => DB base column (so we can pull the correct *_name from PHP alias)
const roleToColumn = <?= json_encode($roleToColumn) ?>;
const roleLabel    = <?= json_encode($roleLabel) ?>;
const existingCrew = <?= json_encode($existing_crew) ?>;
const currentSport = <?= json_encode($currentSport) ?>;

function updateCrewSizeOptions() {
    const sport = $('#sport').val();
    const select = $('#crew_size');
    select.empty();

    const keys = Object.keys(presets[sport] || {});
    keys.forEach(k => {
        const text = k + ': ' + presets[sport][k].join(', ');
        select.append(`<option value="${k}">${text}</option>`);
    });

    // If we have an existing crew_size, try to preselect it; otherwise pick first
    if (existingCrew && existingCrew.crew_size && keys.includes(existingCrew.crew_size)) {
        select.val(existingCrew.crew_size);
    } else if (keys.length) {
        select.val(keys[0]);
    }

    updateCrewFields();
}

function fieldValueForRole(role) {
    if (!existingCrew) return '';
    const colBase = roleToColumn[role];    // e.g. DJ -> 'hl'
    if (!colBase) return '';
    const aliasName = colBase + '_name';   // e.g. 'hl_name'
    return existingCrew[aliasName] || '';
}

function updateCrewFields() {
    const sport = $('#sport').val();
    const size  = $('#crew_size').val();
    const positions = (presets[sport] && presets[sport][size]) ? presets[sport][size] : [];
    const container = $('#crew_fields');
    container.empty();

    // Build inputs
    positions.forEach(pos => {
        const label = roleLabel[pos] || pos;
        const value = fieldValueForRole(pos);
        container.append(`
            <div class="form-group">
                <label>${label} <span class="role-tag">(${pos})</span></label>
                <input type="text" name="positions[${pos}]" class="form-control referee-input"
                       value="${value.replace(/"/g,'&quot;')}">
            </div>
        `);
    });

    // Name autocomplete -> php/search_user.php (expects ?term=)
    $('.referee-input').autocomplete({
        source: function (request, response) {
            $.getJSON('php/search_user.php', { term: request.term }, response);
        },
        minLength: 2
    });
}

$(function(){
    if ($('#sport').length) {
        // Initialize
        $('#sport').val(currentSport);
        updateCrewSizeOptions();

        // Handlers
        $('#sport').on('change', updateCrewSizeOptions);
        $('#crew_size').on('change', updateCrewFields);
    }
});
</script>
</body>
</html>
