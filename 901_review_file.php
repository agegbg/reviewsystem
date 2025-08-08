<?php
require_once 'php/db.php';
$pdo = getDatabaseConnection();

$filename = $_GET['file'] ?? '';
$comment = '';
$checked = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'];
    $comment = $_POST['comment'];
    $checked = isset($_POST['checked']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO web_file_review (filename, comment, checked)
                           VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE comment = VALUES(comment), checked = VALUES(checked)");
    $stmt->execute([$filename, $comment, $checked]);
    echo "<div class='alert alert-success'>Sparat för <b>$filename</b></div>";
}

// Hämta existerande data
if ($filename) {
    $stmt = $pdo->prepare("SELECT * FROM web_file_review WHERE filename = ?");
    $stmt->execute([$filename]);
    $data = $stmt->fetch();
    if ($data) {
        $comment = $data['comment'];
        $checked = $data['checked'];
    }
}
?>

<!-- Formulär -->
<form method="post" class="p-3">
    <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
    <h3><?= htmlspecialchars($filename) ?></h3>
    <div class="form-group">
        <label for="comment">Kommentar:</label>
        <textarea class="form-control" name="comment" id="comment" rows="3"><?= htmlspecialchars($comment) ?></textarea>
    </div>
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="checked" name="checked" <?= $checked ? 'checked' : '' ?>>
        <label class="form-check-label" for="checked">Denna fil ska vara med i nya systemet</label>
    </div>
    <button type="submit" class="btn btn-primary">Spara</button>
</form>
