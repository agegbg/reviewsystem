<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';

// Add file info to database for menu/system tracking
require_once 'php/file_register.php';

updateFileInfo(basename(__FILE__), 'List files in root not yet registered in web_files and add them manually', 'review', 0);

// File: 999_missing_in_web_files.php
// Lists all root PHP files missing in web_files and allows adding them one by one

$pdo = getDatabaseConnection();

// Get all PHP files in root folder
$allFiles = array_filter(scandir(__DIR__), function($f) {
    return is_file($f) && pathinfo($f, PATHINFO_EXTENSION) === 'php';
});

// Get files already in web_files
$stmt = $pdo->query("SELECT filename FROM web_files");
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Find missing files
$missingFiles = array_diff($allFiles, $existing);

// Handle add (one file at a time)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    $filename = $_POST['filename'];
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO web_files (system, filename, description, create_date, update_date) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute(['review', $filename, $description]);

    if ($status !== '') {
        $stmt = $pdo->prepare("INSERT INTO web_file_review (filename, status, comment, checked, create_date, update_date) VALUES (?, ?, ?, 1, NOW(), NOW())");
        $stmt->execute([$filename, $status, $description]);
    }

    echo '<div class="alert alert-success">Added ' . htmlspecialchars($filename) . '</div>';
    header("Refresh:1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Missing Files</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        input[type="text"], select { width: 100%; }
        td, th { vertical-align: middle !important; }
        .desc-col { width: 300px; }
        .status-col { width: 180px; }
        .btn-col { width: 100px; text-align: center; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <table class="table table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th>Filename</th>
                    <th class="desc-col">Comment</th>
                    <th class="status-col">Status</th>
                    <th class="btn-col">Add</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $statusOptions = [
                    '' => '–',
                    'OK' => '✅ OK',
                    'Broken' => '❌ Broken',
                    'Needs Fix' => '🛠️ Needs Fix',
                    'Deprecated' => '⚠️ Deprecated',
                    'In Progress' => '🔄 In Progress',
                    'Test Only' => '🧪 Test Only',
                    'Template' => '📁 Template',
                    'Not Used' => '🚫 Not Used'
                ];
                foreach ($missingFiles as $filename): ?>
                    <tr>
                        <form method="post" class="form-inline">
                            <td>
                                <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
                                <?= htmlspecialchars($filename) ?>
                            </td>
                            <td>
                                <input type="text" name="description" placeholder="What does this file do?" class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="status" class="form-control form-control-sm">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <button type="submit" class="btn btn-sm btn-success">➕ Add</button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
