<?php
function render_auth_page(string $mode, ?string $error = null): void {
    $setup = $mode === 'setup';
    $title = $setup ? 'Set up your school administrator' : 'Welcome back';
    $subtitle = $setup ? 'Create the first account for your school portal.' : 'Sign in to your school workspace.';
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#143d35"><title><?= h($title) ?> · Mavambo SchoolOS</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/secure.css"><link rel="stylesheet" href="assets/readability.css"></head>
    <body class="auth-body"><main class="auth-card"><div class="auth-brand"><div class="brand-mark">M</div><div><strong>Mavambo</strong><span>SchoolOS</span></div></div><div class="eyebrow">SCHOOL ADMINISTRATION</div><h1><?= h($title) ?></h1><p class="auth-subtitle"><?= h($subtitle) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="actions/<?= $setup ? 'first_admin_create' : 'login' ?>.php" class="auth-form">
      <?= csrf_field() ?>
      <?php if ($setup && production_mode()): ?><label>One-time setup key<input type="password" name="setup_key" required autocomplete="off"></label><?php endif; ?>
      <?php if ($setup): ?><label>Your name<input name="full_name" required maxlength="160" autocomplete="name" autofocus></label><?php endif; ?>
      <label>Email address<input type="email" name="email" required maxlength="190" autocomplete="username" autofocus></label>
      <label>Password<input type="password" name="password" required minlength="<?= $setup ? '12' : '1' ?>" autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"><?php if ($setup): ?><small>Use at least 12 characters.</small><?php endif; ?></label>
      <?php if ($setup): ?><label>Confirm password<input type="password" name="password_confirm" required minlength="12" autocomplete="new-password"></label><?php endif; ?>
      <button class="primary-button auth-submit"><?= $setup ? 'Create administrator account' : 'Sign in' ?></button>
    </form><div class="auth-foot">Your school workspace is private to authorised users.</div></main></body></html>
    <?php
}
?>
