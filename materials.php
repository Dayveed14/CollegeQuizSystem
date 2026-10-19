<?php
require __DIR__ . '/includes/init.php';
$u = require_login();

$cfg      = $GLOBALS['config'];
$allowed  = array_map('strtolower', $cfg['allowed_extensions']);
$maxBytes = (int) $cfg['max_upload_mb'] * 1024 * 1024;
$errors   = [];

if (is_post()) {
    require_admin();
    // An upload bigger than post_max_size empties $_POST entirely, including the CSRF token
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', "That file is too large. The limit is {$cfg['max_upload_mb']} MB.");
        redirect('materials.php');
    }
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $m = q('SELECT stored_name FROM materials WHERE id = ?', [int_param($_POST, 'id')])->fetch();
        if ($m) {
            $path = UPLOAD_DIR . '/' . basename($m['stored_name']);
            if (is_file($path)) @unlink($path);
            q('DELETE FROM materials WHERE id = ?', [int_param($_POST, 'id')]);
            flash('success', 'Material deleted.');
        }
        redirect('materials.php');
    }

    if ($action === 'upload') {
        $courseId = int_param($_POST, 'course_id');
        $title    = trim((string) ($_POST['title'] ?? ''));
        $file     = $_FILES['file'] ?? null;

        if (!q('SELECT id FROM courses WHERE id = ?', [$courseId])->fetch()) $errors[] = 'Choose the course this material belongs to.';
        if ($title === '' || mb_strlen($title) > 150) $errors[] = 'Give the material a title (up to 150 characters).';

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Choose a file to upload.';
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > $maxBytes) {
            $errors[] = "That file is too large. The limit is {$cfg['max_upload_mb']} MB.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'The upload didn\'t complete. Try again.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'That file type isn\'t allowed. Use one of: ' . implode(', ', $allowed) . '.';
            }
        }

        if (!$errors) {
            if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;   // random name: never executable, never guessable
            if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
                $original = preg_replace('/[^\w.\- ()]+/u', '_', basename($file['name']));
                q('INSERT INTO materials (course_id, title, original_name, stored_name, file_size, uploaded_by) VALUES (?,?,?,?,?,?)',
                  [$courseId, $title, $original, $stored, (int) $file['size'], $u['id']]);
                flash('success', "\"{$title}\" uploaded.");
                redirect('materials.php');
            }
            $errors[] = 'The server couldn\'t save the file. Check that the uploads/materials folder is writable.';
        }
    }
}

$courses   = q('SELECT id, code, title FROM courses ORDER BY code')->fetchAll();
$filter    = int_param($_GET, 'course');
$sql       = 'SELECT m.*, c.code, c.title AS course_title FROM materials m JOIN courses c ON c.id = m.course_id';
$materials = $filter ? q($sql . ' WHERE m.course_id = ? ORDER BY m.created_at DESC', [$filter])->fetchAll()
                     : q($sql . ' ORDER BY c.code, m.created_at DESC')->fetchAll();

layout_start('Study materials', 'materials', 'Notes, slides and handouts from your lecturers');
?>
<section class="panel">
  <form method="get" class="filter">
    <label class="field field-inline"><span>Course</span>
      <select name="course" data-autosubmit>
        <option value="">All courses</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $filter === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['code'] . ' — ' . $c['title']) ?></option><?php endforeach; ?>
      </select></label>
    <noscript><button class="btn btn-sm">Filter</button></noscript>
  </form>

  <?php if (!$materials): ?>
    <p class="empty"><?= is_admin() ? 'No materials uploaded yet. Use the form below to add the first one.' : 'Your lecturers haven\'t uploaded any materials yet. Check back later.' ?></p>
  <?php else: ?>
    <ul class="files">
      <?php foreach ($materials as $m): $ext = strtoupper(pathinfo($m['original_name'], PATHINFO_EXTENSION)); ?>
        <li>
          <span class="file-ext"><?= e($ext ?: 'FILE') ?></span>
          <span class="file-meta"><b><?= e($m['title']) ?></b>
            <small class="muted"><?= e($m['code']) ?> · <?= e(fmt_bytes((int) $m['file_size'])) ?> · added <?= e(fmt_date($m['created_at'], false)) ?></small></span>
          <a class="btn btn-sm" href="download.php?id=<?= (int) $m['id'] ?>"><?= icon('download') ?>Download</a>
          <?php if (is_admin()): ?>
            <form method="post" data-confirm="Delete &quot;<?= e($m['title']) ?>&quot;? Students will no longer be able to download it.">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button class="icon-btn danger" type="submit" aria-label="Delete <?= e($m['title']) ?>" title="Delete"><?= icon('trash') ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if (is_admin()): ?>
<section class="panel" id="add">
  <div class="panel-head"><h2>Upload material</h2></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('x') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <?php if (!$courses): ?>
    <p class="empty">Add a course first, then you can upload materials for it.</p>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxBytes ?>">
    <label class="field"><span>Course</span>
      <select name="course_id" required><?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) ($_POST['course_id'] ?? $filter) === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?></select></label>
    <label class="field span-2"><span>Title</span><input name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Week 3 lecture notes" required maxlength="150"></label>
    <label class="field span-3"><span>File</span><input type="file" name="file" required accept=".<?= e(implode(',.', $allowed)) ?>">
      <small>Up to <?= (int) $cfg['max_upload_mb'] ?> MB. Allowed: <?= e(implode(', ', $allowed)) ?></small></label>
    <div class="form-actions span-3"><button class="btn btn-primary" type="submit"><?= icon('plus') ?>Upload material</button></div>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php
layout_end();
