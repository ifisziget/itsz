<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title  = 'Dashboard';
$active_page = 'dashboard';

$pdo = db();

// Get counts using prepared statements
$stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users');
$stmt->execute();
$user_count = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM gallery WHERE active=?');
$stmt->execute([1]);
$gallery_count = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM social_links WHERE active=?');
$stmt->execute([1]);
$social_count = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM site_content');
$stmt->execute();
$content_count = $stmt->fetchColumn();

// Get recent activity log
$stmt = $pdo->prepare('
    SELECT l.*, u.full_name FROM activity_log l
    LEFT JOIN admin_users u ON l.user_id=u.id
    ORDER BY l.created_at DESC LIMIT 10
');
$stmt->execute();
$recent_log = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Tartalom mezők</div>
    <div class="stat-value"><?= $content_count ?><div class="stat-icon">✏️</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Galéria képek</div>
    <div class="stat-value"><?= $gallery_count ?><div class="stat-icon">🖼️</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Social linkek</div>
    <div class="stat-value"><?= $social_count ?><div class="stat-icon">📱</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Admin felhasználók</div>
    <div class="stat-value"><?= $user_count ?><div class="stat-icon">👥</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

  <div class="card">
    <div class="card-title">⚡ Gyors műveletek</div>
    <div style="display:flex;flex-direction:column;gap:.75rem;">
      <a href="<?= SITE_URL ?>/admin/content.php?section=hero" class="btn btn-primary">✏️ Hero szöveg szerkesztése</a>
      <a href="<?= SITE_URL ?>/admin/gallery.php" class="btn btn-success">🖼️ Képek kezelése</a>
      <a href="<?= SITE_URL ?>/admin/social.php" class="btn" style="background:rgba(126,184,212,0.12);border:1px solid var(--border);color:var(--blue);">📱 Social linkek</a>
      <a href="<?= SITE_URL ?>/admin/content.php?section=contact" class="btn" style="background:rgba(126,184,212,0.12);border:1px solid var(--border);color:var(--blue);">📬 Kapcsolati adatok</a>
      <a href="<?= SITE_URL ?>/index.php" target="_blank" class="btn" style="background:rgba(71,122,59,0.12);border:1px solid rgba(71,122,59,0.3);color:#6cbf62;">🌐 Weboldal megnyitása ↗</a>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📋 Legutóbbi tevékenységek</div>
    <?php if (empty($recent_log)): ?>
      <p style="color:var(--muted);font-size:.88rem;">Még nincs bejegyzés.</p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.6rem;">
        <?php foreach ($recent_log as $log): ?>
          <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.6rem;background:rgba(126,184,212,0.04);border-radius:8px;">
            <div style="flex:1;">
              <div style="font-size:.85rem;font-weight:500;"><?= htmlspecialchars($log['action']) ?></div>
              <?php if ($log['details']): ?>
                <div style="font-size:.76rem;color:var(--muted);margin-top:.15rem;"><?= htmlspecialchars(substr($log['details'],0,80)) ?></div>
              <?php endif; ?>
            </div>
            <div style="font-size:.72rem;color:var(--muted);white-space:nowrap;">
              <?= date('m.d H:i', strtotime($log['created_at'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
