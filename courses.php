<?php
require __DIR__ . '/includes/init.php';
$u = require_login();

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$errors = [];
$form = ['id' => 0, 'code' => '', 'title' => '', 'lecturer' => '', 'class_day' => 'Monday',
         'start_time' => '08:00', 'end_time' => '10:00', 'venue' => '', 'quiz_minutes' => 10, 'max_attempts' => 3];

if (is_post()) {
    require_admin();
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = int_param($_POST, 'id');
        q('DELETE FROM courses WHERE id = ?', [$id]);
        flash('success', 'Course deleted, along with its questions, materials and results.');
        redirect('courses.php');
    }

    if ($action === 'save') {
        $form = [
            'id'           => int_param($_POST, 'id'),
            'code'         => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'title'        => trim((string) ($_POST['title'] ?? '')),
            'lecturer'     => trim((string) ($_POST['lecturer'] ?? '')),
            'class_day'    => (string) ($_POST['class_day'] ?? ''),
            'start_time'   => (string) ($_POST['start_time'] ?? ''),
            'end_time'     => (string) ($_POST['end_time'] ?? ''),
            'venue'        => trim((string) ($_POST['venue'] ?? '')),
            'quiz_minutes' => (int) ($_POST['quiz_minutes'] ?? 0),
            'max_attempts' => (int) ($_POST['max_attempts'] ?? 0),
        ];
        if (!preg_match('/^[A-Z0-9 \-]{2,20}$/', $form['code'])) $errors[] = 'Course code should be 2–20 letters or numbers, like CSC101.';
        if ($form['title'] === '' || mb_strlen($form['title']) > 150) $errors[] = 'Enter a course title (up to 150 characters).';
        if (!in_array($form['class_day'], DAYS, true)) $errors[] = 'Choose the day the class holds.';
        if (!preg_match('/^\d{2}:\d{2}/', $form['start_time']) || !preg_match('/^\d{2}:\d{2}/', $form['end_time'])) $errors[] = 'Enter start and end times.';
        elseif ($form['end_time'] <= $form['start_time']) $errors[] = 'The class must end after it starts.';
        if ($form['quiz_minutes'] < 1 || $form['quiz_minutes'] > 300) $errors[] = 'Quiz time should be between 1 and 300 minutes.';
        if ($form['max_attempts'] < 0 || $form['max_attempts'] > 50) $errors[] = 'Attempts allowed should be between 0 (unlimited) and 50.';

        $dupe = q('SELECT id FROM courses WHERE code = ? AND id <> ?', [$form['code'], $form['id']])->fetch();
        if ($dupe) $errors[] = "A course with the code {$form['code']} already exists.";

        if (!$errors) {
            $params = [$form['code'], $form['title'], $form['lecturer'], $form['class_day'], $form['start_time'],
                       $form['end_time'], $form['venue'], $form['quiz_minutes'], $form['max_attempts']];
            if ($form['id']) {
                q('UPDATE courses SET code=?, title=?, lecturer=?, class_day=?, start_time=?, end_time=?, venue=?, quiz_minutes=?, max_attempts=? WHERE id=?',
                  array_merge($params, [$form['id']]));
                flash('success', "{$form['code']} updated.");
            } else {
                q('INSERT INTO courses (code, title, lecturer, class_day, start_time, end_time, venue, quiz_minutes, max_attempts) VALUES (?,?,?,?,?,?,?,?,?)', $params);
                flash('success', "{$form['code']} added.");
            }
            redirect('courses.php');
        }
    }
}

if (!is_post() && is_admin() && ($editId = int_param($_GET, 'edit'))) {
    $row = q('SELECT * FROM courses WHERE id = ?', [$editId])->fetch();
    if ($row) {
        $form = $row;
        $form['start_time'] = substr($row['start_time'], 0, 5);
        $form['end_time']   = substr($row['end_time'], 0, 5);
    }
}

$courses = q('SELECT c.*,
                (SELECT COUNT(*) FROM questions qq WHERE qq.course_id = c.id) AS qcount,
                (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) AS mcount
              FROM courses c ORDER BY c.code')->fetchAll();

layout_start('Courses', 'courses', count($courses) . ' course' . (count($courses) === 1 ? '' : 's') . ' this semester');
?>
<section class="panel">
  <?php if (!$courses): ?>
    <p class="empty">No courses yet.<?= is_admin() ? ' Add the first one below.' : '' ?></p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Code</th><th>Title</th><th>Lecturer</th><th>Class</th><th class="num">Questions</th><th class="num">Materials</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($courses as $c): ?>
        <tr>
          <td><b><?= e($c['code']) ?></b></td>
          <td><?= e($c['title']) ?></td>
          <td><?= e($c['lecturer']) ?></td>
          <td class="muted"><?= e(substr($c['class_day'], 0, 3)) ?> <?= e(fmt_time($c['start_time'])) ?><?= $c['venue'] ? ', ' . e($c['venue']) : '' ?></td>
          <td class="num"><?= (int) $c['qcount'] ?></td>
          <td class="num"><?= (int) $c['mcount'] ?></td>
          <?php if (is_admin()): ?>
            <td class="actions">
              <a class="icon-btn" href="courses.php?edit=<?= (int) $c['id'] ?>#add" title="Edit" aria-label="Edit <?= e($c['code']) ?>"><?= icon('edit') ?></a>
              <form method="post" data-confirm="Delete <?= e($c['code']) ?>? Its questions, materials and every student result for it will be removed too.">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="icon-btn danger" type="submit" title="Delete" aria-label="Delete <?= e($c['code']) ?>"><?= icon('trash') ?></button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<?php if (is_admin()): ?>
<section class="panel" id="add">
  <div class="panel-head"><h2><?= $form['id'] ? 'Edit ' . e($form['code']) : 'Add a course' ?></h2>
    <?php if ($form['id']): ?><a class="link" href="courses.php">Cancel editing</a><?php endif; ?></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('x') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <label class="field"><span>Course code</span><input name="code" value="<?= e($form['code']) ?>" placeholder="CSC101" required maxlength="20"></label>
    <label class="field span-2"><span>Title</span><input name="title" value="<?= e($form['title']) ?>" placeholder="Introduction to Computing" required maxlength="150"></label>
    <label class="field"><span>Lecturer</span><input name="lecturer" value="<?= e($form['lecturer']) ?>" maxlength="120"></label>
    <label class="field"><span>Class day</span>
      <select name="class_day"><?php foreach (DAYS as $d): ?><option<?= $d === $form['class_day'] ? ' selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Venue</span><input name="venue" value="<?= e($form['venue']) ?>" maxlength="100"></label>
    <label class="field"><span>Starts</span><input type="time" name="start_time" value="<?= e($form['start_time']) ?>" required></label>
    <label class="field"><span>Ends</span><input type="time" name="end_time" value="<?= e($form['end_time']) ?>" required></label>
    <label class="field"><span>Quiz time (minutes)</span><input type="number" name="quiz_minutes" min="1" max="300" value="<?= (int) $form['quiz_minutes'] ?>" required></label>
    <label class="field"><span>Attempts allowed</span><input type="number" name="max_attempts" min="0" max="50" value="<?= (int) $form['max_attempts'] ?>" required><small>0 means unlimited</small></label>
    <div class="form-actions span-3"><button class="btn btn-primary" type="submit"><?= $form['id'] ? 'Save changes' : 'Add course' ?></button></div>
  </form>
</section>
<?php endif; ?>
<?php
layout_end();
