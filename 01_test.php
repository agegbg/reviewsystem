<?php
include 'php/db.php'; // Vi testar MySQLi

if (!isset($mysqli)) {
    die("❌ \$mysqli är inte definierad.");
}

echo "✅ Ansluten till databasen!<br>";

$result = $mysqli->query("SHOW TABLES");

if ($result) {
    while ($row = $result->fetch_array()) {
        echo "Tabell: " . $row[0] . "<br>";
    }
} else {
    echo "❌ SQL-fel: " . $mysqli->error;
}
?>
