<?php
/**
 * Shared page chrome: <head>, sidebar, flash messages, footer.
 */

function icon(string $name): string
{
    $p = [
        'home'     => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
        'book'     => '<path d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2z"/><path d="M4 19V5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'file'     => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/>',
        'quiz'     => '<circle cx="7" cy="7" r="3"/><circle cx="17" cy="7" r="3"/><circle cx="7" cy="17" r="3"/><circle cx="17" cy="17" r="3" fill="currentColor"/>',
        'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0"/><path d="M16 4.5a3.5 3.5 0 010 7M18 14a6 6 0 013.5 6"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',
        'list'     => '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1.2"/><circle cx="4" cy="12" r="1.2"/><circle cx="4" cy="18" r="1.2"/>',
        'logout'   => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'trash'    => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'check'    => '<path d="M5 12l5 5 9-10"/>',
        'x'        => '<path d="M6 6l12 12M18 6L6 18"/>',
        'eye'      => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($p[$name] ?? '') . '</svg>';
}

function nav_items(string $role): array
{
    if ($role === 'admin') {
        return [
            'dashboard' => ['Dashboard', 'dashboard.php', 'home'],
            'courses'   => ['Courses', 'courses.php', 'book'],
            'timetable' => ['Timetable', 'timetable.php', 'calendar'],
            'questions' => ['Question bank', 'questions.php', 'list'],
            'materials' => ['Study materials', 'materials.php', 'file'],
            'results'   => ['Results', 'results.php', 'chart'],
            'users'     => ['Users', 'users.php', 'users'],
            'profile'   => ['My profile', 'profile.php', 'user'],
        ];
    }
    return [
        'dashboard' => ['Dashboard', 'dashboard.php', 'home'],
        'quizzes'   => ['Quizzes', 'quizzes.php', 'quiz'],
        'results'   => ['My results', 'results.php', 'chart'],
        'courses'   => ['Courses', 'courses.php', 'book'],
        'timetable' => ['Timetable', 'timetable.php', 'calendar'],
        'materials' => ['Study materials', 'materials.php', 'file'],
        'profile'   => ['My profile', 'profile.php', 'user'],
    ];
}

function page_head(string $title, string $bodyClass = ''): void
{
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(app_name()) ?></title>
  <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function render_flashes(): void
{
    foreach (take_flashes() as $f) {
        $type = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="flash flash-' . $type . '" role="status">' . icon($type === 'error' ? 'x' : 'check')
            . '<span>' . e($f['message']) . '</span></div>';
    }
}

function layout_start(string $title, string $active, string $subtitle = ''): void
{
    $u = require_login();
    page_head($title);
    $initials = strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1));
    ?>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php">
      <img src="assets/img/logo.svg" alt="" width="34" height="34">
      <span><?= e(app_name()) ?></span>
    </a>
    <nav class="nav" aria-label="Main">
      <?php foreach (nav_items($u['role']) as $key => [$label, $href, $ic]): ?>
        <a href="<?= e($href) ?>" class="<?= $key === $active ? 'is-active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
          <?= icon($ic) ?><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="me">
      <span class="avatar"><?= e($initials) ?></span>
      <span class="me-text">
        <b><?= e($u['first_name'] . ' ' . $u['last_name']) ?></b>
        <small><?= e($u['reg_number']) ?> · <?= $u['role'] === 'admin' ? 'Administrator' : 'Student' ?></small>
      </span>
      <a class="icon-btn" href="logout.php" title="Sign out" aria-label="Sign out"><?= icon('logout') ?></a>
    </div>
  </aside>
  <div class="scrim" data-close-nav></div>

  <main class="main">
    <header class="topbar">
      <button class="icon-btn nav-toggle" type="button" aria-label="Open menu" aria-controls="sidebar" data-open-nav><?= icon('menu') ?></button>
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle): ?><p class="subtitle"><?= e($subtitle) ?></p><?php endif; ?>
      </div>
    </header>
    <div class="content">
      <?php render_flashes(); ?>
<?php
}

function layout_end(): void
{
    ?>
    </div>
    <footer class="foot"><?= e(app_name()) ?> &copy; <?= date('Y') ?></footer>
  </main>
</div>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
<?php
}
