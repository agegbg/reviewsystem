<?php
// php/header.php
declare(strict_types=1);

// Page title and extra head content
if (empty($PAGE_TITLE)) $PAGE_TITLE = 'My Football System';
if (empty($EXTRA_HEAD)) $EXTRA_HEAD = '';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 4.3.1 CSS (global) -->
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9Jv8c+M7E6Z1"
        crossorigin="anonymous">

  <!-- Your site CSS -->
  <link rel="stylesheet" href="/css/main.css">

  <!-- Page-specific head -->
  <?= $EXTRA_HEAD ?>
</head>
<body>
