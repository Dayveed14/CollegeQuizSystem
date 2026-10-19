<?php
require __DIR__ . '/includes/init.php';
$u = require_login();
$errors = [];
$pwErrors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'details') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'That email address doesn\'t look right.';
        if (mb_strlen($phone) > 30) $errors[] = 'Phone number is too long.';
        if (!$errors) {
            q('UPDATE users SET email = ?, phone = ? WHERE id = ?', [$email, $phone, $u['id']]);
            flash('success', 'Contact details saved.');
            redirect('profile.php');
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $new     = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        $hash = q('SELECT password_hash FROM users WHERE id = ?', [$u['id']])->fetchColumn();
        if (!password_verify($current, (string) $hash)) $pwErrors[] = 'Your current password is incorrect.';
        if (strlen($new) < 6) $pwErrors[] = 'New password must be at least 6 characters.';
        if ($new !== $confirm) $pwErrors[] = 'The new passwords don\'t match.';
        if (!$pwErrors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }
}

layout_start('My profile', 'profile', $u['first_name'] . ' ' . $u['last_name']);
?>
<div class="grid-2">
  <section class="panel">
    <div class="panel-head"><h2>Contact details</h2></div>
    <dl class="kv">
      <dt>Registration number</dt><dd><?= e($u['reg_number']) ?></dd>
      <dt>Name</dt><dd><?= e($u['first_name'] . ' ' . $u['last_name']) ?></dd>
      <dt>Role</dt><dd><?= $u['role'] === 'admin' ? 'Administrator' : 'Student' ?></dd>
    </dl>
    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('x') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="details">
      <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($_POST['email'] ?? $u['email']) ?>" maxlength="150"></label>
      <label class="field"><span>Phone</span><input name="phone" value="<?= e($_POST['phone'] ?? $u['phone']) ?>" maxlength="30"></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save details</button></div>
    </form>
    <p class="hint">To change your name or registration number, contact an administrator.</p>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Change password</h2></div>
    <?php foreach ($pwErrors as $err): ?><div class="flash flash-error"><?= icon('x') ?><span><?= e($err) ?></span></div><?php endforeach; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label class="field"><span>Current password</span><input type="password" name="current" autocomplete="current-password" required></label>
      <label class="field"><span>New password</span><input type="password" name="new" minlength="6" autocomplete="new-password" required></label>
      <label class="field"><span>Confirm new password</span><input type="password" name="confirm" minlength="6" autocomplete="new-password" required></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Change password</button></div>
    </form>
  </section>
</div>
<?php
layout_end();
