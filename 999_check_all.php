<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Add file info to database for menu/system tracking
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Review and update status for all registered files', 'review', 0);

$pdo = getDatabaseConnection();

// Handle filter
$filter = $_GET['filter'] ?? '';

// Fetch all web_files
$stmt = $pdo->query("SELECT * FROM web_files ORDER BY filename");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch review data
$reviewStmt = $pdo->query("SELECT * FROM web_file_review");
$reviewData = [];
foreach ($reviewStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $reviewData[$r['filename']] = $r;
}

// Sort: incomplete (no status/comment/tested) first
usort($files, function($a, $b) use ($reviewData) {
    $ra = $reviewData[$a['filename']] ?? [];
    $rb = $reviewData[$b['filename']] ?? [];

    $aEmpty = empty($ra['status']) && empty($ra['comment']) && empty($ra['last_tested']);
    $bEmpty = empty($rb['status']) && empty($rb['comment']) && empty($rb['last_tested']);

    return $bEmpty <=> $aEmpty; // true first
});

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['filename'] as $i => $filename) {
        $status = $_POST['status'][$i];
        $needs_fix = isset($_POST['needs_fix'][$filename]) ? 1 : 0;
        $related = $_POST['related_files'][$i];
        $tested = $_POST['last_tested'][$i];
        $comment = $_POST['comment'][$i];

        $stmt = $pdo->prepare("SELECT id FROM web_file_review WHERE filename = ?");
        $stmt->execute([$filename]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $update = $pdo->prepare("UPDATE web_file_review SET status = ?, needs_fix = ?, related_files = ?, last_tested = ?, comment = ?, update_date = NOW() WHERE filename = ?");
            $update->execute([$status, $needs_fix, $related, $tested ?: null, $comment, $filename]);
        } else {
            $insert = $pdo->prepare("INSERT INTO web_file_review (filename, status, needs_fix, related_files, last_tested, comment, checked, create_date, update_date) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
            $insert->execute([$filename, $status, $needs_fix, $related, $tested ?: null, $comment]);
        }
    }
    echo '<div class="alert alert-success">Changes saved!</div>';
    header("Refresh:1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Review</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        input[type="text"], textarea { width: 100%; }
        th, td { vertical-align: middle !important; }
        .timestamp-btn { font-size: 0.9em; padding: 0.2em 0.5em; }
        .filename-col { width: 220px; }
        .status-col { width: 160px; }
        .fix-col { width: 70px; text-align: center; }
        .related-col { width: 180px; }
        .tested-col { width: 200px; }
        .comment-col { width: 300px; }
        .filter-form { margin-bottom: 1rem; }
    </style>
    <script>
        function setNow(inputId) {
            const now = new Date();
            const timestamp = now.toISOString().slice(0, 19).replace('T', ' ');
            document.getElementById(inputId).value = timestamp;
        }
        function filterByStatus() {
            const val = document.getElementById('filter').value;
            window.location.href = '?filter=' + encodeURIComponent(val);
        }
    </script>
</head>
<body class="p-4">
    <div class="container">
        <form method="get" class="filter-form form-inline">
            <label class="mr-2">Filter by status:</label>
            <select id="filter" name="filter" class="form-control form-control-sm mr-2" onchange="filterByStatus()">
                <option value="">(Show all)</option>
                <option value="OK" <?= $filter === 'OK' ? 'selected' : '' ?>>✅ OK</option>
                <option value="Broken" <?= $filter === 'Broken' ? 'selected' : '' ?>>❌ Broken</option>
                <option value="Needs Fix" <?= $filter === 'Needs Fix' ? 'selected' : '' ?>>🛠️ Needs Fix</option>
                <option value="Not Used" <?= $filter === 'Not Used' ? 'selected' : '' ?>>🚫 Not Used</option>
            </select>
        </form>

        <form method="post">
            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                    <tr>
                        <th class="filename-col">Filename</th>
                        <th class="status-col">Status</th>
                        <th class="fix-col">Fix?</th>
                        <th class="related-col">Linked to</th>
                        <th class="tested-col">Last tested</th>
                        <th class="comment-col">Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $statusOptions = [
                        'OK' => '✅ OK',
                        'Broken' => '❌ Broken',
                        'Needs Fix' => '🛠️ Needs Fix',
                        'Deprecated' => '⚠️ Deprecated',
                        'In Progress' => '🔄 In Progress',
                        'Test Only' => '🧪 Test Only',
                        'Template' => '📁 Template', 
                        'Not Used' => '🚫 Not Used'
                    ];
                    foreach ($files as $index => $f): 
                        $r = $reviewData[$f['filename']] ?? [];
                        $inputId = "tested_" . $index;

                        // Filter logic
                        if ($filter && ($r['status'] ?? '') !== $filter) continue;
                    ?>
                        <tr>
                            <td>
                                <input type="hidden" name="filename[]" value="<?= htmlspecialchars($f['filename']) ?>">
                                <?= htmlspecialchars($f['filename']) ?>
                            </td>
                            <td>
                                <select name="status[]" class="form-control">
                                    <option value="">–</option>
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($value === ($r['status'] ?? '')) ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="checkbox" name="needs_fix[<?= $f['filename'] ?>]" <?= !empty($r['needs_fix']) ? 'checked' : '' ?>>
                            </td>
                            <td><input type="text" name="related_files[]" value="<?= $r['related_files'] ?? '' ?>"></td>
                            <td>
                                <input type="text" id="<?= $inputId ?>" name="last_tested[]" value="<?= $r['last_tested'] ?? '' ?>">
                                <button type="button" class="btn btn-sm btn-outline-secondary timestamp-btn" onclick="setNow('<?= $inputId ?>')">🕒</button>
                            </td>
                            <td><textarea name="comment[]"><?= $r['comment'] ?? '' ?></textarea></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">💾 Save changes</button>
        </form>
    </div>
</body>
</html>
