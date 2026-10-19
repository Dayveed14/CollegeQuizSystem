<?php
require __DIR__ . '/includes/init.php';
$u = require_login();

$today = date('l');

if ($u['role'] === 'admin') {
    $stats = q("SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'student') AS students,
        (SELECT COUNT(*) FROM courses) AS courses,
        (SELECT COUNT(*) FROM questions) AS questions,
        (SELECT COUNT(*) FROM attempts WHERE submitted_at IS NOT NULL) AS attempts,
        (SELECT ROUND(AVG(score * 100 / NULLIF(total, 0))) FROM attempts WHERE submitted_at IS NOT NULL) AS avg_pct")->fetch();

    $recent = q("SELECT a.id, a.score, a.total, a.submitted_at, c.code, us.first_name, us.last_name, us.reg_number
                 FROM attempts a
                 JOIN courses c ON c.id = a.course_id
                 JOIN users us ON us.id = a.user_id
                 WHERE a.submitted_at IS NOT NULL
                 ORDER BY a.submitted_at DESC LIMIT 8")->fetchAll();

    $empty = q("SELECT c.id, c.code, c.title FROM courses c
                LEFT JOIN questions qq ON qq.course_id = c.id
                GROUP BY c.id HAVING COUNT(qq.id) = 0 ORDER BY c.code")->fetchAll();

    layout_start('Dashboard', 'dashboard', 'Good to see you, ' . $u['first_name'] . '.');
    ?>
    <div class="stats">
      <div class="stat"><b><?= (int) $stats['students'] ?></b><span>Students</span></div>
      <div class="stat"><b><?= (int) $stats['courses'] ?></b><span>Courses</span></div>
      <div class="stat"><b><?= (int) $stats['questions'] ?></b><span>Questions in the bank</span></div>
      <div class="stat"><b><?= (int) $stats['attempts'] ?></b><span>Quizzes taken</span></div>
      <div class="stat"><b><?= $stats['avg_pct'] === null ? '—' : (int) $stats['avg_pct'] . '%' ?></b><span>Average score</span></div>
    </div>

    <div class="grid-2">
      <section class="panel">
        <div class="panel-head">
          <h2>Latest submissions</h2>
          <a class="link" href="results.php">All results</a>
        </div>
        <?php if (!$recent): ?>
          <p class="empty">No one has taken a quiz yet. Once students submit, their scores appear here.</p>
        <?php else: ?>
          <div class="table-wrap"><table class="table">
            <thead><tr><th>Student</th><th>Course</th><th class="num">Score</th><th>Submitted</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): $pct = percent((int) $r['score'], (int) $r['total']); ?>
              <tr>
                <td><a href="result.php?id=<?= (int) $r['id'] ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a><small class="block muted"><?= e($r['reg_number']) ?></small></td>
                <td><?= e($r['code']) ?></td>
                <td class="num"><span class="score score-<?= $pct >= 50 ? 'pass' : 'fail' ?>"><?= $pct ?>%</span></td>
                <td class="muted"><?= e(fmt_date($r['submitted_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Needs attention</h2></div>
        <?php if (!$empty): ?>
          <p class="empty">Every course has quiz questions. Nothing waiting on you.</p>
        <?php else: ?>
          <p class="muted">These courses have no questions yet, so students can't take their quiz.</p>
          <ul class="list">
            <?php foreach ($empty as $c): ?>
              <li><span><b><?= e($c['code']) ?></b> <?= e($c['title']) ?></span>
                  <a class="btn btn-sm" href="questions.php?course=<?= (int) $c['id'] ?>"><?= icon('plus') ?>Add questions</a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="quick">
          <a class="btn" href="users.php#add"><?= icon('plus') ?>Add a user</a>
          <a class="btn" href="courses.php#add"><?= icon('plus') ?>Add a course</a>
          <a class="btn" href="materials.php#add"><?= icon('plus') ?>Upload material</a>
        </div>
      </section>
    </div>
    <?php
    layout_end();
    exit;
}

// ---------------- Student dashboard ----------------
$quizzes = q("SELECT c.id, c.code, c.title, c.quiz_minutes, c.max_attempts,
                     (SELECT COUNT(*) FROM questions qq WHERE qq.course_id = c.id) AS qcount,
                     (SELECT COUNT(*) FROM attempts a WHERE a.course_id = c.id AND a.user_id = ? AND a.submitted_at IS NOT NULL) AS taken,
                     (SELECT MAX(ROUND(a.score * 100 / NULLIF(a.total, 0))) FROM attempts a WHERE a.course_id = c.id AND a.user_id = ? AND a.submitted_at IS NOT NULL) AS best
              FROM courses c ORDER BY c.code", [$u['id'], $u['id']])->fetchAll();

$todayClasses = q('SELECT code, title, start_time, end_time, venue FROM courses WHERE class_day = ? ORDER BY start_time', [$today])->fetchAll();

$recent = q("SELECT a.id, a.score, a.total, a.submitted_at, c.code, c.title
             FROM attempts a JOIN courses c ON c.id = a.course_id
             WHERE a.user_id = ? AND a.submitted_at IS NOT NULL
             ORDER BY a.submitted_at DESC LIMIT 5", [$u['id']])->fetchAll();

$open = array_filter($quizzes, fn($c) => $c['qcount'] > 0 && ($c['max_attempts'] == 0 || $c['taken'] < $c['max_attempts']));

layout_start('Hello, ' . $u['first_name'], 'dashboard', date('l, j F Y'));
?>
<div class="grid-2">
  <section class="panel">
    <div class="panel-head">
      <h2>Quizzes you can take</h2>
      <a class="link" href="quizzes.php">All quizzes</a>
    </div>
    <?php if (!$open): ?>
      <p class="empty">You're all caught up. New quizzes will show here when your lecturers add them.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($open as $c): ?>
          <li>
            <span><b><?= e($c['code']) ?></b> <?= e($c['title']) ?>
              <small class="block muted"><?= (int) $c['qcount'] ?> questions · <?= (int) $c['quiz_minutes'] ?> min<?= $c['best'] !== null ? ' · best ' . (int) $c['best'] . '%' : '' ?></small></span>
            <a class="btn btn-sm btn-primary" href="take_quiz.php?course=<?= (int) $c['id'] ?>"><?= $c['taken'] ? 'Retake' : 'Start' ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Classes today</h2>
      <a class="link" href="timetable.php">Full timetable</a>
    </div>
    <?php if (!$todayClasses): ?>
      <p class="empty">No classes on <?= e($today) ?>.</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($todayClasses as $c): ?>
          <li><span><b><?= e($c['code']) ?></b> <?= e($c['title']) ?><small class="block muted"><?= e($c['venue']) ?></small></span>
              <span class="time"><?= e(fmt_time($c['start_time'])) ?> – <?= e(fmt_time($c['end_time'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Your recent results</h2>
    <a class="link" href="results.php">All results</a>
  </div>
  <?php if (!$recent): ?>
    <p class="empty">You haven't taken a quiz yet. Pick one above to get started.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Course</th><th class="num">Score</th><th class="num">Grade</th><th>Taken</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): $pct = percent((int) $r['score'], (int) $r['total']); ?>
        <tr>
          <td><b><?= e($r['code']) ?></b> <span class="muted"><?= e($r['title']) ?></span></td>
          <td class="num"><?= (int) $r['score'] ?>/<?= (int) $r['total'] ?> <span class="score score-<?= $pct >= 50 ? 'pass' : 'fail' ?>"><?= $pct ?>%</span></td>
          <td class="num"><?= grade_for($pct) ?></td>
          <td class="muted"><?= e(fmt_date($r['submitted_at'])) ?></td>
          <td class="actions"><a class="link" href="result.php?id=<?= (int) $r['id'] ?>">Review</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php
layout_end();
