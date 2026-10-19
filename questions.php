<?php
require __DIR__ . '/includes/init.php';
require_admin();

$courses = q('SELECT id, code, title, quiz_minutes FROM courses ORDER BY code')->fetchAll();
$courseId = int_param($_GET, 'course') ?: int_param($_POST, 'course_id') ?: (int) ($courses[0]['id'] ?? 0);
$course = null;
foreach ($courses as $c) if ((int) $c['id'] === $courseId) $course = $c;

$errors = [];
$form = ['id' => 0, 'question' => '', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'correct_option' => 'A'];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        q('DELETE FROM questions WHERE id = ? AND course_id = ?', [int_param($_POST, 'id'), $courseId]);
        flash('success', 'Question deleted.');
        redirect('questions.php?course=' . $courseId);
    }
    if ($action === 'save' && $course) {
        $form['id'] = int_param($_POST, 'id');
        foreach (['question', 'option_a', 'option_b', 'option_c', 'option_d'] as $k) {
            $form[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        $form['correct_option'] = strtoupper((string) ($_POST['correct_option'] ?? ''));

        if ($form['question'] === '') $errors[] = 'Write the question.';
        foreach (['a', 'b', 'c', 'd'] as $l) {
            $v = $form['option_' . $l];
            if ($v === '') $errors[] = 'Fill in option ' . strtoupper($l) . '.';
            elseif (mb_strlen($v) > 255) $errors[] = 'Option ' . strtoupper($l) . ' is longer than 255 characters.';
        }
        if (!in_array($form['correct_option'], ['A', 'B', 'C', 'D'], true)) $errors[] = 'Mark which option is correct.';
        $opts = array_map('mb_strtolower', [$form['option_a'], $form['option_b'], $form['option_c'], $form['option_d']]);
        if (!$errors && count(array_unique($opts)) < 4) $errors[] = 'Each option must be different.';

        if (!$errors) {
            $p = [$form['question'], $form['option_a'], $form['option_b'], $form['option_c'], $form['option_d'], $form['correct_option']];
            if ($form['id']) {
                q('UPDATE questions SET question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=? AND course_id=?',
                  array_merge($p, [$form['id'], $courseId]));
                flash('success', 'Question updated.');
            } else {
                q('INSERT INTO questions (question, option_a, option_b, option_c, option_d, correct_option, course_id) VALUES (?,?,?,?,?,?,?)',
                  array_merge($p, [$courseId]));
                flash('success', 'Question added to ' . $course['code'] . '.');
            }
            redirect('questions.php?course=' . $courseId . '#add');
        }
    }
}

if (!is_post() && ($editId = int_param($_GET, 'edit'))) {
    $row = q('SELECT * FROM questions WHERE id = ? AND course_id = ?', [$editId, $courseId])->fetch();
    if ($row) $form = $row;
}

$questions = $course ? q('SELECT * FROM questions WHERE course_id = ? ORDER BY id', [$courseId])->fetchAll() : [];

layout_start('Question bank', 'questions', $course ? $course['code'] . ' · ' . count($questions) . ' question' . (count($questions) === 1 ? '' : 's') : '');

if (!$courses): ?>
  <section class="panel"><p class="empty">Add a course first, then you can write its quiz questions. <a href="courses.php#add">Add a course</a></p></section>
<?php layout_end(); exit; endif; ?>

<section class="panel">
  <form method="get" class="filter">
    <label class="field field-inline"><span>Course</span>
      <select name="course" data-autosubmit>
        <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $courseId ? ' selected' : '' ?>><?= e($c['code'] . ' — ' . $c['title']) ?></option><?php endforeach; ?>
      </select></label>
    <noscript><button class="btn btn-sm">Show</button></noscript>
    <span class="muted">Students get these in random order, with <?= (int) $course['quiz_minutes'] ?> minutes to finish.</span>
  </form>

  <?php if (!$questions): ?>
    <p class="empty">No questions for <?= e($course['code']) ?> yet. Add the first one below.</p>
  <?php else: ?>
    <ol class="qlist">
      <?php foreach ($questions as $qq): ?>
        <li>
          <div class="qlist-body">
            <p><?= nl2br(e($qq['question'])) ?></p>
            <ul class="opts">
              <?php foreach (['A', 'B', 'C', 'D'] as $l): ?>
                <li class="<?= $qq['correct_option'] === $l ? 'is-correct' : '' ?>"><span class="bubble bubble-sm<?= $qq['correct_option'] === $l ? ' is-filled' : '' ?>"><?= $l ?></span><?= e($qq['option_' . strtolower($l)]) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="actions">
            <a class="icon-btn" href="questions.php?course=<?= $courseId ?>&amp;edit=<?= (int) $qq['id'] ?>#add" title="Edit" aria-label="Edit question"><?= icon('edit') ?></a>
            <form method="post" data-confirm="Delete this question? Past results that used it will lose it too.">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $qq['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>">
              <button class="icon-btn danger" type="submit" title="Delete" aria-label="Delete question"><?= icon('trash') ?></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<section class="panel" id="add">
  <div class="panel-head"><h2><?= $form['id'] ? 'Edit question' : 'Add a question to ' . e($course['code']) ?></h2>
    <?php if ($form['id']): ?><a class="link" href="questions.php?course=<?= $courseId ?>">Cancel editing</a><?php endif; ?></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('x') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>">
    <label class="field"><span>Question</span><textarea name="question" rows="3" required><?= e($form['question']) ?></textarea></label>
    <fieldset class="opt-edit">
      <legend>Options — select the bubble next to the correct answer</legend>
      <?php foreach (['A', 'B', 'C', 'D'] as $l): $k = 'option_' . strtolower($l); ?>
        <div class="opt-row">
          <label class="bubble-pick" title="Mark <?= $l ?> as correct">
            <input type="radio" name="correct_option" value="<?= $l ?>"<?= $form['correct_option'] === $l ? ' checked' : '' ?>>
            <span class="bubble"><?= $l ?></span>
          </label>
          <input name="<?= $k ?>" value="<?= e($form[$k]) ?>" aria-label="Option <?= $l ?>" maxlength="255" required>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= $form['id'] ? 'Save changes' : 'Add question' ?></button></div>
  </form>
</section>
<?php
layout_end();
