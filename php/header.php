<?php
/**
 * header.php
 * Common HTML <head> and opening <body> for all pages.
 * 
 * Usage:
 *   $PAGE_TITLE = 'Referee Statistics';
 *   $EXTRA_HEAD = '<meta name="robots" content="noindex">';
 *   require_once __DIR__ . '/header.php';
 *
 * Notes:
 * - Keep comments in English so it's easy to maintain.
 * - If this is moved into WordPress later, map variables to WP functions (e.g. wp_head()).
 */

// Sensible defaults
if (!isset($PAGE_TITLE)) { $PAGE_TITLE = 'Dukes Tourney'; }
if (!isset($EXTRA_HEAD)) { $EXTRA_HEAD = ''; }

// Optional: expose a per-page identifier for analytics/menus
if (!isset($PAGE_ID)) { $PAGE_ID = basename($_SERVER['PHP_SELF']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= htmlspecialchars($PAGE_TITLE) ?></title>

  <!-- Bootstrap CSS (pin 4.3.1 to match current design) -->
  <link
    rel="stylesheet"
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

  <!-- Minimal, shared CSS across the app -->
  <style>
    /* Layout helpers */
    .container-narrow { max-width: 980px; }
    .nowrap { white-space: nowrap; }
    .btn-wide { min-width: 160px; }

    /* Badges / pills */
    .pill { display:inline-block; background:#0d6efd; color:#fff;
            padding:.55rem 1rem; border-radius:.5rem; font-weight:600; }

    /* Tables */
    .table thead th { vertical-align: middle; text-align: center; }
    .table tbody td { text-align: center; }
    .table tbody td.name { text-align: left; }
    .totals-row { font-weight:700; background:#f8f9fa; }
    .counts th { background:#f8f9fa; }
    .counts th, .counts td { text-align:center; }

    /* Avatars */
    .avatar { width:96px; height:96px; object-fit:cover; border-radius:50%;
              background:#f1f3f5; display:flex; align-items:center; justify-content:center;
              font-weight:600; color:#999; }

    /* Page title */
    .page-title { font-size:2rem; font-weight:700; text-align:center; }
  </style>

  <!-- Page-specific head content (optional) -->
  <?= $EXTRA_HEAD ?>

  <!-- Matomo (Piwik) placeholder — add your real script later
  <script>
    // Example (replace URL and siteId):
    // var _paq = window._paq = window._paq || [];
    // _paq.push(['trackPageView']);
    // _paq.push(['enableLinkTracking']);
    // (function() {
    //   var u="//analytics.example.com/";
    //   _paq.push(['setTrackerUrl', u+'matomo.php']);
    //   _paq.push(['setSiteId', '1']);
    //   var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    //   g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    // })();
  </script>
  -->
</head>
<body class="p-4">
