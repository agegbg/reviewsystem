<?php
require_once 'php/session.php';
require_once 'php/db.php';
require_once 'php/file_register.php';
updateFileInfo(basename(__FILE__), 'Lista alla registrerade filer och deras metadata', 'admin', 1, 99);

$pdo = getDatabaseConnection();
$stmt = $pdo->query("SELECT * FROM web_files ORDER BY system ASC, menu_order ASC, filename ASC");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Lista filer</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .muted { color: #aaa; font-style: italic; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h4 class="mb-4">📁 Lista över registrerade filer</h4>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th>System</th>
                <th>Filnamn</th>
                <th>Beskrivning</th>
                <th>Roller</th>
                <th>Meny</th>
                <th>Sort</th>
                <th>Senast använd</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['system']) ?></td>
                    <td class="nowrap"><?= htmlspecialchars($f['filename']) ?></td>
                    <td><?= $f['show_in_menu'] ? htmlspecialchars($f['description']) : "<span class='muted'>" . htmlspecialchars($f['description']) . "</span>" ?></td>
                    <td><?= htmlspecialchars($f['roles']) ?></td>
                    <td class="text-center"><?= $f['show_in_menu'] ? '✅' : '❌' ?></td>
                    <td class="text-right"><?= htmlspecialchars($f['menu_order']) ?></td>
                    <td class="nowrap"><?= htmlspecialchars($f['last_access']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
