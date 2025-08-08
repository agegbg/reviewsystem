<?php
// file_register.php – Automatically registers the current file in the web_files table
// Can be disabled by defining FILE_LOGGING_DISABLED before including this file

if (defined('FILE_LOGGING_DISABLED') && FILE_LOGGING_DISABLED) {
    return;
}

$currentFile = basename($_SERVER['SCRIPT_FILENAME'] ?? 'unknown.php');
$defaultDescription = 'Auto-registered file';

// Function definition
function updateFileInfo($filename, $description = '', $system = 'review', $show_in_menu = 0, $menu_order = 0) {
    try {
        require_once __DIR__ . '/db.php';
        $pdo = getDatabaseConnection();
    } catch (Exception $e) {
        // Could not connect to database – skip logging
        return;
    }

    if (!$pdo) return;

    $stmt = $pdo->prepare("SELECT description FROM web_files WHERE filename = ? AND system = ?");
    $stmt->execute([$filename, $system]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (trim($existing['description']) !== trim($description)) {
            $stmt = $pdo->prepare("UPDATE web_files SET description = ?, update_date = NOW() WHERE filename = ? AND system = ?");
            $stmt->execute([$description, $filename, $system]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO web_files (system, filename, description, show_in_menu, menu_order, create_date, update_date)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$system, $filename, $description, $show_in_menu, $menu_order]);
    }
}

// 🟢 Auto-register file
updateFileInfo($currentFile, $defaultDescription);
