<?php
require __DIR__ . '/includes/init.php';
require_login();

$days  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$today = date('l');
$byDay = array_fill_keys($days, []);
foreach (q('SELECT code, title, lecturer, class_day, start_time, end_time, venue FROM courses ORDER BY start_time, code')->fetchAll() as $c) {
    $byDay[$c['class_day']][] = $c;
}

layout_start('Timetable', 'timetable', 'Weekly lecture schedule');
?>
<div class="week">
  <?php foreach ($days as $d): ?>
    <section class="day<?= $d === $today ? ' is-today' : '' ?>">
      <h2><?= e($d) ?><?php if ($d === $today): ?> <small>Today</small><?php endif; ?></h2>
      <?php if (!$byDay[$d]): ?>
        <p class="day-empty">No classes</p>
      <?php else: foreach ($byDay[$d] as $c): ?>
        <article class="slot">
          <span class="time"><?= e(fmt_time($c['start_time'])) ?> – <?= e(fmt_time($c['end_time'])) ?></span>
          <b><?= e($c['code']) ?></b>
          <span><?= e($c['title']) ?></span>
          <small class="muted"><?= e(trim($c['lecturer'] . ($c['venue'] ? ', ' . $c['venue'] : ''), ', ')) ?></small>
        </article>
      <?php endforeach; endif; ?>
    </section>
  <?php endforeach; ?>
</div>
<?php if (is_admin()): ?><p class="muted">The timetable is built from each course's day and time. Change them on the <a href="courses.php">Courses</a> page.</p><?php endif; ?>
<?php
layout_end();
