<?php
// 99_audit_files.php
// Purpose: Audit all root PHP files, compare with web_files, detect duplicates/overlaps, and check required headers.
// Calls/Requires: php/session.php, php/db.php, php/file_register.php

// Start the session and optionally enforce login (controlled via php/session.php)
require_once __DIR__ . '/php/session.php';

// Load database connection (PDO with utf8mb4)
require_once __DIR__ . '/php/db.php';

// Add file info to database for menu/system tracking
require_once __DIR__ . '/php/file_register.php';
updateFileInfo(basename(__FILE__), 'Audit root PHP files against web_files; detect duplicates and missing headers.');

$pdo = getDatabaseConnection();

/**
 * Helper: fetch web_files into an associative map by filename
 */
function getWebFilesMap(PDO $pdo): array {
    $stmt = $pdo->query("SELECT filename, description, roles, show_in_menu, menu_order, update_date FROM web_files WHERE system = 'review' ORDER BY filename ASC");
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[$row['filename']] = $row;
    }
    return $map;
}

/**
 * Helper: build “pattern keys” used to spot likely duplicates (e.g., add_observation, list_games, edit_team)
 */
function buildPatternKey(string $filename): string {
    $base = strtolower(preg_replace('/\.php$/i', '', $filename));
    // Normalize common verbs and nouns
    $patterns = [
        ['find' => ['add_observation','add-observation','addobs'], 'key' => 'add_observation'],
        ['find' => ['list_games','list-game','listgames','list'],    'key' => 'list_games'],
        ['find' => ['edit_team','edit-team','team_edit'],            'key' => 'edit_team'],
        ['find' => ['list_teams','teams_list'],                      'key' => 'list_teams'],
        ['find' => ['add_game','addgame'],                           'key' => 'add_game'],
        ['find' => ['confirm_game','confirmgame'],                   'key' => 'confirm_game'],
        ['find' => ['review_links','reviewlinks'],                   'key' => 'review_links'],
        ['find' => ['ref','refs','referee'],                         'key' => 'ref_listing'],
    ];
    foreach ($patterns as $p) {
        foreach ($p['find'] as $needle) {
            if (strpos($base, $needle) !== false) return $p['key'];
        }
    }
    // default to whole base to group exact same names with different prefixes
    return $base;
}

/**
 * Helper: check header lines presence
 * We require these lines (exact includes may vary, but we check for substrings):
 * - require_once 'php/session.php'
 * - require_once 'php/db.php'
 * - require_once 'php/file_register.php' AND updateFileInfo(
 */
function checkHeaders(string $path): array {
    $code = @file_get_contents($path);
    if ($code === false) return ['read_error' => true];
    $missing = [];
    if (stripos($code, "php/session.php") === false)       $missing[] = "require 'php/session.php'";
    if (stripos($code, "php/db.php") === false)            $missing[] = "require 'php/db.php'";
    if (stripos($code, "php/file_register.php") === false) $missing[] = "require 'php/file_register.php'";
    if (stripos($code, "updateFileInfo(") === false)       $missing[] = "updateFileInfo(...) call";
    return ['read_error' => false, 'missing' => $missing];
}

// --- Scan root for .php files (exclude vendor/php/ etc.) ---
$rootFiles = array_values(array_filter(scandir(__DIR__), function($f) {
    if (!preg_match('/\.php$/i', $f)) return false;
    // Exclude common non-pages if needed, but for now include all:
    return true;
}));

$webFilesMap = getWebFilesMap($pdo);

// Build duplicate buckets
$dupBuckets = [];     // pattern_key => [filenames...]
$headerIssues = [];   // filename => missing headers array
$notInWeb = [];       // files present on disk but missing in web_files
$notOnDisk = [];      // web_files entries not present on disk

foreach ($rootFiles as $file) {
    $key = buildPatternKey($file);
    $dupBuckets[$key][] = $file;

    $check = checkHeaders(__DIR__ . '/' . $file);
    if ($check['read_error'] || !empty($check['missing'])) {
        $headerIssues[$file] = $check['read_error'] ? ['(read error)'] : $check['missing'];
    }

    if (!isset($webFilesMap[$file])) {
        $notInWeb[] = $file;
    }
}

// Find web_files entries that have no real file on disk
foreach ($webFilesMap as $wfFile => $_) {
    if (!in_array($wfFile, $rootFiles, true)) {
        $notOnDisk[] = $wfFile;
    }
}

// Simple Bootstrap UI
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Audit files</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
 integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T"
 crossorigin="anonymous">
<style>
.badge-file { font-size: 100%; }
.code { font-family: monospace; }
</style>
</head>
<body class="p-3">
<div class="container-fluid">
  <h1 class="mb-3">Audit: Root PHP files</h1>

  <div class="card mb-3">
    <div class="card-body">
      <p class="mb-1"><strong>Total files on disk:</strong> <?= count($rootFiles) ?></p>
      <p class="mb-1"><strong>Missing in web_files:</strong> <?= count($notInWeb) ?></p>
      <p class="mb-1"><strong>Present in web_files but missing on disk:</strong> <?= count($notOnDisk) ?></p>
    </div>
  </div>

  <!-- Duplicate / overlap buckets -->
  <div class="card mb-3">
    <div class="card-header">Possible duplicates / overlaps (by pattern)</div>
    <div class="card-body">
      <?php
      $suspect = array_filter($dupBuckets, fn($arr) => count($arr) > 1);
      if (!$suspect) {
          echo '<p>No obvious duplicates found.</p>';
      } else {
          echo '<ul class="list-group">';
          foreach ($suspect as $pattern => $files) {
              echo '<li class="list-group-item">';
              echo '<strong class="mr-2">'.htmlspecialchars($pattern).'</strong>';
              foreach ($files as $f) {
                  $link = htmlspecialchars($f);
                  echo "<a href='{$link}' class='badge badge-info badge-file mr-2'>{$link}</a>";
              }
              echo '</li>';
          }
          echo '</ul>';
      }
      ?>
    </div>
  </div>

  <!-- Header issues -->
  <div class="card mb-3">
    <div class="card-header">Header issues (missing includes/updateFileInfo)</div>
    <div class="card-body">
      <?php
      if (!$headerIssues) {
          echo '<p>All files passed header checks.</p>';
      } else {
          echo '<div class="table-responsive"><table class="table table-sm table-striped">';
          echo '<thead><tr><th>File</th><th>Missing</th></tr></thead><tbody>';
          foreach ($headerIssues as $file => $missing) {
              echo '<tr>';
              echo '<td><a href="'.htmlspecialchars($file).'" target="_blank">'.htmlspecialchars($file).'</a></td>';
              echo '<td class="code">'.htmlspecialchars(implode(', ', $missing)).'</td>';
              echo '</tr>';
          }
          echo '</tbody></table></div>';
      }
      ?>
    </div>
  </div>

  <!-- Not in web_files -->
  <div class="card mb-3">
    <div class="card-header">On disk but NOT in web_files</div>
    <div class="card-body">
      <?php
      if (!$notInWeb) {
          echo '<p>Everything on disk exists in web_files.</p>';
      } else {
          echo '<ul class="list-group">';
          foreach ($notInWeb as $f) {
              echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
              echo '<span>'.htmlspecialchars($f).'</span>';
              echo '<a class="btn btn-sm btn-outline-primary" href="901_review_file.php?filename='.urlencode($f).'">Open</a>';
              echo '</li>';
          }
          echo '</ul>';
      }
      ?>
    </div>
  </div>

  <!-- In web_files but missing on disk -->
  <div class="card mb-5">
    <div class="card-header">In web_files but MISSING on disk</div>
    <div class="card-body">
      <?php
      if (!$notOnDisk) {
          echo '<p>No orphan records found in web_files.</p>';
      } else {
          echo '<ul class="list-group">';
          foreach ($notOnDisk as $f) {
              echo '<li class="list-group-item">'.htmlspecialchars($f).'</li>';
          }
          echo '</ul>';
      }
      ?>
    </div>
  </div>

</div>
</body>
</html>
