<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$game_id = $_GET['game_id'] ?? 0;
$evaluation_id = $_GET['evaluation_id'] ?? 0;
if (!$game_id || !$evaluation_id) die("Missing game or evaluation ID");

// Game info
$stmt = $pdo->prepare("
    SELECT g.*, t1.name AS home_name, t1.logo AS home_logo,
                 t2.name AS away_name, t2.logo AS away_logo
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE g.id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

// Crew
$stmt = $pdo->prepare("SELECT * FROM review_crew WHERE game_id = ?");
$stmt->execute([$game_id]);
$crew = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

// Map valid positions
$positionMap = [
    'referee_id' => 'R', 'umpire_id' => 'Ump', 'hl_id' => 'HL', 'lj_id' => 'LJ',
    'sj_id' => 'SJ', 'fj_id' => 'FJ', 'bj_id' => 'BJ', 'cj_id' => 'C'
];
$positions = [];
foreach ($positionMap as $field => $label) {
    if (!empty($crew[$field])) $positions[] = $label;
}
$positions[] = 'Crew';

// Save observation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['positions'] ?? [];
    $position_string = implode(',', $selected);

    $stmt = $pdo->prepare("
        INSERT INTO review_observation (evaluation_id, game_id, time, play_type, position, foul, grading, comment, create_date, update_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $evaluation_id, $game_id,
        $_POST['time'] ?? '',
        $_POST['play_type'] ?? '',
        $position_string,
        $_POST['foul'] ?? '',
        $_POST['grading'] ?? '',
        $_POST['comment'] ?? ''
    ]);
    header("Location: 203_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id");
    exit;
}

// Observations
$stmt = $pdo->prepare("SELECT * FROM review_observation WHERE evaluation_id = ? ORDER BY time ASC");
$stmt->execute([$evaluation_id]);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Observation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo { height: 50px; }
        .checkbox-cell { text-align: center; }
        .form-control-sm { font-size: 0.9em; }
    </style>
</head>
<body class="p-4">

<br>
<br>




<div class="container">
    <!-- Game Info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-center">
            <?php if ($game['home_logo']): ?>
                <img src="logo/<?= htmlspecialchars($game['home_logo']) ?>" class="logo"><br>
            <?php endif; ?>
            <strong><?= htmlspecialchars($game['home_name']) ?></strong>
        </div>
        <div class="text-center">
            <strong><?= htmlspecialchars($game['date']) ?></strong><br>
            <strong><?= htmlspecialchars($game['time']) ?></strong><br>
            <span><?= htmlspecialchars($game['league']) ?></span><br>
            <span><?= htmlspecialchars($game['field']) ?></span>
        </div>
        <div class="text-center">
            <?php if ($game['away_logo']): ?>
                <img src="logo/<?= htmlspecialchars($game['away_logo']) ?>" class="logo"><br>
            <?php endif; ?>
            <strong><?= htmlspecialchars($game['away_name']) ?></strong>
        </div>
    </div>


    <!-- Position Table -->
    <table class="table table-sm table-bordered text-center mb-3">
            <thead class="thead-light">
                <tr>
                    <?php foreach ($positions as $pos): ?>
                        <th><?= $pos ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php foreach ($positions as $pos): ?>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="positions[]" value="<?= $pos ?>">
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>


    <!-- Observation Form -->
    <form method="post">
        <input type="hidden" name="game_id" value="<?= $game_id ?>">
        <input type="hidden" name="evaluation_id" value="<?= $evaluation_id ?>">

        <!-- Top Fields -->
        <div class="form-row">
            <div class="form-group col-md-3">
                <label>Time (HH:MM:SS)</label>
                <input type="text" name="time" class="form-control form-control-sm" placeholder="HH:MM:SS">
            </div>
            <div class="form-group col-md-3">
                <label>Play Type</label>
                <select name="play_type" class="form-control form-control-sm" onchange="updateGrading(this.value)">
                    <option value="Mechanics">Mechanics</option>
                    <option value="Foul">Foul</option>
                    <option value="Judgement">Judgement</option>
                    <option value="Rules">Rules</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>Grading</label>
                <select name="grading" id="grading" class="form-control form-control-sm"></select>
            </div>
            <div class="form-group col-md-3" id="foulGroup" style="display:none;">
                <label>Foul / Rule</label>
                <input type="text" name="foul" class="form-control form-control-sm" placeholder="e.g. DPI, FST">
            </div>
        </div>

        

        <!-- Comment -->
        <div class="form-group">
            <label>Comment</label>
            <input type="text" name="comment" class="form-control" placeholder="What happened?">
        </div>

        <button type="submit" class="btn btn-success">💾 Save Observation</button>
    </form>

    <!-- Observation List -->
    <hr>
    
    <table class="table table-sm table-bordered">
    <thead class="thead-light">
    <thead class="thead-light">
    <tr>
        <th>Time</th>
        <th>Position</th>
        <th>Type</th>
        <th>Foul</th>
        <th>Grading</th>
        <th>Comment</th>
        <th>Action</th>
    </tr>
</thead>
</thead>
        <tbody>
        <?php foreach ($observations as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['time']) ?></td>
                <td><?= htmlspecialchars($o['position']) ?></td>
                <td><?= htmlspecialchars($o['play_type']) ?></td>
                <td><?= htmlspecialchars($o['foul']) ?></td>
                <td><?= htmlspecialchars($o['grading']) ?></td>
                <td><?= htmlspecialchars($o['comment']) ?></td>
                <<td>
    <div class="btn-group btn-group-sm" role="group">
        <a href="204_edit_observation.php?id=<?= $o['id'] ?>" class="btn btn-primary">Edit</a>
        <a href="php/delete_observation.php?id=<?= $o['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this?')">Delete</a>
    </div>
</td>


            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
const gradingOptions = {
    "Mechanics": { "IM": "Incorrect Mechanics", "NR": "Negative Remark", "GM": "Good Mechanics", "PR": "Positive Remark", "OBS": " " },
    "Foul": { "CC": "Correct Call", "NC": "No Call", "MC": "Marginal Call", "GN": "Good No-call", "IC": "Incorrect Call", "NG": "Not Graded", "PR": "Positive Remark", "NR": "Negative Remark", "OBS": " " },
    "Judgement": { "IJ": "Incorrect Judgement", "CJ": "Correct Judgement", "PR": "Positive Remark", "NR": "Negative Remark" },
    "Rules": { "IR": "Incorrect Rules Application", "PR": "Positive Remark", "NR": "Negative Remark" }
};
function updateGrading(type) {
    const select = document.getElementById('grading');
    select.innerHTML = '';
    const options = gradingOptions[type] || {};
    for (let code in options) {
        let opt = document.createElement("option");
        opt.value = code;
        opt.text = code + " – " + options[code];
        select.appendChild(opt);
    }
    document.getElementById('foulGroup').style.display = (type === 'Foul') ? 'block' : 'none';
}
updateGrading('Mechanics');
</script>
</body>
</html>
