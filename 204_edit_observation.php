<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Register this file in web_files for menu/admin usage
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Edit observation and handle internal not ready flags');

$pdo = getDatabaseConnection();

$id = $_GET['id'] ?? 0;
if (!$id) die("Missing observation ID");

// Fetch observation + optional private comment
$stmt = $pdo->prepare("
    SELECT o.*, p.not_ready_comment, p.id AS private_id
    FROM review_observation o
    LEFT JOIN review_observation_private p ON o.id = p.observation_id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$obs = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$obs) die("Observation not found");

$game_id = $obs['game_id'];
$evaluation_id = $obs['evaluation_id'];

// Fetch teams
$stmt = $pdo->prepare("
    SELECT g.*, t1.name AS home_name, t2.name AS away_name
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE g.id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
$home_name = $game['home_name'] ?? 'Home';
$away_name = $game['away_name'] ?? 'Away';

// Crew positions
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
$selected_positions = explode(',', $obs['position']);

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position_string = implode(',', $_POST['positions'] ?? []);
    $stmt = $pdo->prepare("
        UPDATE review_observation
        SET time = ?, play_type = ?, grading = ?, foul = ?, foul_team = ?, foul_number = ?, 
            position = ?, comment = ?, education_clip = ?, education_comment = ?, update_date = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['time'] ?? '',
        $_POST['play_type'] ?? '',
        $_POST['grading'] ?? '',
        $_POST['foul'] ?? '',
        $_POST['foul_team'] ?? '',
        $_POST['foul_number'] ?? '',
        $position_string,
        $_POST['comment'] ?? '',
        isset($_POST['education_clip']) ? 1 : 0,
        $_POST['education_comment'] ?? '',
        $id
    ]);

    // Handle Review Not Ready
    $notReadyChecked = isset($_POST['not_ready']);
    $notReadyComment = $_POST['not_ready_comment'] ?? '';

    if ($notReadyChecked && !$obs['private_id']) {
        $stmt = $pdo->prepare("INSERT INTO review_observation_private (observation_id, user_id, not_ready_comment, create_date, update_date) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$id, $_SESSION['user_id'], $notReadyComment]);
    } elseif ($notReadyChecked && $obs['private_id']) {
        $stmt = $pdo->prepare("UPDATE review_observation_private SET not_ready_comment = ?, update_date = NOW() WHERE id = ?");
        $stmt->execute([$notReadyComment, $obs['private_id']]);
    } elseif (!$notReadyChecked && $obs['private_id']) {
        $stmt = $pdo->prepare("DELETE FROM review_observation_private WHERE id = ?");
        $stmt->execute([$obs['private_id']]);
    }

    header("Location: 202_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id&updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Edit Observation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
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
    <h4>Edit Observation</h4>
    <form method="post" autocomplete="off">
        <!-- Positions -->
        <table class="table table-sm table-bordered text-center mb-3">
            <thead class="thead-light">
                <tr><?php foreach ($positions as $pos): ?><th><?= $pos ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <tr>
                <?php foreach ($positions as $pos): ?>
                    <td class="checkbox-cell">
                        <input type="checkbox" name="positions[]" value="<?= $pos ?>"
                        <?= in_array($pos, $selected_positions) ? 'checked' : '' ?>>
                    </td>
                <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <div class="form-row">
            <div class="form-group col-md-3">
                <label>Time</label>
                <input type="text" name="time" class="form-control" value="<?= htmlspecialchars($obs['time']) ?>">
            </div>
            <div class="form-group col-md-3">
                <label>Play Type</label>
                <select name="play_type" class="form-control" onchange="updateGrading(this.value)">
                    <?php
                    $types = ['Mechanics', 'Foul', 'Judgement', 'Rules'];
                    foreach ($types as $t)
                        echo "<option value=\"$t\"" . ($obs['play_type'] == $t ? ' selected' : '') . ">$t</option>";
                    ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label>Grading</label>
                <select name="grading" id="grading" class="form-control"></select>
            </div>
            <div class="form-group col-md-3" id="foulGroup">
                <label>Foul / Rule</label>
                <input type="text" name="foul" id="foul" class="form-control"
                       value="<?= htmlspecialchars($obs['foul']) ?>">
                <div id="foulResults" class="autocomplete-results"></div>
            </div>
        </div>

        <div class="form-row" id="foulExtras">
            <div class="form-group col-md-6">
                <label>Foul Team</label>
                <select name="foul_team" class="form-control">
                    <option value="">–</option>
                    <option value="<?= $game['home_team_id'] ?>" <?= $obs['foul_team'] == $game['home_team_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($home_name) ?>
                    </option>
                    <option value="<?= $game['away_team_id'] ?>" <?= $obs['foul_team'] == $game['away_team_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($away_name) ?>
                    </option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Foul Number</label>
                <input type="text" name="foul_number" class="form-control"
                       value="<?= htmlspecialchars($obs['foul_number']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Comment</label>
            <input type="text" name="comment" class="form-control" value="<?= htmlspecialchars($obs['comment']) ?>">
        </div>

        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" name="education_clip" id="education_clip"
                   <?= $obs['education_clip'] ? 'checked' : '' ?> onclick="toggleEduComment()">
            <label class="form-check-label" for="education_clip">Education Clip</label>
        </div>

        <div class="form-group" id="eduCommentGroup" style="display:none;">
            <label>Education Comment</label>
            <textarea name="education_comment" class="form-control"
                      rows="2"><?= htmlspecialchars($obs['education_comment']) ?></textarea>
        </div>

        <div class="form-group form-check mt-4">
            <input type="checkbox" class="form-check-input" name="not_ready" id="not_ready"
                   <?= $obs['private_id'] ? 'checked' : '' ?> onclick="toggleNotReadyComment()">
            <label class="form-check-label" for="not_ready">Review not ready (internal note)</label>
        </div>

        <div class="form-group" id="notReadyCommentGroup" style="display:none;">
            <label>Internal Comment</label>
            <textarea name="not_ready_comment" class="form-control"
                      rows="2"><?= htmlspecialchars($obs['not_ready_comment'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">💾 Save</button>
        <a href="202_add_observation.php?game_id=<?= $game_id ?>&evaluation_id=<?= $evaluation_id ?>" class="btn btn-secondary">Back</a>
    </form>
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
        const opt = document.createElement("option");
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

function toggleNotReadyComment() {
    document.getElementById('notReadyCommentGroup').style.display =
        document.getElementById('not_ready').checked ? 'block' : 'none';
}

updateGrading("<?= $obs['play_type'] ?>");
if (<?= (int)$obs['education_clip'] ?>) toggleEduComment();
if (<?= $obs['private_id'] ? 'true' : 'false' ?>) toggleNotReadyComment();

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
