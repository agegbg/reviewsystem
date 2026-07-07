<?php

$configFile = __DIR__ . '/config.local.php';
if (!file_exists($configFile)) {
    error_log("Missing local config file: " . $configFile);
    die("Konfigurationsfil saknas.");
}

$config = require $configFile;
if (!is_array($config) || empty($config['database']) || !is_array($config['database'])) {
    error_log("Invalid database config in: " . $configFile);
    die("Databaskonfigurationen ar ogiltig.");
}

$table_prefix = $config['table_prefix'] ?? 'scoreboard_';

$host = $config['database']['host'] ?? '';
$user = $config['database']['user'] ?? '';
$pass = $config['database']['pass'] ?? '';
$db = $config['database']['name'] ?? '';

if ($host === '' || $user === '' || $db === '') {
    error_log("Database config is missing host, user, or name.");
    die("Databaskonfigurationen saknar nodvandiga varden.");
}

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    error_log("MySQLi connection failed: " . $mysqli->connect_error);
    die("Anslutningen till databasen misslyckades (mysqli).");
}

if (!$mysqli->set_charset("utf8")) {
    error_log("Fel vid installning av teckenuppsattning: " . $mysqli->error);
    die("Fel vid installning av teckenuppsattning (mysqli).");
}

/* PDO connection used by newer system parts */
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
