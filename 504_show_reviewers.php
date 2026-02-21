<?php
/**
 * File: 504_show_reviewers.php
 * Description: Shows all reviewers (review_evaluation rows) linked to a specific game.
 *              Displays reviewer name/email, head-evaluator flag, evaluation type and a link to open review.
 * Called from: 504_list_games.php (button "Reviewers")
 *
 * Conventions:
 * - All comments in English.
 * - Bootstrap 4.3.1 via CDN.
 * - Includes shared footer at the bottom.
 */

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Show reviewers linked to a game');

$pdo = getDatabaseConnection();

// Validate input
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if ($game_id <= 0) {
    http_response_code(400);
    die('Missing or invalid game_id');
}

// Game header
$gsql = "
    SELECT 
        g.id, g.date, g.league,
        ht.name AS home_name, at.name AS away_name,
        g.score_home, g.score_away
    FROM review_game g
    LEFT JOIN review_team ht ON g.home_team_id = ht.id
    LEFT JOIN review_team at ON g.away_team_id = at.id
    WHERE g.id = ?
    LIMIT 1
";
$gst = $pdo->prepare($gsql);
$gst->execute([$game_id]);
$game = $gst->fetch(PDO::FETCH_ASSOC);
if (!$game) {
    http_response_code(404);
    die('Game not found');
}

// Reviewers for this game
$esql = "
    SELECT 
        e.id,
        e.user_id,
        e.is_head_evaluator,
        e.evaluation_type,
        e.shared_link_code,
        u.name  AS user_name,
        u.email AS user_email
    FROM review_evaluation e
    LEFT JOIN review_user u ON e.user_id = u.id
    WHERE e.game_id = ?
    ORDER BY e.is_head_evaluator DESC, u.name ASC, e.id ASC
";
$est = $pdo->prepare($esql);
$est->execute([$game_id]);
$reviewers = $est->fetchAll(PDO::FETCH_ASSOC);

function buildReviewLink(int $gameId, int $evaluationId): string {
    // Example path used in your system:
    return "202_add_observation.php?game_id={$gameId}&evaluation_id={$evaluationId}";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reviewers for Game #<?= (int)$game_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 4.3.1 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css">
    <style>
        .score-badge { min-width: 60px; display: inline-block; text-align: center; }
        .chip { display:inline-block; padding:.25rem .5rem; border-radius:9999px; font-size:.75rem; }
        .chip-head { background:#e6f3ff; border:1px solid #b3dcff; }
        .chip-type { background:#f7f7f7; border:1px solid #e1e1e1; }
        .wrap-email { max-width: 260px; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">
            Reviewers – <?= htmlspecialchars($game['home_name'] ?? '—') ?>
            <span class="mx-1">vs</span>
            <?= htmlspecialchars($game['away_name'] ?? '—') ?>
        </h1>
        <a class="btn btn-sm btn-outline-secondary" href="504_list_games.php">← Back to games</a>
    </div>

    <div class="mb-3 text-muted">
        <span><?= htmlspecialchars($game['date']) ?></span>
        <?php if (!empty($game['league'])): ?>
            <span class="mx-2">•</span><span><?= htmlspecialchars($game['league']) ?></span>
        <?php endif; ?>
        <span class="mx-2">•</span>
        <?php
        $sh = is_null($game['score_home']) ? '–' : (int)$game['score_home'];
        $sa = is_null($game['score_away']) ? '–' : (int)$game['score_away'];
        ?>
        <span class="badge badge-secondary score-badge"><?= $sh ?> : <?= $sa ?></span>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong>Reviewers (<?= count($reviewers) ?>)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th style="width:260px;">Name</th>
                        <th style="width:260px;">Email</th>
                        <th style="width:140px;">Flags</th>
                        <th style="width:140px;">Type</th>
                        <th>Open</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($reviewers)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No reviewers linked to this game.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reviewers as $i => $r): ?>
                            <?php
                            $isHead = (int)($r['is_head_evaluator'] ?? 0) === 1;
                            $etype  = $r['evaluation_type'] ?: 'official';
                            $name   = $r['user_name'] ?: '—';
                            $email  = $r['user_email'] ?: '—';
                            $link   = buildReviewLink($game_id, (int)$r['id']);
                            ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($name) ?></td>
                                <td class="wrap-email">
                                    <?php if ($email !== '—'): ?>
                                        <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isHead): ?>
                                        <span class="chip chip-head">Head evaluator</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="chip chip-type"><?= htmlspecialchars($etype) ?></span></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($link) ?>">
                                        Open review
                                    </a>
                                    <?php if (!empty($r['shared_link_code'])): ?>
                                        <small class="text-muted ml-2">code: <?= htmlspecialchars($r['shared_link_code']) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
?>
</body>
</html>
