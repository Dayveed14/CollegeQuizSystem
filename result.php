<?php
require __DIR__ . '/includes/init.php';
$u = require_login();

$id = int_param($_GET, 'id');
$a = q('SELECT a.*, c.code, c.title, us.first_name, us.last_name, us.reg_number
        FROM attempts a JOIN courses c ON c.id = a.course_id JOIN users us ON us.id = a.user_id
        WHERE a.id = ?', [$id])->fetch();

if (!$a || (!is_admin() && (int) $a['user_id'] !== (int) $u['id'])) {
    flash('error', 'That result could not be found.');
    redirect('results.php');
}
if (!$a['submitted_at']) {
    redirect(is_admin() ? 'results.php' : 'take_quiz.php?attempt=' . $a['id']);
}

$items = q('SELECT aa.position, aa.chosen_option, aa.is_correct, qq.question, qq.option_a, qq.option_b, qq.option_c, qq.option_d, qq.correct_option
            FROM attempt_answers aa JOIN questions qq ON qq.id = aa.question_id
            WHERE aa.attempt_id = ? ORDER BY aa.position', [$a['id']])->fetchAll();

$pct     = percent((int) $a['score'], (int) $a['total']);
$pass    = $pct >= 50;
$skipped = count(array_filter($items, fn($i) => $i['chosen_option'] === null));
$minutes = max(1, (int) round((strtotime($a['submitted_at']) - strtotime($a['started_at'])) / 60));

$who = is_admin() ? $a['first_name'] . ' ' . $a['last_name'] . ' (' . $a['reg_number'] . ')' : '';
layout_start($a['code'] . ' result', is_admin() ? 'results' : 'results', trim($a['title'] . ($who ? ' — ' . $who : '')));
?>
<section class="panel result-head">
  <div class="mark mark-<?= $pass ? 'pass' : 'fail' ?>" aria-label="Score <?= $pct ?> percent">
    <b><?= $pct ?><small>%</small></b>
    <span>Grade <?= grade_for($pct) ?></span>
  </div>
  <div>
    <h2><?= $pass ? 'Passed' : 'Not passed yet' ?></h2>
    <p class="lead"><?= (int) $a['score'] ?> of <?= (int) $a['total'] ?> correct<?= $skipped ? ", {$skipped} left blank" : '' ?>.</p>
    <p class="muted">Submitted <?= e(fmt_date($a['submitted_at'])) ?> after <?= $minutes ?> minute<?= $minutes === 1 ? '' : 's' ?><?= $a['late'] ? '. Time ran out before submission.' : '.' ?></p>
    <div class="quick">
      <a class="btn" href="results.php"><?= is_admin() ? 'All results' : 'My results' ?></a>
      <?php if (!is_admin()): ?><a class="btn" href="quizzes.php">Back to quizzes</a><?php endif; ?>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><h2>Answer review</h2></div>
  <ol class="review">
    <?php foreach ($items as $it): ?>
      <li class="<?= $it['is_correct'] ? 'is-right' : 'is-wrong' ?>">
        <p class="review-q"><span class="tick" aria-label="<?= $it['is_correct'] ? 'Correct' : 'Incorrect' ?>"><?= icon($it['is_correct'] ? 'check' : 'x') ?></span><?= nl2br(e($it['question'])) ?></p>
        <ul class="opts">
          <?php foreach (['A', 'B', 'C', 'D'] as $l):
              $chosen = $it['chosen_option'] === $l;
              $right  = $it['correct_option'] === $l; ?>
            <li class="<?= $right ? 'is-correct' : '' ?><?= $chosen && !$right ? ' is-picked-wrong' : '' ?>">
              <span class="bubble bubble-sm<?= $chosen ? ' is-filled' : '' ?>"><?= $l ?></span>
              <?= e($it['option_' . strtolower($l)]) ?>
              <?php if ($right): ?><em>Correct answer</em><?php elseif ($chosen): ?><em>Your answer</em><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($it['chosen_option'] === null): ?><p class="muted small">Left blank</p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
<?php
layout_end();
