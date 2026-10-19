<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/quiz.php';
$u = require_login();
$admin = is_admin();

if (is_post()) {
    require_admin();
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM attempts WHERE id = ?', [int_param($_POST, 'id')]);
        flash('success', 'Result deleted. The student gets that attempt back.');
    }
    redirect('results.php' . (isset($_POST['back']) ? '?' . preg_replace('/[^\w=&%+\-.]/', '', (string) $_POST['back']) : ''));
}

if (!$admin) close_expired_attempts((int) $u['id']);

$courseId = int_param($_GET, 'course');
$search   = trim((string) ($_GET['q'] ?? ''));

$where  = ['a.submitted_at IS NOT NULL'];
$params = [];
if (!$admin) { $where[] = 'a.user_id = ?'; $params[] = $u['id']; }
if ($courseId) { $where[] = 'a.course_id = ?'; $params[] = $courseId; }
if ($admin && $search !== '') {
    $where[] = '(us.reg_number LIKE ? OR CONCAT_WS(CHAR(32), us.first_name, us.last_name) LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$rows = q('SELECT a.id, a.score, a.total, a.started_at, a.submitted_at, a.late, c.code, c.title,
                  us.first_name, us.last_name, us.reg_number
           FROM attempts a JOIN courses c ON c.id = a.course_id JOIN users us ON us.id = a.user_id
           WHERE ' . implode(' AND ', $where) . ' ORDER BY a.submitted_at DESC', $params)->fetchAll();

// CSV export for admins
if ($admin && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quiz-results-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 correctly
    fputcsv($out, ['Reg number', 'Student', 'Course', 'Score', 'Total', 'Percent', 'Grade', 'Submitted', 'Late']);
    foreach ($rows as $r) {
        $pct = percent((int) $r['score'], (int) $r['total']);
        fputcsv($out, [$r['reg_number'], $r['first_name'] . ' ' . $r['last_name'], $r['code'], $r['score'], $r['total'], $pct, grade_for($pct), $r['submitted_at'], $r['late'] ? 'yes' : 'no']);
    }
    exit;
}

$courses = q('SELECT id, code, title FROM courses ORDER BY code')->fetchAll();
$qs = http_build_query(array_filter(['course' => $courseId ?: null, 'q' => $search ?: null]));

layout_start($admin ? 'Results' : 'My results', 'results', count($rows) . ' submission' . (count($rows) === 1 ? '' : 's'));
?>
<section class="panel">
  <form method="get" class="filter">
    <label class="field field-inline"><span>Course</span>
      <select name="course" data-autosubmit>
        <option value="">All courses</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $courseId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?>
      </select></label>
    <?php if ($admin): ?>
      <label class="field field-inline"><span>Student</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name or reg number"></label>
      <button class="btn btn-sm" type="submit">Search</button>
      <a class="btn btn-sm btn-ghost push" href="results.php?<?= e($qs . ($qs ? '&' : '') . 'export=csv') ?>"><?= icon('download') ?>Export CSV</a>
    <?php else: ?>
      <noscript><button class="btn btn-sm">Filter</button></noscript>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <p class="empty"><?= $admin ? 'No results match. Try another course or clear the search.' : 'No results yet. Take a quiz and your score will appear here.' ?>
      <?php if (!$admin): ?><a href="quizzes.php">Go to quizzes</a><?php endif; ?></p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr>
        <?php if ($admin): ?><th>Student</th><?php endif; ?>
        <th>Course</th><th class="num">Score</th><th class="num">Grade</th><th>Submitted</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $pct = percent((int) $r['score'], (int) $r['total']); ?>
        <tr>
          <?php if ($admin): ?><td><?= e($r['first_name'] . ' ' . $r['last_name']) ?><small class="block muted"><?= e($r['reg_number']) ?></small></td><?php endif; ?>
          <td><b><?= e($r['code']) ?></b> <span class="muted hide-sm"><?= e($r['title']) ?></span></td>
          <td class="num"><?= (int) $r['score'] ?>/<?= (int) $r['total'] ?> <span class="score score-<?= $pct >= 50 ? 'pass' : 'fail' ?>"><?= $pct ?>%</span></td>
          <td class="num"><?= grade_for($pct) ?></td>
          <td class="muted"><?= e(fmt_date($r['submitted_at'])) ?><?= $r['late'] ? ' <span class="tag">timed out</span>' : '' ?></td>
          <td class="actions">
            <a class="icon-btn" href="result.php?id=<?= (int) $r['id'] ?>" title="Review answers" aria-label="Review answers"><?= icon('eye') ?></a>
            <?php if ($admin): ?>
              <form method="post" data-confirm="Delete this result? The student will get the attempt back.">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($qs) ?>">
                <button class="icon-btn danger" type="submit" title="Delete result" aria-label="Delete result"><?= icon('trash') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php
layout_end();
