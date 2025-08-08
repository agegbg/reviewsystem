<?php
// Skapar file_info-tabellen om den inte finns
function create_table_file_info($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS file_info (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            description TEXT,
            last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            create_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            update_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($sql);
}
?>
