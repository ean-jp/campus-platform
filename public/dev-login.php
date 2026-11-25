<?php
/**
 * DEV ONLY: fake "log in as" page for testing roles without the real login.
 * Works only when APP_ENV is "local" or "development" in your .env.
 * Delete this file before final submission.
 */
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Env.php';          // P4: read APP_ENV from .env (XAMPP has no system APP_ENV)
\App\Core\Env::load(__DIR__ . '/../.env');

$env = \App\Core\Env::get('APP_ENV') ?: (getenv('APP_ENV') ?: '');
if (!in_array($env, ['local', 'development'], true)) {
    http_response_code(404);
    exit('Not available.');
}

$fakeUsers = [
    // P4: ids 1-3 match real users in seed.sql (so orders, carts and vendor requests can be saved)
    'ashesi_buyer'  => ['id' => 2, 'name' => 'Test Ashesi Buyer', 'email' => 'buyer@ashesi.edu.gh',  'role' => 'buyer',    'account_type' => 'ashesi'],
    'guest_buyer'   => ['id' => 3, 'name' => 'Test Guest Buyer',  'email' => 'guest@gmail.com',       'role' => 'buyer',    'account_type' => 'guest'],
    // P4: id 8 = owner of "Kente Corner" (vendor 5) in seed.sql, so the vendor pages have a real shop
    'ashesi_vendor' => ['id' => 8,    'name' => 'Kente Corner Owner', 'email' => 'vendor5@campus-platform.test', 'role' => 'vendor', 'account_type' => 'guest'],
    'provider'      => ['id' => 9004, 'name' => 'Test Provider',     'email' => 'provider@ashesi.edu.gh','role' => 'provider', 'account_type' => 'ashesi'],
    'admin'         => ['id' => 1, 'name' => 'Test Admin',        'email' => 'admin@ashesi.edu.gh',   'role' => 'admin',    'account_type' => 'ashesi'],
];

Session::start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'logout') {
        Session::destroy();
    } elseif (isset($fakeUsers[$_POST['user'] ?? ''])) {
        Session::login($fakeUsers[$_POST['user']]);
    }
    header('Location: dev-login.php');
    exit;
}

$current = Session::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dev Login</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 480px; margin: 40px auto; padding: 0 16px; }
    .warn { background: #fff3cd; padding: 8px 12px; border-radius: 6px; }
    select, button { padding: 8px; margin-top: 8px; }
  </style>
</head>
<body>
  <h2>Dev login (testing only)</h2>
  <p class="warn">Fake accounts for testing. Remove this file before submission.</p>

  <?php if ($current): ?>
    <p>Logged in as <strong><?= htmlspecialchars($current['name']) ?></strong>
       (<?= htmlspecialchars($current['role']) ?>, <?= htmlspecialchars($current['account_type']) ?>)</p>
    <form method="post">
      <input type="hidden" name="action" value="logout">
      <button type="submit">Log out</button>
    </form>
  <?php else: ?>
    <p>Not logged in.</p>
  <?php endif; ?>

  <form method="post">
    <label for="user">Log in as:</label><br>
    <select name="user" id="user">
      <?php foreach ($fakeUsers as $key => $u): ?>
        <option value="<?= $key ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>, <?= $u['account_type'] ?>)</option>
      <?php endforeach; ?>
    </select><br>
    <button type="submit">Log in</button>
  </form>
</body>
</html>
