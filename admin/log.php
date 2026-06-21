<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_superadmin()) { flash('error','Nincs jogosultságod.'); header('Location: ' . SITE_URL . '/admin/dashboard.php'); exit; }
$page_title  = 'Tevékenységnapló';
$active_page = 'log';
$pdo = db();

$page  = max(1, (int)($_GET['p'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;
$stmt_total = $pdo->prepare('SELECT COUNT(*) FROM activity_log');
$stmt_total->execute();
$total  = $stmt_total->fetchColumn();
$pages  = ceil($total / $limit);

$logs = $pdo->prepare('
    SELECT l.*, u.full_name, u.username
    FROM activity_log l
    LEFT JOIN admin_users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
');
$logs->execute([$limit, $offset]);
$logs = $logs->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-title">📋 Tevékenységnapló (<?= $total ?> bejegyzés)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Dátum</th><th>Felhasználó</th><th>Művelet</th><th>Részletek</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem;">Nincs bejegyzés.</td></tr>
        <?php else: ?>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-size:.8rem;color:var(--muted);white-space:nowrap;">
            <?= date('Y.m.d H:i:s', strtotime($l['created_at'])) ?>
          </td>
          <td>
            <?php if ($l['full_name']): ?>
              <div style="font-size:.88rem;"><?= htmlspecialchars($l['full_name']) ?></div>
              <div style="font-size:.74rem;color:var(--muted);"><?= htmlspecialchars($l['username']) ?></div>
            <?php else: ?>
              <span style="color:var(--muted);font-size:.82rem;">Ismeretlen</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:500;"><?= htmlspecialchars($l['action']) ?></td>
          <td style="font-size:.82rem;color:var(--muted);">
            <?= htmlspecialchars(mb_substr($l['details'] ?? '', 0, 120)) ?>
          </td>
          <td style="font-size:.78rem;color:var(--muted);font-family:monospace;"><?= htmlspecialchars($l['ip'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div style="display:flex;gap:.5rem;margin-top:1.5rem;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?p=<?= $i ?>"
         style="padding:.4rem .85rem;border-radius:6px;font-size:.82rem;text-decoration:none;
                <?= $i===$page
                  ? 'background:var(--blue);color:var(--navy);font-weight:700;'
                  : 'background:rgba(126,184,212,0.08);border:1px solid var(--border);color:var(--muted);' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
