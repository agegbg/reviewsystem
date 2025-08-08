<?php
// inject_session_check.php – Check which PHP files include correct session/db injection

$baseDir = __DIR__; // ändra vid behov

$targetCode = <<<EOD
<?php
// Start the session and optionally enforce login (controlled via php/session.php)
require_once 'php/session.php';

// Load database connection (PDO with utf8mb4)
require_once 'php/db.php';
EOD;

$targetCode = str_replace(["\r\n", "\r"], "\n", $targetCode);

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
$matching = [];
$missing = [];

foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;

    $filePath = $file->getPathname();
    $contents = file_get_contents($filePath);
    $normalizedContents = str_replace(["\r\n", "\r"], "\n", $contents);

    $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);

    if (strpos($normalizedContents, $targetCode) !== false) {
        $matching[] = $relativePath;
    } else {
        $missing[] = $relativePath;
    }
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Session/DB Injection Check</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { padding: 2rem; }
        h2 { margin-top: 2rem; }
        table td { font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Kontroll av session/db-kodblock</h1>

        <h2 class="text-success">✅ Filer med korrekt kodblock (<?= count($matching) ?> st)</h2>
        <table class="table table-sm table-bordered">
            <thead class="thead-light"><tr><th>Filnamn</th></tr></thead>
            <tbody>
                <?php foreach ($matching as $file): ?>
                    <tr><td><?= htmlspecialchars($file) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="text-danger">❌ Filer utan kodblock (<?= count($missing) ?> st)</h2>
        <table class="table table-sm table-bordered">
            <thead class="thead-light"><tr><th>Filnamn</th></tr></thead>
            <tbody>
                <?php foreach ($missing as $file): ?>
                    <tr><td><?= htmlspecialchars($file) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="text-muted">Totalt granskade filer: <?= count($matching) + count($missing) ?></p>
    </div>
</body>
</html>
