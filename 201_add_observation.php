<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Add file info to database for menu/system tracking
require_once 'php/file_register.php';

$pdo = getDatabaseConnection();

$game_id = $_GET['game_id'] ?? 0;
$evaluation_id = $_GET['evaluation_id'] ?? 0;
if (!$game_id || !$evaluation_id) die("Missing game or evaluation ID");

// Match- och crewdata
$stmt = $pdo->prepare("SELECT g.*, t1.name AS home_name, t1.logo AS home_logo, t2.name AS away_name, t2.logo AS away_logo FROM review_game g LEFT JOIN review_team t1 ON g.home_team_id = t1.id LEFT JOIN review_team t2 ON g.away_team_id = t2.id WHERE g.id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

// Reviewer
$stmt = $pdo->prepare("SELECT u.name FROM review_evaluation e JOIN review_user u ON e.user_id = u.id WHERE e.id = ?");
$stmt->execute([$evaluation_id]);
$reviewer_name = $stmt->fetchColumn();

// Crew
$crew = $pdo->prepare("SELECT * FROM review_crew WHERE game_id = ?"); $crew->execute([$game_id]);
$crew = $crew->fetch(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id, name FROM review_user")->fetchAll(PDO::FETCH_KEY_PAIR);

$positionMap = [
    'referee_id' => 'R', 'umpire_id' => 'U', 'hl_id' => 'L', 'lj_id' => 'D',
    'fj_id' => 'F', 'sj_id' => 'S', 'bj_id' => 'B', 'cj_id' => 'C'
];
$positionList = [];
foreach ($positionMap as $field => $code) {
    if (!empty($crew[$field])) {
        $name = $users[$crew[$field]] ?? 'Unknown';
        $positionList[] = ['code' => $code, 'name' => $name];
    }
}
$positionList[] = ['code' => 'Crew', 'name' => 'Entire Crew'];

// Spara observation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO review_observation (
            evaluation_id, game_id, time, play_type, position, foul, grading, comment, create_date, update_date
        ) VALUES (
            :evaluation_id, :game_id, :time, :play_type, :position, :foul, :grading, :comment, NOW(), NOW()
        )
    ");
    $stmt->execute([
        ':evaluation_id' => $evaluation_id,
        ':game_id' => $game_id,
        ':time' => $_POST['time'] ?? '',
        ':play_type' => $_POST['play_type'] ?? '',
        ':position' => $_POST['position'] ?? '',
        ':foul' => $_POST['foul'] ?? '',
        ':grading' => $_POST['grading'] ?? '',
        ':comment' => $_POST['comment'] ?? ''
    ]);
    header("Location: 201_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Observation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .logo-col img { max-height: 60px; }
        .form-group label { font-weight: 500; }
        .autocomplete-results {
            position: absolute; background: white; border: 1px solid #ccc; z-index: 1000; width: 100%;
        }
    </style>
</head>
<body>
<div class="container text-center">
    <div class="row justify-content-center mb-4">
        <div class="col-auto text-center logo-col">
            <?php if ($game['home_logo']) echo "<img src='logo/" . htmlspecialchars($game['home_logo']) . "'><br>"; ?>
            <strong><?= htmlspecialchars($game['home_name']) ?></strong>
        </div>
        <div class="col-auto text-center">
            <strong>Start:</strong> <?= htmlspecialchars($game['start_time']) ?><br>
            <strong>Stream:</strong> <?= htmlspecialchars($game['link']) ?>
        </div>
        <div class="col-auto text-center logo-col">
            <?php if ($game['away_logo']) echo "<img src='logo/" . htmlspecialchars($game['away_logo']) . "'><br>"; ?>
            <strong><?= htmlspecialchars($game['away_name']) ?></strong>
        </div>
    </div>

    <form method="post" id="obsForm">
        <input type="hidden" name="time" id="time_field">

        <div class="form-group">
            <label>Position</label>
            <select name="position" class="form-control">
                <?php foreach ($positionList as $pos): ?>
                    <option value="<?= $pos['code'] ?>"><?= $pos['code'] ?> – <?= $pos['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Play Type</label>
            <select name="play_type" id="play_type" class="form-control" onchange="toggleFoulAndGrading()">
                <option value="Mechanics">Mechanics</option>
                <option value="Foul">Foul</option>
                <option value="Judgement">Judgement</option>
                <option value="Rules">Rules</option>
            </select>
        </div>

        <div class="form-group" id="foul_group">
            <label>Foul (if any)</label>
            <input type="text" name="foul" id="foul_input" class="form-control" placeholder="Start typing DPI...">
            <div id="foul_results" class="autocomplete-results" style="display: none;"></div>
        </div>

        <div class="form-group">
            <label>Grading</label>
            <select name="grading" id="grading" class="form-control"></select>
        </div>

        <div class="form-group">
            <label>Comment</label>
            <input type="text" name="comment" class="form-control" placeholder="Description">
        </div>

        <button type="submit" class="btn btn-success btn-block">Save</button>
        <?php if ($reviewer_name): ?>
            <small class="text-muted">Reviewer: <?= htmlspecialchars($reviewer_name) ?></small>
        <?php endif; ?>
    </form>
</div>

<script>
function setCurrentTime() {
    const now = new Date();
    const hh = now.getHours().toString().padStart(2, '0');
    const mm = now.getMinutes().toString().padStart(2, '0');
    const ss = now.getSeconds().toString().padStart(2, '0');
    document.getElementById('time_field').value = `${hh}:${mm}:${ss}`;
}
setCurrentTime();

document.getElementById("obsForm").addEventListener("submit", function () {
    setCurrentTime();
});

const gradingField = document.getElementById('grading');
const playTypeField = document.getElementById('play_type');
const foulGroup = document.getElementById('foul_group');
const foulInput = document.getElementById("foul_input");
const foulResults = document.getElementById("foul_results");

const gradingOptions = {
    "Mechanics": { "IM": "Incorrect Mechanics", "NR": "Negative Remark", "GM": "Good Mechanics", "PR": "Positive Remark" },
    "Foul": { "CC": "Correct Call", "NC": "No Call", "MC": "Marginal Call", "GN": "Good No-call", "IC": "Incorrect Call", "NG": "Not Graded", "PR": "Positive Remark", "NR": "Negative Remark" },
    "Judgement": { "IJ": "Incorrect Judgement", "CJ": "Correct Judgement", "PR": "Positive Remark", "NR": "Negative Remark" },
    "Rules": { "IR": "Incorrect Rules Application", "PR": "Positive Remark", "NR": "Negative Remark" }
};

function toggleFoulAndGrading() {
    const type = playTypeField.value;
    gradingField.innerHTML = "";
    const options = gradingOptions[type] || {};
    for (const [code, label] of Object.entries(options)) {
        const opt = document.createElement("option");
        opt.value = code;
        opt.textContent = `${code} – ${label}`;
        gradingField.appendChild(opt);
    }
    foulGroup.style.display = (type === "Foul") ? "block" : "none";
}
toggleFoulAndGrading();

foulInput.addEventListener("input", () => {
    const val = foulInput.value.trim();
    if (val.length < 2) return foulResults.style.display = "none";
    fetch("php/foul_search.php?term=" + encodeURIComponent(val))
        .then(res => res.json())
        .then(data => {
            foulResults.innerHTML = "";
            data.forEach(item => {
                const div = document.createElement("div");
                div.textContent = `${item.shortcode} – ${item.name_sv}`;
                div.onclick = () => {
                    foulInput.value = item.shortcode;
                    foulResults.style.display = "none";
                };
                foulResults.appendChild(div);
            });
            foulResults.style.display = "block";
        });
});

document.addEventListener("click", e => {
    if (!foulResults.contains(e.target) && e.target !== foulInput) {
        foulResults.style.display = "none";
    }
});
</script>
</body>
</html>
