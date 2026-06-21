<?php
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/includes/auth.php';
$logo_url = null;
foreach (['png','jpg','jpeg','webp','gif'] as $ext) {
    if (file_exists(dirname(__DIR__) . "/assets/logo.$ext")) {
        $logo_url = SITE_URL . "/assets/logo.$ext";
        break;
    }
}
$_fav = null;
foreach (["png","jpg","jpeg","webp","gif"] as $_e) {
  if (file_exists(dirname(__DIR__) . "/assets/favicon.$_e")) { $_fav = SITE_URL . "/assets/favicon.$_e"; break; }
}
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';
$timeout_msg = !empty($_GET['timeout']) ? 'A munkamenet lejárt, kérjük lépj be újra.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Brute-force védelem: IP + username kombináció alapján
        $limit_key = ($username) . '_' . ($_SERVER['REMOTE_ADDR'] ?? '');
        if (!login_rate_limit($limit_key)) {
            $error = 'Túl sok sikertelen bejelentkezési kísérlet. Próbálj újra 15 perc múlva.';
        } else {
            $stmt = db()->prepare('SELECT id, username, password, role FROM admin_users WHERE username=?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                login_reset_limit($limit_key);
                // Session fixation elleni védelem: regenerálunk bejelentkezéskor
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $user['id'];
                $_SESSION['admin_role']     = $user['role'];
                $_SESSION['last_activity']  = time();
                db()->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
                log_action('Bejelentkezés', $user['username']);
                $redirect = ($user['role'] === 'kampanyfonok')
                    ? SITE_URL . '/admin/representatives.php'
                    : SITE_URL . '/admin/dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                // Azonos hibaüzenet timing-attack ellen (ne lehessen kitalálni, hogy a user létezik-e)
                usleep(random_int(200000, 500000));
                $error = 'Hibás felhasználónév vagy jelszó.';
                error_log('Sikertelen bejelentkezés: ' . $username . ' IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            }
        }
    } else {
        $error = 'Töltsd ki mindkét mezőt.';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bejelentkezés – ITSZ Győr Admin</title>
<?php if ($_fav): ?><link rel="icon" type="image/png" href="<?= $_fav ?>?v=<?= time() ?>"><?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --navy:#0c1a3a; --blue:#7eb8d4; --red:#ce2939; --white:#f0f4fa; --muted:#8fa3c2; --border:rgba(126,184,212,0.18); }
body { font-family:'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:1rem; background: radial-gradient(ellipse 80% 80% at 50% 0%, rgba(126,184,212,0.1) 0%, transparent 70%), #080f22;
  min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1rem; color:var(--white); }
.login-box { width:100%; max-width:400px; }
.login-logo { text-align:center; margin-bottom:2rem; }
.login-logo img { width:80px; height:80px; border-radius:50%; object-fit:cover; background:#fff;
  box-shadow: 0 0 40px rgba(126,184,212,0.25), 0 0 0 3px rgba(126,184,212,0.15); }
.login-logo h1 { font-family:'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:1.4rem; font-weight:800; margin-top:.75rem; }
.login-logo p { color:var(--muted); font-size:1rem; }
.card { background:var(--navy); border:1px solid var(--border); border-radius:16px; padding:2rem; }
.form-group { margin-bottom:1.2rem; }
label { display:block; font-size:.8rem; font-weight:500; color:var(--muted); margin-bottom:.4rem; }
input { width:100%; padding:.75rem 1rem; background:rgba(255,255,255,0.05); border:1px solid var(--border);
  border-radius:8px; color:var(--white); font-family:'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:1rem; outline:none;
  transition:border-color .2s; }
input:focus { border-color:var(--blue); }
.password-field-wrap { position: relative; }
.password-field-wrap input { padding-right: 40px; }
.toggle-password {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--white);
  cursor: pointer;
  font-size: 1rem;
  padding: 0;
}
.btn-login { width:100%; padding:.8rem; background:var(--blue); color:var(--navy);
  font-family:'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-weight:700; font-size:1rem; border:none; border-radius:8px;
  cursor:pointer; margin-top:.5rem; transition:background .2s; }
.btn-login:hover { background:#94c8e0; }
.error { background:rgba(206,41,57,0.1); border:1px solid rgba(206,41,57,0.3); color:#e74c3c;
  padding:.75rem 1rem; border-radius:8px; font-size:.85rem; margin-bottom:1rem; }
.back-link { text-align:center; margin-top:1rem; }
.back-link a { color:var(--muted); font-size:.82rem; text-decoration:none; }
.back-link a:hover { color:var(--white); }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <img src="<?= htmlspecialchars(($logo_url ?? SITE_URL . '/assets/logo.jpg') . '?v=' . time()) ?>" alt="Logo">
    <h1>Admin panel</h1>
    <p>Ifjúsági TISZA Sziget – Győr</p>
  </div>
  <div class="card">
    <?php if ($timeout_msg): ?>
      <div class="error" style="background:rgba(255,193,7,.1);border-color:rgba(255,193,7,.4);color:#ffc107;">⏱️ <?= htmlspecialchars($timeout_msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Felhasználónév</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username']??'') ?>" autofocus autocomplete="username">
      </div>
      <div class="form-group">
        <label>Jelszó</label>
        <div class="password-field-wrap">
          <input type="password" id="loginPassword" name="password" autocomplete="current-password">
          <button type="button" class="toggle-password" onclick="togglePasswordVisibility(this)">👁</button>
        </div>
      </div>
      <button class="btn-login" type="submit">Bejelentkezés →</button>
    </form>
  </div>
  <div class="back-link"><a href="<?= SITE_URL ?>">← Vissza a weboldalra</a></div>
</div>
<script>
function togglePasswordVisibility(button) {
  const input = button.previousElementSibling;
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
  button.textContent = input.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
