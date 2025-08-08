<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

// Hämta kommande matcher (inom 1 timme) som inte är avslutade
$games = $pdo->query("
    SELECT g.id, g.date, g.start_time, g.league, g.place, g.field,
           t1.name AS home_name, t2.name AS away_name
    FROM review_game g
    LEFT JOIN review_team t1 ON g.home_team_id = t1.id
    LEFT JOIN review_team t2 ON g.away_team_id = t2.id
    WHERE g.is_finished = 0
      AND TIMESTAMP(g.date, g.start_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
    ORDER BY g.date, g.start_time
")->fetchAll(PDO::FETCH_ASSOC);

// Hämta alla utvärderare
$users = $pdo->query("
    SELECT id, name FROM review_user WHERE is_evaluator = 1 ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Starta Live Review</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .autocomplete-results {
            border: 1px solid #ccc;
            max-height: 200px;
            overflow-y: auto;
            position: absolute;
            z-index: 1000;
            width: 100%;
            background: white;
        }
        .autocomplete-results div {
            padding: 8px;
            cursor: pointer;
        }
        .autocomplete-results div:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Starta Live Review</h2>

    <?php if (count($games) === 0): ?>
        <div class="alert alert-info">Inga matcher tillgängliga för live-review just nu.</div>
    <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label>Välj match</label>
                <select name="game_id" class="form-control" required>
                    <option value="">-- Välj match --</option>
                    <?php foreach ($games as $g): ?>
                        <option value="<?= $g['id'] ?>">
                            <?= htmlspecialchars($g['home_name']) ?> vs <?= htmlspecialchars($g['away_name']) ?> 
                            (<?= $g['date'] ?> <?= substr($g['start_time'], 0, 5) ?> – <?= $g['place'] ?> / <?= $g['field'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group position-relative">
                <label>Utvärderare (sök)</label>
                <input type="text" id="reviewer_search" class="form-control" placeholder="Börja skriv namn..." autocomplete="off">
                <input type="hidden" name="reviewer_id" id="reviewer_id">
                <div id="autocomplete_results" class="autocomplete-results" style="display:none;"></div>
            </div>

            <button type="submit" class="btn btn-success">Starta Review</button>
        </form>
    <?php endif; ?>
</div>

<script>
    const users = <?= json_encode($users) ?>;
    const input = document.getElementById('reviewer_search');
    const hiddenInput = document.getElementById('reviewer_id');
    const results = document.getElementById('autocomplete_results');

    input.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        results.innerHTML = '';
        hiddenInput.value = '';
        if (term.length < 2) {
            results.style.display = 'none';
            return;
        }
        const matches = users.filter(u => u.name.toLowerCase().includes(term));
        if (matches.length) {
            results.style.display = 'block';
            matches.forEach(u => {
                const div = document.createElement('div');
                div.textContent = u.name;
                div.onclick = () => {
                    input.value = u.name;
                    hiddenInput.value = u.id;
                    results.innerHTML = '';
                    results.style.display = 'none';
                };
                results.appendChild(div);
            });
        } else {
            results.style.display = 'none';
        }
    });

    document.addEventListener('click', (e) => {
        if (!results.contains(e.target) && e.target !== input) {
            results.style.display = 'none';
        }
    });
</script>
</body>
</html>

<?php
// Hantera form-svar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = intval($_POST['game_id']);
    $user_id = intval($_POST['reviewer_id']);

    if ($game_id > 0 && $user_id > 0) {
        // Finns redan en utvärdering?
        $stmt = $pdo->prepare("SELECT id FROM review_evaluation WHERE game_id = ? AND user_id = ?");
        $stmt->execute([$game_id, $user_id]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            header("Location: 201_add_observation.php?game_id=$game_id&evaluation_id=$existing");
            exit;
        }

        // Annars skapa ny
        $stmt = $pdo->prepare("
            INSERT INTO review_evaluation (game_id, user_id, is_head_evaluator, evaluation_type, create_date, update_date)
            VALUES (?, ?, 0, 'official', NOW(), NOW())
        ");
        $stmt->execute([$game_id, $user_id]);
        $evaluation_id = $pdo->lastInsertId();

        header("Location: 201_add_observation.php?game_id=$game_id&evaluation_id=$evaluation_id");
        exit;
    } else {
        echo "<div class='alert alert-danger'>Välj både match och utvärderare.</div>";
    }
}
?>
