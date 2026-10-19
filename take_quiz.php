<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/quiz.php';
$u = require_login();
if ($u['role'] === 'admin') {
    flash('info', 'Quizzes are taken from a student account. You can review questions here.');
    redirect('questions.php');
}
$uid = (int) $u['id'];
close_expired_attempts($uid);

/** Load an attempt that belongs to this student, with its course. */
function load_attempt(int $id, int $uid): ?array
{
    $a = q('SELECT a.*, c.code, c.title, c.quiz_minutes FROM attempts a JOIN courses c ON c.id = a.course_id
            WHERE a.id = ? AND a.user_id = ?', [$id, $uid])->fetch();
    return $a ?: null;
}

// ---------- POST: start / autosave / submit ----------
if (is_post()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'autosave') {
        header('Content-Type: application/json');
        $sent = $_POST['csrf'] ?? '';
        $a = load_attempt(int_param($_POST, 'attempt'), $uid);
        if (!is_string($sent) || !hash_equals(csrf_token(), $sent) || !$a || $a['submitted_at']) {
            http_response_code(409);
            exit(json_encode(['ok' => false]));
        }
        if (time() <= strtotime($a['deadline_at']) + QUIZ_GRACE_SECONDS) {
            save_answers((int) $a['id'], (array) ($_POST['ans'] ?? []));
        }
        exit(json_encode(['ok' => true]));
    }

    csrf_check();

    if ($action === 'start') {
        $courseId = int_param($_POST, 'course');
        $course = q('SELECT * FROM courses WHERE id = ?', [$courseId])->fetch();
        if (!$course) redirect('quizzes.php');

        $open = q('SELECT id FROM attempts WHERE user_id = ? AND course_id = ? AND submitted_at IS NULL', [$uid, $courseId])->fetch();
        if ($open) redirect('take_quiz.php?attempt=' . $open['id']);

        $taken = (int) q('SELECT COUNT(*) FROM attempts WHERE user_id = ? AND course_id = ? AND submitted_at IS NOT NULL', [$uid, $courseId])->fetchColumn();
        if ($course['max_attempts'] > 0 && $taken >= $course['max_attempts']) {
            flash('error', "You've used all {$course['max_attempts']} attempts for {$course['code']}.");
            redirect('quizzes.php');
        }
        $qids = q('SELECT id FROM questions WHERE course_id = ?', [$courseId])->fetchAll(PDO::FETCH_COLUMN);
        if (!$qids) {
            flash('error', "{$course['code']} has no questions yet.");
            redirect('quizzes.php');
        }
        shuffle($qids);

        $pdo = db();
        $pdo->beginTransaction();
        $now = time();
        q('INSERT INTO attempts (user_id, course_id, started_at, deadline_at, total) VALUES (?,?,?,?,?)',
          [$uid, $courseId, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now + $course['quiz_minutes'] * 60), count($qids)]);
        $attemptId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO attempt_answers (attempt_id, question_id, position) VALUES (?,?,?)');
        foreach ($qids as $i => $qid) $ins->execute([$attemptId, $qid, $i + 1]);
        $pdo->commit();
        redirect('take_quiz.php?attempt=' . $attemptId);
    }

    if ($action === 'submit') {
        $a = load_attempt(int_param($_POST, 'attempt'), $uid);
        if (!$a) redirect('quizzes.php');
        if ($a['submitted_at']) redirect('result.php?id=' . $a['id']);

        $late = time() > strtotime($a['deadline_at']) + QUIZ_GRACE_SECONDS;
        // Answers sent after the grace period are ignored; only autosaved ones count.
        finalize_attempt((int) $a['id'], $late ? null : (array) ($_POST['ans'] ?? []), $late);
        flash($late ? 'info' : 'success', $late
            ? 'Time ran out before you submitted, so only the answers saved in time were marked.'
            : 'Quiz submitted.');
        redirect('result.php?id=' . $a['id']);
    }
    redirect('quizzes.php');
}

// ---------- GET ?attempt= : the quiz itself ----------
if ($attemptId = int_param($_GET, 'attempt')) {
    $a = load_attempt($attemptId, $uid);
    if (!$a) redirect('quizzes.php');
    if ($a['submitted_at']) redirect('result.php?id=' . $a['id']);

    $items = q('SELECT aa.position, aa.chosen_option, qq.id, qq.question, qq.option_a, qq.option_b, qq.option_c, qq.option_d
                FROM attempt_answers aa JOIN questions qq ON qq.id = aa.question_id
                WHERE aa.attempt_id = ? ORDER BY aa.position', [$a['id']])->fetchAll();
    $deadlineMs = strtotime($a['deadline_at']) * 1000;
    $answered   = count(array_filter($items, fn($i) => $i['chosen_option'] !== null));

    page_head($a['code'] . ' quiz', 'exam');
    ?>
<header class="exam-bar">
  <div class="exam-title"><img src="assets/img/logo.svg" alt="" width="30" height="30"><span><b><?= e($a['code']) ?></b> <?= e($a['title']) ?></span></div>
  <div class="exam-meta">
    <span class="progress-text"><b data-answered><?= $answered ?></b> of <?= count($items) ?> answered</span>
    <span class="timer" data-deadline="<?= $deadlineMs ?>" data-now="<?= time() * 1000 ?>" role="timer" aria-live="off"><?= icon('clock') ?><b>--:--</b></span>
  </div>
</header>

<form method="post" class="exam-form" id="quiz-form" data-attempt="<?= (int) $a['id'] ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="submit">
  <input type="hidden" name="attempt" value="<?= (int) $a['id'] ?>">

  <nav class="palette" aria-label="Jump to question">
    <?php foreach ($items as $i => $it): ?>
      <button type="button" class="pal<?= $it['chosen_option'] ? ' is-answered' : '' ?>" data-goto="<?= $i ?>" aria-label="Question <?= $it['position'] ?>"><?= $it['position'] ?></button>
    <?php endforeach; ?>
  </nav>

  <div class="questions">
    <?php foreach ($items as $i => $it): ?>
      <fieldset class="question" data-q="<?= $i ?>" id="q<?= $it['position'] ?>">
        <span class="qbig" aria-hidden="true"><?= $it['position'] ?></span>
        <legend><span class="qnum">Question <?= $it['position'] ?> of <?= count($items) ?></span><?= nl2br(e($it['question'])) ?></legend>
        <div class="choices">
          <?php foreach (['A', 'B', 'C', 'D'] as $l): ?>
            <label class="choice">
              <input type="radio" name="ans[<?= (int) $it['id'] ?>]" value="<?= $l ?>"<?= $it['chosen_option'] === $l ? ' checked' : '' ?>>
              <span class="bubble"><?= $l ?></span>
              <span class="choice-text"><?= e($it['option_' . strtolower($l)]) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div>

  <div class="exam-nav">
    <button type="button" class="btn" data-prev>Previous</button>
    <button type="button" class="btn btn-primary" data-next>Next question</button>
    <button type="submit" class="btn btn-primary" data-submit>Submit quiz</button>
  </div>
  <p class="exam-note muted">Answers save automatically. When the timer reaches zero, the quiz submits itself.</p>
</form>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
<?php
    exit;
}

// ---------- GET ?course= : start screen ----------
$courseId = int_param($_GET, 'course');
$course = q('SELECT c.*, (SELECT COUNT(*) FROM questions qq WHERE qq.course_id = c.id) AS qcount FROM courses c WHERE c.id = ?', [$courseId])->fetch();
if (!$course) redirect('quizzes.php');

$open = q('SELECT id FROM attempts WHERE user_id = ? AND course_id = ? AND submitted_at IS NULL', [$uid, $courseId])->fetch();
if ($open) redirect('take_quiz.php?attempt=' . $open['id']);

$taken = (int) q('SELECT COUNT(*) FROM attempts WHERE user_id = ? AND course_id = ? AND submitted_at IS NOT NULL', [$uid, $courseId])->fetchColumn();
$left  = $course['max_attempts'] == 0 ? null : max(0, $course['max_attempts'] - $taken);

layout_start($course['code'] . ' quiz', 'quizzes', $course['title']);
?>
<section class="panel start">
  <h2>Before you start</h2>
  <ul class="facts facts-lg">
    <li><?= icon('list') ?><span><b><?= (int) $course['qcount'] ?> questions</b>, each with four options. Fill one bubble per question.</span></li>
    <li><?= icon('clock') ?><span><b><?= (int) $course['quiz_minutes'] ?> minutes</b> from the moment you press Start. The timer keeps running if you leave the page.</span></li>
    <li><?= icon('quiz') ?><span><?= $left === null ? '<b>Unlimited attempts.</b> Your best score is kept.' : '<b>' . $left . ' of ' . (int) $course['max_attempts'] . ' attempts left.</b> Your best score is kept.' ?></span></li>
  </ul>
  <?php if ($course['qcount'] == 0): ?>
    <p class="empty">This course has no questions yet.</p>
  <?php elseif ($left === 0): ?>
    <p class="empty">You've used all your attempts for this quiz. <a href="results.php?course=<?= (int) $course['id'] ?>">See your results</a></p>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="course" value="<?= (int) $course['id'] ?>">
      <button class="btn btn-primary btn-lg" type="submit">Start the quiz</button>
      <a class="btn btn-ghost" href="quizzes.php">Not now</a>
    </form>
  <?php endif; ?>
</section>
<?php
layout_end();
