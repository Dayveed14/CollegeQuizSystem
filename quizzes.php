<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/quiz.php';
$u = require_login();
if ($u['role'] === 'admin') redirect('questions.php');

close_expired_attempts((int) $u['id']);

$rows = q("SELECT c.id, c.code, c.title, c.lecturer, c.quiz_minutes, c.max_attempts,
             (SELECT COUNT(*) FROM questions qq WHERE qq.course_id = c.id) AS qcount,
             (SELECT COUNT(*) FROM attempts a WHERE a.course_id = c.id AND a.user_id = :u1 AND a.submitted_at IS NOT NULL) AS taken,
             (SELECT MAX(ROUND(a.score * 100 / NULLIF(a.total, 0))) FROM attempts a WHERE a.course_id = c.id AND a.user_id = :u2 AND a.submitted_at IS NOT NULL) AS best,
             (SELECT a.id FROM attempts a WHERE a.course_id = c.id AND a.user_id = :u3 AND a.submitted_at IS NULL LIMIT 1) AS open_attempt
           FROM courses c ORDER BY c.code", ['u1' => $u['id'], 'u2' => $u['id'], 'u3' => $u['id']])->fetchAll();

layout_start('Quizzes', 'quizzes', 'One quiz per course. Your best score counts.');
?>
<?php if (!$rows): ?>
  <section class="panel"><p class="empty">There are no courses yet, so there's nothing to take.</p></section>
<?php else: ?>
<div class="quiz-grid">
  <?php foreach ($rows as $c):
      $left = $c['max_attempts'] == 0 ? null : max(0, $c['max_attempts'] - $c['taken']);
      $canTake = $c['qcount'] > 0 && ($left === null || $left > 0); ?>
    <article class="quiz-card<?= $canTake || $c['open_attempt'] ? '' : ' is-done' ?>">
      <header>
        <b class="code"><?= e($c['code']) ?></b>
        <?php if ($c['best'] !== null): ?><span class="score score-<?= $c['best'] >= 50 ? 'pass' : 'fail' ?>" title="Your best score"><?= (int) $c['best'] ?>%</span><?php endif; ?>
      </header>
      <h2><?= e($c['title']) ?></h2>
      <p class="muted"><?= e($c['lecturer']) ?></p>
      <ul class="facts">
        <li><?= icon('list') ?><?= (int) $c['qcount'] ?> questions</li>
        <li><?= icon('clock') ?><?= (int) $c['quiz_minutes'] ?> minutes</li>
        <li><?= icon('quiz') ?><?= $left === null ? 'Unlimited attempts' : $left . ' of ' . (int) $c['max_attempts'] . ' attempts left' ?></li>
      </ul>
      <footer>
        <?php if ($c['open_attempt']): ?>
          <a class="btn btn-primary" href="take_quiz.php?attempt=<?= (int) $c['open_attempt'] ?>">Continue quiz</a>
        <?php elseif ($c['qcount'] == 0): ?>
          <span class="muted">No questions yet</span>
        <?php elseif ($canTake): ?>
          <a class="btn btn-primary" href="take_quiz.php?course=<?= (int) $c['id'] ?>"><?= $c['taken'] ? 'Retake quiz' : 'Start quiz' ?></a>
        <?php else: ?>
          <span class="muted">No attempts left</span>
        <?php endif; ?>
        <?php if ($c['taken']): ?><a class="link" href="results.php?course=<?= (int) $c['id'] ?>">My results</a><?php endif; ?>
      </footer>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
layout_end();
