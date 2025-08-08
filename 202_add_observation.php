<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Register this file in web_files for menu/admin usage
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Beskrivning av vad denna fil gör');



$pdo = getDatabaseConnection();

$game_id = $_GET['game_id'] ?? 0;
$evaluation_id = $_GET['evaluation_id'] ?? 0;
if (!$game_id || !$evaluation_id) die("Missing game or evaluation ID");

// Hämta laginfo
$stmt = $pdo->prepare("
    SELECT g.*, t1.name AS home_name, t2.name AS away_name, t1.logo AS home_logo, t2.logo AS away_logo
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE g.id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
$home_name = $game['home_name'] ?? 'Home';
$away_name = $game['away_name'] ?? 'Away';

// Hämta crew
$stmt = $pdo->prepare("SELECT * FROM review_crew WHERE game_id = ?");
$stmt->execute([$game_id]);
$crew = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

$positionMap = [
    'referee_id' => 'R', 'umpire_id' => 'Ump', 'hl_id' => 'HL', 'lj_id' => 'LJ',
    'sj_id' => 'SJ', 'fj_id' => 'FJ', 'bj_id' => 'BJ', 'cj_id' => 'C'
];
$positions = [];
foreach ($positionMap as $field => $label) {
    if (!empty($crew[$field])) $positions[] = $label;
}
$positions[] = 'Crew';

// Spara observation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['positions'] ?? [];
    $position_string = implode(',', $selected);

    $stmt = $pdo->prepare("
        INSERT INTO review_observation (
            evaluation_id, game_id, time, play_type, position, foul, grading,
            comment, foul_team, foul_number, education_clip, education_comment,
            create_date, update_date
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $evaluation_id, $game_id,
        $_POST['time'] ?? '',
        $_POST['play_type'] ?? '',
        $position_string,
        $_POST['foul'] ?? '',
        $_POST['grading'] ?? '',
        $_POST['comment'] ?? '',
        $_POST['foul_team'] ?? '',
        $_POST['foul_number'] ?? '',
        isset($_POST['education_clip']) ? 1 : 0,
        $_POST['education_comment'] ?? ''
    ]);
    header("Location: 202_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id");
    exit;
}

// Hämta tidigare observationer
$stmt = $pdo->prepare("SELECT * FROM review_observation WHERE evaluation_id = ? ORDER BY time ASC");
$stmt->execute([$evaluation_id]);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Add Observation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .logo { height: 50px; }
        .checkbox-cell { text-align: center; }
        .autocomplete-results {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            z-index: 1000;
            width: 100%;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <!-- Game Info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-center">
            <?php if ($game['home_logo']): ?>
                <img src="logo/<?= htmlspecialchars($game['home_logo']) ?>" class="logo"><br>
            <?php endif; ?>
            <strong><?= htmlspecialchars($home_name) ?></strong>
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
            <strong><?= htmlspecialchars($away_name) ?></strong>
        </div>
    </div>

    <form method="post" autocomplete="off">
        <input type="hidden" name="game_id" value="<?= $game_id ?>">
        <input type="hidden" name="evaluation_id" value="<?= $evaluation_id ?>">

        <!-- Positions -->
        <table class="table table-sm table-bordered text-center mb-3">
            <thead class="thead-light">
                <tr><?php foreach ($positions as $pos): ?><th><?= $pos ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <tr>
                <?php foreach ($positions as $pos): ?>
                    <td class="checkbox-cell"><input type="checkbox" name="positions[]" value="<?= $pos ?>"></td>
                <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <div class="form-row">
            <div class="form-group col-md-3">
                <label>Time (HH:MM:SS)</label>
                <input type="text" name="time" class="form-control" placeholder="HH:MM:SS">
            </div>
            <div class="form-group col-md-3">
                <label>Play Type</label>
                <select name="play_type" class="form-control" onchange="updateGrading(this.value)">
                    <option value="Mechanics">Mechanics</option>
                    <option value="Foul">Foul</option>
                    <option value="Judgement">Judgement</option>
                    <option value="Rules">Rules</option>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>Grading</label>
                <select name="grading" id="grading" class="form-control"></select>
            </div>
            <div class="form-group col-md-3" id="foulGroup" style="display:none;">
                <label>Foul / Rule</label>
                <input type="text" name="foul" id="foul" class="form-control" placeholder="e.g. DPI, FST">
                <div id="foulResults" class="autocomplete-results"></div>
            </div>
        </div>

        <div class="form-row" id="foulExtras" style="display:none;">
            <div class="form-group col-md-6">
                <label>Foul Team</label>
                <select name="foul_team" class="form-control">
                    <option value="">–</option>
                    <option value="<?= $game['home_team_id'] ?>"><?= htmlspecialchars($home_name) ?></option>
<option value="<?= $game['away_team_id'] ?>"><?= htmlspecialchars($away_name) ?></option>

                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Foul Number</label>
                <input type="text" name="foul_number" class="form-control" placeholder="e.g. 22">
            </div>
        </div>

        <div class="form-group">
            <label>Comment</label>
            <input type="text" name="comment" class="form-control" placeholder="What happened?">
        </div>

        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" name="education_clip" id="education_clip" onclick="toggleEduComment()">
            <label class="form-check-label" for="education_clip">Mark as Education Clip</label>
        </div>

        <div class="form-group" id="eduCommentGroup" style="display:none;">
            <label>Education Comment</label>
            <textarea name="education_comment" class="form-control" rows="2" placeholder="Explain why it's educational"></textarea>
        </div>

        <button type="submit" class="btn btn-success">💾 Save Observation</button>
    </form>

    <hr>
    <h5>Saved Observations</h5>
    <table class="table table-sm table-bordered">
        <thead class="thead-light">
            <tr><th>Time</th><th>Pos</th><th>Type</th><th>Foul</th><th>Grading</th><th>Comment</th><th>Action</th></tr>
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
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="204_edit_observation.php?id=<?= $o['id'] ?>" class="btn btn-primary">Edit</a>
                        <a href="php/delete_observation.php?id=<?= $o['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete?')">Delete</a>
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
    const grading = document.getElementById('grading');
    grading.innerHTML = '';
    const options = gradingOptions[type] || {};
    for (let code in options) {
        let opt = document.createElement("option");
        opt.value = code;
        opt.text = `${code} – ${options[code]}`;
        grading.appendChild(opt);
    }
    const foulVisible = type === 'Foul';
    document.getElementById('foulGroup').style.display = foulVisible ? 'block' : 'none';
    document.getElementById('foulExtras').style.display = foulVisible ? 'flex' : 'none';
}
function toggleEduComment() {
    document.getElementById('eduCommentGroup').style.display =
        document.getElementById('education_clip').checked ? 'block' : 'none';
}
updateGrading('Mechanics');

document.getElementById('foul').addEventListener('input', function () {
    const query = this.value;
    const resultsDiv = document.getElementById('foulResults');
    resultsDiv.innerHTML = '';
    if (query.length < 1) return;
    fetch('php/foul_search.php?term=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            data.forEach(item => {
                const div = document.createElement('div');
                div.textContent = `${item.shortcode} – ${item.name_sv}`;
                div.classList.add('px-2', 'py-1');
                div.style.cursor = 'pointer';
                div.onclick = () => {
                    document.getElementById('foul').value = item.shortcode;
                    resultsDiv.innerHTML = '';
                };
                resultsDiv.appendChild(div);
            });
        });
});
</script>
</body>
</html>
