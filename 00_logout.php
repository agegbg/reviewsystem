<?php
// 00_logout.php – Logs the user out by destroying session

session_start();
session_unset();
session_destroy();

// Redirect to start page
header("Location: index.php");
exit;
