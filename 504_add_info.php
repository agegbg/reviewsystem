<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$game_id = $_GET['game_id'] ?? 0;
if (!$game_id) die("Missing game ID");

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = intval($_POST['game_id']);
    $evaluator_id = intval($_POST['evaluator_id'] ?? 0);

    // Spara utvärderare (om inte redan finns)
    if ($evaluator_id > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM review_evaluation WHERE game_id = ? AND user_id = ?");
        $stmt->execute([$game_id, $evaluator_id]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO review_evaluation (game_id, user_id, is_head_evaluator, evaluation_type, create_date, update_date)
                VALUES (?, ?, 0, 'official', NOW(), NOW())
            ");
            $stmt->execute([$game_id, $evaluator_id]);
        }
    }

  // Spara domare i review_crew
$positions = ['referee_id', 'umpire_id', 'dj_id', 'lj_id', 'bj_id'];
$values = [];
foreach ($positions as $p) {
    $values[$p] = intval($_POST[$p] ?? 0);
}

// Kolla om posten finns
$stmt = $pdo->prepare("SELECT id FROM review_crew WHERE game_id = ?");
$stmt->execute([$game_id]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $sql = "UPDATE review_crew SET 
        referee_id = ?, umpire_id = ?, dj_id = ?, lj_id = ?, bj_id = ?, 
        update_date = NOW() 
        WHERE game_id = ?";
    $pdo->prepare($sql)->execute([
        $values['referee_id'], $values['umpire_id'], $values['dj_id'],
        $values['lj_id'], $values['bj_id'], $game_id
    ]);
} else {
    $sql = "INSERT INTO review_crew (
        game_id, crew_size, referee_id, umpire_id, dj_id, lj_id, bj_id,
        hl_id, fj_id, sj_id, cj_id, observer_id, evaluator_id,
        create_date, update_date
    ) VALUES (?, '5', ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())";
    $pdo->prepare($sql)->execute([
        $game_id,
        $values['referee_id'], $values['umpire_id'], $values['dj_id'],
        $values['lj_id'], $values['bj_id']
    ]);
}


    header("Location: 504_add_info.php?game_id=$game_id&saved=1");
    exit;
}

// Load game data
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

// Users
$users = $pdo->query("SELECT id, name FROM review_user ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$userList = json_encode($users);

// Load crew
$stmt = $pdo->prepare("SELECT * FROM review_crew WHERE game_id = ?");
$stmt->execute([$game_id]);
$crew = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Redigera Matchinfo</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { padding: 1rem; }
        .logo { height: 50px; }
        .autocomplete-box { position: relative; }
        .autocomplete-results {
            position: absolute; background: white; border: 1px solid #ccc;
            z-index: 1000; width: 100%; max-height: 200px; overflow-y: auto;
        }
        .autocomplete-results div {
            padding: 5px; cursor: pointer;
        }
        .autocomplete-results div:hover { background: #eee; }
    </style>
</head>
<body>
<div class="container">
    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Sparat!</div>
    <?php endif; ?>

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

    <form method="post">
        <input type="hidden" name="game_id" value="<?= $game_id ?>">

        <h5>Domare</h5>
        <div class="form-row">
            <?php
            $fields = [
                'referee_id' => 'R',
                'umpire_id' => 'Ump',
                'dj_id' => 'DJ',
                'lj_id' => 'LJ',
                'bj_id' => 'BJ'
            ];
            foreach ($fields as $key => $label): ?>
                <div class="form-group col-md-4 autocomplete-box">
                    <label><?= $label ?></label>
                    <input type="text" class="form-control form-control-sm user-autocomplete"
                           value=""
                           data-field="<?= $key ?>" placeholder="Skriv namn...">
                    <input type="hidden" name="<?= $key ?>" id="<?= $key ?>" value="<?= $crew[$key] ?? '' ?>">
                    <div class="autocomplete-results" style="display:none;"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <h5>Utvärderare</h5>
        <div class="form-group autocomplete-box col-md-6">
            <input type="text" class="form-control form-control-sm user-autocomplete"
                   data-field="evaluator_id" placeholder="Skriv namn...">
            <input type="hidden" name="evaluator_id" id="evaluator_id">
            <div class="autocomplete-results" style="display:none;"></div>
        </div>

        <button type="submit" class="btn btn-success">💾 Spara</button>
    </form>
</div>

<script>
const users = <?= $userList ?>;
document.querySelectorAll('.user-autocomplete').forEach(input => {
    const results = input.parentElement.querySelector('.autocomplete-results');
    const hidden = document.getElementById(input.dataset.field);
    input.addEventListener('input', () => {
        const term = input.value.toLowerCase();
        results.innerHTML = '';
        hidden.value = '';
        if (term.length < 2) {
            results.style.display = 'none';
            return;
        }
        const matches = users.filter(u => u.name.toLowerCase().includes(term));
        matches.forEach(u => {
            const div = document.createElement('div');
            div.textContent = u.name;
            div.onclick = () => {
                input.value = u.name;
                hidden.value = u.id;
                results.style.display = 'none';
            };
            results.appendChild(div);
        });
        results.style.display = matches.length ? 'block' : 'none';
    });
    document.addEventListener('click', e => {
        if (!results.contains(e.target) && e.target !== input) results.style.display = 'none';
    });
});
</script>
</body>
</html>
