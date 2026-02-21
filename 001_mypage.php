<?php
/**
 * 101_mypage.php
 * Purpose:
 *   Alternative landing/menu page with a tabbed navigation (Menu, Create, List, Admin).
 *   Shows a flash notice when redirected after creating a meeting.
 *
 * Requires:
 *   - php/session.php   (provides session, $currentUser, requireAuth())
 *   - php/db.php        (PDO getDatabaseConnection())
 *   - php/header.php    (shared head + Bootstrap 4.3.1 + /css/main.css)
 *   - php/footer.php    (shared footer + JS)
 */

declare(strict_types=1);

require_once __DIR__ . '/php/session.php';
require_once __DIR__ . '/php/db.php';
requireAuth();

// Page meta for header
$PAGE_TITLE = 'Min sida (101)';
$EXTRA_HEAD = <<<CSS
<style>
  .page-lead { margin-top: 1rem; }
  .menu-grid .card { transition: box-shadow .15s ease; }
  .menu-grid .card:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.08); }
</style>
CSS;

// Load shared header
require_once __DIR__ . '/php/header.php';

// --- Optional fallback: ensure Bootstrap CSS even if header failed to inject it ---
?>
<link rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
      integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9Jv8c+M7E6Z1"
      crossorigin="anonymous">
<?php

// Mark active tab by current file
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = isset($currentUser['role']) && $currentUser['role'] === 'administrator';
?>

<?php if (!empty($_GET['notice']) && $_GET['notice'] === 'meeting_created'): ?>
  <div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Möte skapades.
      <button type="button" class="close" data-dismiss="alert" aria-label="Stäng">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
  </div>
<?php endif; ?>

<!-- Top navigation tabs -->
<div class="container mt-3">
  <ul class="nav nav-tabs">
    <li class="nav-item">
      <a class="nav-link <?= $currentPage === '101_mypage.php' ? 'active' : '' ?>" href="101_mypage.php">Meny</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentPage === '101_create_meeting.php' ? 'active' : '' ?>" href="101_create_meeting.php">Skapa möte</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentPage === '102_list_meetings.php' ? 'active' : '' ?>" href="102_list_meetings.php">Lista möten</a>
    </li>
    <?php if ($isAdmin): ?>
      <li class="nav-item">
        <a class="nav-link <?= $currentPage === '999_admin_menu.php' ? 'active' : '' ?>" href="999_admin_menu.php">Admin</a>
      </li>
    <?php endif; ?>
    <li class="nav-item ml-auto">
      <a class="nav-link text-danger" href="002_logout.php">Logga ut</a>
    </li>
  </ul>
</div>

<!-- Lead -->
<div class="container page-lead">
  <h1 class="h4 mb-1">
    Välkommen<?= isset($currentUser['name']) ? ', ' . htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') : '' ?>!
  </h1>
  <p class="text-muted mb-4">Här kan du skapa, lista och hantera möten.</p>
</div>

<!-- Quick actions -->
<div class="container menu-grid">
  <div class="row">
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-2">Skapa möte</h5>
          <p class="card-text text-muted">Lägg till titel, datum, tid, plats och mötestyp. Koppla till förening vid behov.</p>
          <div class="mt-auto">
            <a href="101_create_meeting.php" class="btn btn-primary">Skapa nytt möte</a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-2">Lista möten</h5>
          <p class="card-text text-muted">Visa, filtrera och sortera möten efter datum och förening.</p>
          <div class="mt-auto">
            <a href="102_list_meetings.php" class="btn btn-outline-primary">Öppna möteslista</a>
          </div>
        </div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-2">Adminpanel</h5>
          <p class="card-text text-muted">Hantera systeminställningar, föreningar, användare och behörigheter.</p>
          <div class="mt-auto">
            <a href="999_admin_menu.php" class="btn btn-outline-secondary">Öppna adminpanel</a>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-2">Snabbgenvägar</h5>
          <ul class="mb-3">
            <li><a href="101_create_meeting.php">Skapa möte</a></li>
            <li><a href="102_list_meetings.php">Visa alla möten</a></li>
            <?php if ($isAdmin): ?>
              <li><a href="999_admin_menu.php">Admin</a></li>
            <?php endif; ?>
          </ul>
          <div class="text-muted small">Tips: Lägg till fler kort/lenkar här vid behov.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Include shared footer (version, copyright, JS, Matomo slot)
require_once __DIR__ . '/php/footer.php';
