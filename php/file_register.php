<?php
// Registers file in the web_files table (if not already registered or if description is updated)

require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();

function updateFileInfo($filename, $description = '', $system = 'review') {
    global $pdo;

    // Kontrollera om filen redan finns i databasen
    $stmt = $pdo->prepare("SELECT description FROM web_files WHERE filename = ? AND system = ?");
    $stmt->execute([$filename, $system]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Om beskrivningen är tom eller redan samma – gör inget
        if (trim($existing['description']) === trim($description)) {
            return; // Ingen ändring behövs
        }

        // Annars – uppdatera beskrivningen och timestamp
        $stmt = $pdo->prepare("UPDATE web_files SET description = ?, update_date = NOW() WHERE filename = ? AND system = ?");
        $stmt->execute([$description, $filename, $system]);
    } else {
        // Lägg till ny fil i databasen
        $stmt = $pdo->prepare("INSERT INTO web_files (filename, description, system, create_date, update_date) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$filename, $description, $system]);
    }
}
