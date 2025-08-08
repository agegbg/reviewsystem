<?php

$table_prefix = 'scoreboard_'; // <-- Här ändrar du för varje projekt


// Database connection settings
$host = 'localhost';        // Hostname for the database
$user = 's57703_domare';      // Database username
$pass = '4eSNkBdxMDf23DxaNHGK'; // Database password
$db = 's57703_domare';        // Database name
$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    error_log("MySQLi connection failed: " . $mysqli->connect_error);
    die("Anslutningen till databasen misslyckades (mysqli).");
}

if (!$mysqli->set_charset("utf8")) {
    error_log("Fel vid inställning av teckenuppsättning: " . $mysqli->error);
    die("Fel vid inställning av teckenuppsättning (mysqli).");
}

/* PDO-anslutning – används av nya systemdelar */
function getDatabaseConnection() {
    static $pdo = null;

    if ($pdo === null) {
        global $host, $user, $pass, $db;

        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("PDO connection failed: " . $e->getMessage());
            die("Kunde inte ansluta till databasen (PDO).");
        }
    }

    return $pdo;
}
?>
