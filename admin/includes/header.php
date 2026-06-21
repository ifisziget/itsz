<?php
$user  = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="hu" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title ?? 'Admin') ?> – ITSZ Győr Admin</title>
<?php
$_fav = null;
foreach (["png","jpg","jpeg","webp","gif"] as $_e) {
    if (file_exists(dirname(__DIR__,2)."/assets/favicon.$_e")) { $_fav = SITE_URL."/assets/favicon.$_e"; break; }
}
if ($_fav): ?><link rel="icon" type="image/png" href="<?= $_fav ?>?v=<?= time() ?>">
<link rel="shortcut icon" href="<?= $_fav ?>?v=<?= time() ?>">
<?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body, button, input, select, textarea, label, a, span, div, p, h1, h2, h3, h4, h5, h6, th, td {
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

/* ── DARK MODE (alapértelmezett) ── */
[data-theme="dark"] {
  --bg:          #080f22;
  --navy:        #0c1a3a;
  --navy-mid:    #132040;
  --navy-light:  #1a2d55;
  --surface:     #0f1e3d;
  --card:        #0f1e3d;
  --border:      rgba(126,184,212,0.18);
  --text:        #f0f4fa;
  --text-muted:  #8fa3c2;
  --blue:        #7eb8d4;
  --red:         #ce2939;
  --green:       #477a3b;
  --success:     #2ecc71;
  --danger:      #e74c3c;
  --input-bg:    rgba(255,255,255,0.05);
  --sidebar-bg:  #0c1a3a;
  --topbar-bg:   #0c1a3a;
  --hover-bg:    rgba(126,184,212,0.08);
  --active-bg:   rgba(126,184,212,0.12);
  --shadow:      0 4px 20px rgba(0,0,0,0.4);
}

/* ── LIGHT MODE ── */
[data-theme="light"] {
  --bg:          #eef3fb;
  --navy:        #ffffff;
  --navy-mid:    #f0f5ff;
  --navy-light:  #dce8f8;
  --surface:     #f7faff;
  --card:        #ffffff;
  --border:      rgba(80,130,200,0.2);
  --text:        #0d1e3a;
  --text-muted:  #4a6080;
  --blue:        #2a72b8;
  --red:         #c02030;
  --green:       #2e6b25;
  --success:     #1e9e52;
  --danger:      #c0392b;
  --input-bg:    #f0f5ff;
  --sidebar-bg:  #1a3a6e;
  --topbar-bg:   #ffffff;
  --hover-bg:    rgba(42,114,184,0.08);
  --active-bg:   rgba(42,114,184,0.14);
  --shadow:      0 4px 20px rgba(30,80,150,0.1);
}

body {
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  font-size: 1rem;
  background: var(--bg);
  color: var(--text);
  display: flex;
  min-height: 100vh;
  transition: background .3s, color .3s;
}

/* ── SIDEBAR ── */
.sidebar {
  width: 260px;
  min-height: 100vh;
  background: var(--sidebar-bg);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 50;
  overflow-y: auto;
  transition: background .3s;
}
[data-theme="light"] .sidebar { box-shadow: 2px 0 12px rgba(30,80,150,0.1); }
/* Sidebar mindig sötét marad - szövegek mindig fehérek */
[data-theme="light"] .side-nav a { color: rgba(255,255,255,.7); }
[data-theme="light"] .side-nav a:hover { background: rgba(255,255,255,.1); color: #fff; }
[data-theme="light"] .side-nav a.active { background: rgba(255,255,255,.15); color: #fff; }
[data-theme="light"] .nav-group-label { color: rgba(255,255,255,.4); }
[data-theme="light"] .user-info .name { color: #fff; }
[data-theme="light"] .user-info .role { color: rgba(255,255,255,.5); }
[data-theme="light"] .sidebar-logo .brand { color: #fff; }

.sidebar-logo {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,0.12);
  display: flex; align-items: center; gap: 12px;
}
.sidebar-logo img {
  width: 44px; height: 44px; border-radius: 50%;
  object-fit: cover; background: #fff;
  border: 2px solid rgba(255,255,255,0.2);
}
.sidebar-logo .brand {
  font-family: inherit; font-size: .78rem; font-weight: 700;
  line-height: 1.3; color: #fff;
}
.sidebar-logo .brand span { color: rgba(255,255,255,.6); display: block; font-size: .68rem; }

nav.side-nav { flex: 1; padding: 1rem 0; }
.nav-group { margin-bottom: .5rem; }
.nav-group-label {
  font-family: inherit; font-size: .62rem; font-weight: 700;
  letter-spacing: .14em; text-transform: uppercase;
  color: rgba(255,255,255,.4);
  padding: .6rem 1.5rem .3rem;
}
.side-nav a {
  display: flex; align-items: center; gap: 10px;
  padding: .65rem 1.5rem; font-size: .88rem;
  color: rgba(255,255,255,.65);
  text-decoration: none; transition: background .2s, color .2s;
  position: relative;
}
.side-nav a .icon { width: 20px; text-align: center; font-size: 1rem; }
.side-nav a:hover  { background: rgba(255,255,255,.08); color: #fff; }
.side-nav a.active { background: rgba(255,255,255,.12); color: #fff; }
.side-nav a.active::before {
  content: ''; position: absolute; left: 0; top: 4px; bottom: 4px;
  width: 3px; background: #fff; border-radius: 0 3px 3px 0;
}

.sidebar-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255,255,255,.12);
}
.user-chip { display: flex; align-items: center; gap: 10px; margin-bottom: .75rem; }
.user-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(255,255,255,.2);
  display: flex; align-items: center; justify-content: center;
  font-family: inherit; font-weight: 700; font-size: .85rem; color: #fff;
}
.user-info .name { font-size: .85rem; font-weight: 500; color: #fff; }
.user-info .role { font-size: .72rem; color: rgba(255,255,255,.5); }
.btn-logout {
  display: block; text-align: center; padding: .55rem;
  border-radius: 8px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.15);
  color: rgba(255,255,255,.8); font-size: .82rem; font-weight: 600;
  text-decoration: none; transition: background .2s;
}
.btn-logout:hover { background: rgba(255,255,255,.15); }

/* ── MAIN ── */
.main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

.topbar {
  background: var(--topbar-bg);
  border-bottom: 1px solid var(--border);
  padding: 1rem 2rem;
  display: flex; align-items: center; justify-content: space-between;
  transition: background .3s;
  box-shadow: var(--shadow);
}
.topbar h1 {
  font-family: inherit; font-size: 1.2rem; font-weight: 700;
  color: var(--text);
}
.topbar-actions { display: flex; gap: .75rem; align-items: center; }

/* Theme toggle button */
.theme-toggle {
  width: 40px; height: 40px; border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--hover-bg);
  color: var(--text); font-size: 1.1rem;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background .2s;
}
.theme-toggle:hover { background: var(--active-bg); }

.btn-view-site {
  display: flex; align-items: center; gap: 6px; padding: .5rem 1rem;
  border-radius: 8px; border: 1px solid var(--border);
  color: var(--blue); font-size: .82rem; text-decoration: none;
  transition: background .2s;
}
.btn-view-site:hover { background: var(--hover-bg); }

.content-area { flex: 1; padding: 2rem; }

/* ── FLASH ── */
.flash {
  padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;
  font-size: .9rem; display: flex; align-items: center; gap: .75rem;
}
.flash.success { background: rgba(46,204,113,.12); border: 1px solid rgba(46,204,113,.3); color: var(--success); }
.flash.error   { background: rgba(231,76,60,.12);  border: 1px solid rgba(231,76,60,.3);  color: var(--danger); }
.flash.info    { background: var(--hover-bg); border: 1px solid var(--border); color: var(--blue); }

/* ── CARDS ── */
.card {
  background: var(--card); border: 1px solid var(--border);
  border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem;
  box-shadow: var(--shadow); transition: background .3s;
}
.card-title {
  font-family: inherit; font-size: 1rem; font-weight: 700;
  margin-bottom: 1.25rem; color: var(--text);
  display: flex; align-items: center; gap: .6rem;
  padding-bottom: .75rem; border-bottom: 1px solid var(--border);
}

/* ── FORMS ── */
.form-group { margin-bottom: 1.25rem; }
.form-group label {
  display: block; font-size: .82rem; font-weight: 600; color: var(--text-muted);
  margin-bottom: .4rem; letter-spacing: .04em;
}
.form-control {
  width: 100%; padding: .7rem .9rem;
  background: var(--input-bg);
  border: 1px solid var(--border); border-radius: 8px;
  color: var(--text); font-family: inherit; font-size: .9rem;
  transition: border-color .2s, background .3s; outline: none;
}
.form-control:focus { border-color: var(--blue); }
select.form-control {
  background-color: var(--input-bg);
  color: var(--text);
}
select.form-control option {
  background-color: #132040;
  color: #f0f4fa;
}
[data-theme="light"] select.form-control option {
  background-color: #ffffff;
  color: #0d1e3a;
}
textarea.form-control { min-height: 100px; resize: vertical; }
.form-hint { font-size: .75rem; color: var(--text-muted); margin-top: .3rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* ── BUTTONS ── */
.btn {
  display: inline-flex; align-items: center; gap: .5rem;
  padding: .65rem 1.4rem; border-radius: 8px;
  font-family: inherit; font-weight: 700; font-size: .85rem;
  cursor: pointer; border: none; text-decoration: none;
  transition: all .2s; letter-spacing: .04em;
}
.btn-primary { background: var(--blue); color: #fff; }
[data-theme="dark"] .btn-primary { color: var(--navy); }
.btn-primary:hover { filter: brightness(1.1); }
.btn-success { background: rgba(46,204,113,.15); border: 1px solid rgba(46,204,113,.35); color: var(--success); }
.btn-success:hover { background: rgba(46,204,113,.25); }
.btn-danger  { background: rgba(231,76,60,.12);  border: 1px solid rgba(231,76,60,.3);  color: var(--danger); }
.btn-danger:hover  { background: rgba(231,76,60,.22); }
.btn-sm { padding: .4rem .9rem; font-size: .78rem; }

/* ── TABLE ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th {
  font-family: inherit; font-size: .72rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted);
  padding: .7rem 1rem; border-bottom: 1px solid var(--border); text-align: left;
}
td {
  padding: .75rem 1rem; font-size: .88rem;
  border-bottom: 1px solid var(--border); vertical-align: middle;
  color: var(--text);
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--hover-bg); }

/* ── BADGES ── */
.badge { display: inline-block; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 700; letter-spacing: .06em; }
.badge-green { background: rgba(46,204,113,.15); color: var(--success); }
.badge-red   { background: rgba(231,76,60,.15);  color: var(--danger); }
.badge-blue  { background: rgba(42,114,184,.15); color: var(--blue); }

/* ── STATS ── */
.stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card {
  background: var(--card); border: 1px solid var(--border);
  border-radius: 12px; padding: 1.2rem 1.4rem;
  box-shadow: var(--shadow); transition: background .3s;
}
.stat-card .stat-label { font-size: .75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .1em; margin-bottom: .4rem; }
.stat-card .stat-value { font-family: inherit; font-size: 2rem; font-weight: 800; color: var(--text); }

/* ── QUILL EDITOR ── */
.ql-container { background: var(--input-bg); border-color: var(--border) !important; border-radius: 0 0 8px 8px; color: var(--text); font-family: inherit; font-size: .95rem; min-height: 120px; }
.ql-toolbar { background: var(--navy-mid); border-color: var(--border) !important; border-radius: 8px 8px 0 0; }
[data-theme="light"] .ql-toolbar { background: #e8f0fb; }
.ql-toolbar .ql-stroke { stroke: var(--text-muted) !important; }
.ql-toolbar .ql-fill   { fill:   var(--text-muted) !important; }
.ql-toolbar button:hover .ql-stroke { stroke: var(--blue) !important; }
.ql-toolbar button:hover .ql-fill   { fill:   var(--blue) !important; }
.ql-toolbar .ql-active .ql-stroke   { stroke: var(--blue) !important; }
.ql-toolbar .ql-active .ql-fill     { fill:   var(--blue) !important; }
.ql-editor { min-height: 120px; }
.ql-editor.ql-blank::before { color: var(--text-muted) !important; font-style: normal; }

/* ── TOGGLE ── */
.toggle { position: relative; display: inline-block; width: 42px; height: 24px; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--navy-light); border-radius: 24px; transition: .3s; border: 1px solid var(--border); }
.toggle-slider::before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: var(--text-muted); border-radius: 50%; transition: .3s; }
.toggle input:checked + .toggle-slider { background: rgba(46,204,113,.2); border-color: var(--success); }
.toggle input:checked + .toggle-slider::before { transform: translateX(18px); background: var(--success); }

/* ── MISC ── */
.img-preview-lg { max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); display: block; margin-bottom: .5rem; }
.sort-handle { cursor: grab; color: var(--text-muted); padding: 0 .5rem; font-size: 1.1rem; }

/* Overlay a nyitott sidebar mögé mobilon */
.sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.55);
  z-index: 49;
}
.sidebar-overlay.active { display: block; }

/* Hamburger gomb */
.hamburger {
  display: none;
  width: 40px; height: 40px;
  border: 1px solid var(--border);
  background: var(--hover-bg);
  border-radius: 10px;
  cursor: pointer;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  flex-shrink: 0;
}
.hamburger span {
  display: block; width: 18px; height: 2px;
  background: var(--text); border-radius: 2px;
  transition: .2s;
}

@media (max-width: 900px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform .3s;
    z-index: 50;
  }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .form-row { grid-template-columns: 1fr; }
  .hamburger { display: flex; }
  .topbar { padding: .75rem 1rem; gap: .5rem; }
  .topbar h1 { font-size: 1rem; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .topbar-actions { gap: .4rem; margin-right: .5rem; }
  .theme-toggle { width: 36px; height: 36px; font-size: 1rem; }
  .btn-view-site { padding: .45rem .7rem; font-size: .78rem; }
  .content-area { padding: 1rem; }
  .card { padding: 1.1rem; }
  .stats-grid { grid-template-columns: 1fr 1fr; gap: .75rem; }
}

@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr; }
  .btn-view-site span { display: none; }
}

[data-theme="light"] .form-control {
  color: #0d1e3a;
  background: #f0f5ff;
  border-color: rgba(80,130,200,0.3);
}
[data-theme="light"] .form-control::placeholder { color: #7a9cbf; }
</style>
</head>
<body>

<?php
$admin_logo = null;
foreach (['png','jpg','jpeg','webp','gif'] as $ext) {
    if (file_exists(dirname(__DIR__,2) . "/assets/logo.$ext")) {
        $admin_logo = SITE_URL . "/assets/logo.$ext";
        break;
    }
}
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="<?= htmlspecialchars(($admin_logo ?? SITE_URL . '/assets/logo.jpg') . '?v=' . time()) ?>" alt="Logo">
    <div class="brand">ITSZ Admin<span>Győr · Kezelőfelület</span></div>
  </div>
  <nav class="side-nav">
    <?php if (is_campaign_manager() && !is_superadmin()): ?>
    <!-- KAMPÁNYFŐNÖK: csak képviselők + saját fiók -->
    <div class="nav-group">
      <div class="nav-group-label">Feladatom</div>
      <a href="<?= SITE_URL ?>/admin/representatives.php" class="<?= ($active_page??'')=='representatives'?'active':'' ?>"><span class="icon">👥</span> Képviselőink</a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Fiók</div>
      <a href="<?= SITE_URL ?>/admin/users.php" class="<?= ($active_page??'')=='users'?'active':'' ?>"><span class="icon">🔒</span> Jelszó & Fiók</a>
    </div>
    <?php else: ?>
    <!-- ADMIN / SUPERADMIN: teljes menü -->
    <div class="nav-group">
      <div class="nav-group-label">Főoldal</div>
      <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= ($active_page??'')=='dashboard'?'active':'' ?>"><span class="icon">📊</span> Dashboard</a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Tartalom</div>
      <a href="<?= SITE_URL ?>/admin/content.php?section=hero"    class="<?= ($active_page??'')=='hero'?'active':'' ?>"><span class="icon">🏠</span> Hero szekció</a>
      <a href="<?= SITE_URL ?>/admin/content.php?section=about"   class="<?= ($active_page??'')=='about'?'active':'' ?>"><span class="icon">ℹ️</span> Rólunk</a>
      <a href="<?= SITE_URL ?>/admin/content.php?section=goals"   class="<?= ($active_page??'')=='goals'?'active':'' ?>"><span class="icon">🎯</span> Céljaink</a>
      <a href="<?= SITE_URL ?>/admin/content.php?section=join"    class="<?= ($active_page??'')=='join'?'active':'' ?>"><span class="icon">🚀</span> Csatlakozás</a>
      <a href="<?= SITE_URL ?>/admin/content.php?section=contact" class="<?= ($active_page??'')=='contact'?'active':'' ?>"><span class="icon">📬</span> Kapcsolat</a>
      <a href="<?= SITE_URL ?>/admin/content.php?section=footer"  class="<?= ($active_page??'')=='footer'?'active':'' ?>"><span class="icon">📄</span> Footer</a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Média</div>
      <a href="<?= SITE_URL ?>/admin/gallery.php" class="<?= ($active_page??'')=='gallery'?'active':'' ?>"><span class="icon">🖼️</span> Galéria</a>
      <a href="<?= SITE_URL ?>/admin/assets.php"  class="<?= ($active_page??'')=='assets'?'active':'' ?>"><span class="icon">🔵</span> Logó & QR kód</a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Közösségi</div>
      <a href="<?= SITE_URL ?>/admin/social.php"          class="<?= ($active_page??'')=='social'?'active':'' ?>"><span class="icon">📱</span> Social linkek</a>
      <a href="<?= SITE_URL ?>/admin/representatives.php" class="<?= ($active_page??'')=='representatives'?'active':'' ?>"><span class="icon">👥</span> Képviselőink</a>
    </div>
    <div class="nav-group">
      <div class="nav-group-label">Megjelenés</div>
      <a href="<?= SITE_URL ?>/admin/settings.php" class="<?= ($active_page??'')=='settings'?'active':'' ?>"><span class="icon">🎨</span> Évszakok & Stílus</a>
    </div>
    <?php if (is_superadmin()): ?>
    <div class="nav-group">
      <div class="nav-group-label">Rendszer</div>
      <a href="<?= SITE_URL ?>/admin/users.php" class="<?= ($active_page??'')=='users'?'active':'' ?>"><span class="icon">👤</span> Felhasználók</a>
      <a href="<?= SITE_URL ?>/admin/log.php"   class="<?= ($active_page??'')=='log'?'active':'' ?>"><span class="icon">📋</span> Napló</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($user['full_name']??'A',0,1)) ?></div>
      <div class="user-info">
          <div class="name"><?= htmlspecialchars($user['full_name']??'') ?></div>
          <div class="role"><?php
              $r = $user['role'] ?? '';
              if ($r === 'superadmin') echo 'Főadmin';
              elseif ($r === 'kampanyfonok') echo 'Kampányfőnök';
              else echo 'Admin';
          ?></div>
        </div>
    </div>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="btn-logout">Kijelentkezés</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menü megnyitása">
      <span></span><span></span><span></span>
    </button>
    <h1><?= htmlspecialchars($page_title ?? '') ?></h1>
    <div class="topbar-actions">
      <button class="theme-toggle" id="themeToggle" title="Világos/sötét mód">🌙</button>
      <a href="<?= SITE_URL ?>/index.php" target="_blank" class="btn-view-site">🌐 Weboldal</a>
    </div>
  </div>
  <div class="content-area">
<?php if ($flash): ?>
  <div class="flash <?= htmlspecialchars($flash['type']) ?>">
    <?= $flash['type']==='success'?'✅':($flash['type']==='error'?'❌':'ℹ️') ?>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
// Theme persistence
(function(){
  // Admin panel mindig dark módban tölt be
  localStorage.setItem('itsz_theme', 'dark');
  document.documentElement.setAttribute('data-theme', 'dark');
  document.addEventListener('DOMContentLoaded', function(){
    // Theme toggle
    const btn = document.getElementById('themeToggle');
    if (btn) {
      btn.textContent = '🌙';
      btn.addEventListener('click', function(){
        const cur = document.documentElement.getAttribute('data-theme');
        const next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('itsz_theme', next);
        btn.textContent = next === 'dark' ? '🌙' : '☀️';
      });
    }
    // Hamburger / sidebar toggle mobilon
    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); }
    if (hamburger) hamburger.addEventListener('click', function(){
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
    // Sidebar linkre kattintáskor mobilon bezárul
    if (sidebar) sidebar.querySelectorAll('.side-nav a').forEach(function(a){
      a.addEventListener('click', function(){ if(window.innerWidth <= 900) closeSidebar(); });
    });
  });
})();
</script>