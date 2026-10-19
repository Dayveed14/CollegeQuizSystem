<?php
require __DIR__ . '/includes/init.php';

if (current_user()) {
  redirect('dashboard.php');
}

$error = '';
$regNo = '';
$lockKey = 'login_fail';
$fails = $_SESSION[$lockKey] ?? ['count' => 0, 'until' => 0];

if (is_post()) {
  csrf_check();
  $regNo = strtoupper(trim((string) ($_POST['reg_number'] ?? '')));
  $password = (string) ($_POST['password'] ?? '');

  if ($fails['until'] > time()) {
    $wait = (int) ceil(($fails['until'] - time()) / 60);
    $error = "Too many failed attempts. Try again in {$wait} minute" . ($wait === 1 ? '' : 's') . '.';
  } elseif ($regNo === '' || $password === '') {
    $error = 'Enter your registration number and password.';
  } else {
    $user = q('SELECT id, password_hash FROM users WHERE reg_number = ?', [$regNo])->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
      if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
      }
      session_regenerate_id(true);
      unset($_SESSION[$lockKey]);
      $_SESSION['user_id'] = (int) $user['id'];
      redirect('dashboard.php');
    }
    $fails['count']++;
    if ($fails['count'] >= 5) {
      $fails = ['count' => 0, 'until' => time() + 300];
      $error = 'Too many failed attempts. Try again in 5 minutes.';
    } else {
      $error = 'That registration number and password don\'t match. Check both and try again.';
    }
    $_SESSION[$lockKey] = $fails;
  }
}

page_head('Sign in', 'auth');
?>
<div class="auth-wrap">
  <section class="auth-art" aria-hidden="true">
    <div class="sheet">
      <?php
      // A decorative answer sheet: rows of A–D bubbles with one filled per row
      $marks = [2, 0, 3, 1, 2, 3, 0, 1];
      foreach ($marks as $i => $m): ?>
        <div class="sheet-row" style="--d:<?= $i ?>">
          <span class="sheet-n"><?= $i + 1 ?></span>
          <?php foreach (['A', 'B', 'C', 'D'] as $j => $l): ?>
            <span class="bubble<?= $j === $m ? ' is-filled' : '' ?>"><?= $l ?></span>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="auth-tag">Course quizzes, study materials and results, all in one place.</p>
  </section>

  <section class="auth-panel">
    <div class="auth-card">
      <div class="brand brand-dark">
        <img src="assets/img/logo.svg" alt="" width="40" height="40">
        <span><?= e(app_name()) ?></span>
      </div>
      <h1>Sign in</h1>
      <p class="muted">Use the registration number your college gave you.</p>
      <p class="muted">Test Username STU001/Admin PW = 1</p>

      <?php render_flashes(); ?>
      <?php if ($error): ?>
        <div class="flash flash-error" role="alert"><?= icon('x') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <form method="post" class="stack" novalidate>
        <?= csrf_field() ?>
        <label class="field">
          <span>Registration number</span>
          <input type="text" name="reg_number" value="<?= e($regNo) ?>" autocomplete="username" autofocus required>
        </label>
        <label class="field">
          <span>Password</span>
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
      </form>
      <p class="hint">Forgot your password? Ask your course administrator to reset it.</p>
    </div>
  </section>
</div>
</body>

</html>