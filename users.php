<?php
require __DIR__ . '/includes/init.php';
$me = require_admin();

$errors = [];
$blank  = ['id' => 0, 'reg_number' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'role' => 'student'];
$form   = $blank;

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = int_param($_POST, 'id');

    if ($action === 'delete') {
        if ($id === (int) $me['id']) {
            flash('error', 'You can\'t delete your own account while signed in.');
        } else {
            q('DELETE FROM users WHERE id = ?', [$id]);
            flash('success', 'User deleted, along with their quiz results.');
        }
        redirect('users.php');
    }

    if ($action === 'save') {
        $form = [
            'id'         => $id,
            'reg_number' => strtoupper(trim((string) ($_POST['reg_number'] ?? ''))),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name'  => trim((string) ($_POST['last_name'] ?? '')),
            'email'      => trim((string) ($_POST['email'] ?? '')),
            'phone'      => trim((string) ($_POST['phone'] ?? '')),
            'role'       => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'student',
        ];
        $password = (string) ($_POST['password'] ?? '');

        if (!preg_match('/^[A-Z0-9\/\-]{3,50}$/', $form['reg_number'])) $errors[] = 'Registration number should be 3–50 letters, numbers, slashes or dashes.';
        if ($form['first_name'] === '' || $form['last_name'] === '') $errors[] = 'Enter both first and last name.';
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'That email address doesn\'t look right.';
        if (!$form['id'] && strlen($password) < 6) $errors[] = 'Set a password of at least 6 characters.';
        if ($form['id'] && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($form['id'] === (int) $me['id'] && $form['role'] !== 'admin') $errors[] = 'You can\'t remove your own admin role.';
        if (q('SELECT id FROM users WHERE reg_number = ? AND id <> ?', [$form['reg_number'], $form['id']])->fetch()) {
            $errors[] = "Registration number {$form['reg_number']} is already in use.";
        }

        if (!$errors) {
            $p = [$form['reg_number'], $form['first_name'], $form['last_name'], $form['email'], $form['phone'], $form['role']];
            if ($form['id']) {
                q('UPDATE users SET reg_number=?, first_name=?, last_name=?, email=?, phone=?, role=? WHERE id=?', array_merge($p, [$form['id']]));
                if ($password !== '') q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $form['id']]);
                flash('success', "{$form['first_name']} {$form['last_name']} updated" . ($password !== '' ? ', with a new password.' : '.'));
            } else {
                q('INSERT INTO users (reg_number, first_name, last_name, email, phone, role, password_hash) VALUES (?,?,?,?,?,?,?)',
                  array_merge($p, [password_hash($password, PASSWORD_DEFAULT)]));
                flash('success', "{$form['first_name']} {$form['last_name']} can now sign in as {$form['reg_number']}.");
            }
            redirect('users.php');
        }
    }
}

if (!is_post() && ($editId = int_param($_GET, 'edit'))) {
    $row = q('SELECT id, reg_number, first_name, last_name, email, phone, role FROM users WHERE id = ?', [$editId])->fetch();
    if ($row) $form = $row;
}

$roleFilter = in_array($_GET['role'] ?? '', ['admin', 'student'], true) ? $_GET['role'] : '';
$search = trim((string) ($_GET['q'] ?? ''));
$where = []; $params = [];
if ($roleFilter) { $where[] = 'u.role = ?'; $params[] = $roleFilter; }
if ($search !== '') {
    $where[] = '(u.reg_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    array_push($params, "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%");
}
$users = q('SELECT u.*, (SELECT COUNT(*) FROM attempts a WHERE a.user_id = u.id AND a.submitted_at IS NOT NULL) AS taken
            FROM users u' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.role, u.last_name, u.first_name', $params)->fetchAll();

layout_start('Users', 'users', count($users) . ' account' . (count($users) === 1 ? '' : 's'));
?>
<section class="panel">
  <form method="get" class="filter">
    <label class="field field-inline"><span>Show</span>
      <select name="role" data-autosubmit>
        <option value="">Everyone</option>
        <option value="student"<?= $roleFilter === 'student' ? ' selected' : '' ?>>Students</option>
        <option value="admin"<?= $roleFilter === 'admin' ? ' selected' : '' ?>>Administrators</option>
      </select></label>
    <label class="field field-inline"><span>Search</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, reg number or email"></label>
    <button class="btn btn-sm" type="submit">Search</button>
  </form>

  <?php if (!$users): ?>
    <p class="empty">No accounts match. Clear the search or add a user below.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Reg number</th><th class="hide-sm">Email</th><th>Role</th><th class="num">Quizzes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $row): ?>
      <tr>
        <td><b><?= e($row['first_name'] . ' ' . $row['last_name']) ?></b><?= (int) $row['id'] === (int) $me['id'] ? ' <span class="tag">you</span>' : '' ?></td>
        <td><?= e($row['reg_number']) ?></td>
        <td class="muted hide-sm"><?= e($row['email']) ?></td>
        <td><span class="role role-<?= e($row['role']) ?>"><?= $row['role'] === 'admin' ? 'Admin' : 'Student' ?></span></td>
        <td class="num"><?= (int) $row['taken'] ?></td>
        <td class="actions">
          <a class="icon-btn" href="users.php?edit=<?= (int) $row['id'] ?>#add" title="Edit" aria-label="Edit <?= e($row['first_name']) ?>"><?= icon('edit') ?></a>
          <?php if ((int) $row['id'] !== (int) $me['id']): ?>
          <form method="post" data-confirm="Delete <?= e($row['first_name'] . ' ' . $row['last_name']) ?>? Their quiz results will be deleted too.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="icon-btn danger" type="submit" title="Delete" aria-label="Delete <?= e($row['first_name']) ?>"><?= icon('trash') ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="add">
  <div class="panel-head"><h2><?= $form['id'] ? 'Edit ' . e($form['first_name'] . ' ' . $form['last_name']) : 'Add a user' ?></h2>
    <?php if ($form['id']): ?><a class="link" href="users.php">Cancel editing</a><?php endif; ?></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('x') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
  <form method="post" class="form-grid" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <label class="field"><span>First name</span><input name="first_name" value="<?= e($form['first_name']) ?>" required maxlength="80"></label>
    <label class="field"><span>Last name</span><input name="last_name" value="<?= e($form['last_name']) ?>" required maxlength="80"></label>
    <label class="field"><span>Registration number</span><input name="reg_number" value="<?= e($form['reg_number']) ?>" required maxlength="50"><small>Used to sign in</small></label>
    <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($form['email']) ?>" maxlength="150"></label>
    <label class="field"><span>Phone</span><input name="phone" value="<?= e($form['phone']) ?>" maxlength="30"></label>
    <label class="field"><span>Role</span>
      <select name="role"><option value="student"<?= $form['role'] === 'student' ? ' selected' : '' ?>>Student</option><option value="admin"<?= $form['role'] === 'admin' ? ' selected' : '' ?>>Administrator</option></select></label>
    <label class="field span-2"><span><?= $form['id'] ? 'New password' : 'Password' ?></span><input type="password" name="password" minlength="6" autocomplete="new-password"<?= $form['id'] ? '' : ' required' ?>>
      <small><?= $form['id'] ? 'Leave blank to keep their current password' : 'At least 6 characters. Share it with the user privately.' ?></small></label>
    <div class="form-actions span-3"><button class="btn btn-primary" type="submit"><?= $form['id'] ? 'Save changes' : 'Add user' ?></button></div>
  </form>
</section>
<?php
layout_end();
