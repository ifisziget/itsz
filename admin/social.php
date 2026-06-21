<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title  = 'Social média linkek';
$active_page = 'social';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)$_POST['id'];
        $label    = trim($_POST['label']);
        $handle   = trim($_POST['handle']);
        $url      = trim($_POST['url']);
        $active   = !empty($_POST['active']) ? 1 : 0;
        // URL validáció - javascript: és data: URI tiltva
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            flash('error', 'Érvénytelen URL formátum!');
            header('Location: ' . SITE_URL . '/admin/social.php'); exit;
        }
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            flash('error', 'Tiltott URL protokoll!');
            header('Location: ' . SITE_URL . '/admin/social.php'); exit;
        }
        if ($id) {
            $pdo->prepare('UPDATE social_links SET label=?,handle=?,url=?,active=? WHERE id=?')
                ->execute([$label,$handle,$url,$active,$id]);
            log_action('Social link frissítve', "$label: $url");
            flash('success', 'Link frissítve!');
        } else {
            $platform   = trim($_POST['platform']);
            $icon_class = trim($_POST['icon_class']);
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM social_links');
            $stmt->execute();
            $order = (int)$stmt->fetchColumn();
            // URL validáció
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('/^(javascript|data|vbscript):/i', $url))) {
                flash('error', 'Érvénytelen vagy tiltott URL!');
                header('Location: ' . SITE_URL . '/admin/social.php'); exit;
            }
            $pdo->prepare('INSERT INTO social_links (platform,label,handle,url,icon_class,active,sort_order) VALUES (?,?,?,?,?,?,?)')
                ->execute([$platform,$label,$handle,$url,$icon_class,1,$order]);
            log_action('Social link hozzáadva', "$label: $url");
            flash('success', 'Link hozzáadva!');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM social_links WHERE id=?')->execute([$id]);
        flash('success', 'Link törölve.');
    }

    header('Location: ' . SITE_URL . '/admin/social.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM social_links ORDER BY sort_order');
$stmt->execute();
$links = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-title">📱 Social média linkek</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Platform</th>
          <th>Megjelenő név</th>
          <th>Handle / Felirat</th>
          <th>URL</th>
          <th>Állapot</th>
          <th>Műveletek</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($links as $link): ?>
        <tr>
          <td>
            <?php
            $icons = ['facebook'=>'🔵','instagram'=>'🟣','tiktok'=>'⚫','youtube'=>'🔴','twitter'=>'🐦'];
            echo ($icons[$link['platform']] ?? '🌐') . ' ' . ucfirst($link['platform']);
            ?>
          </td>
          <td><?= htmlspecialchars($link['label']) ?></td>
          <td style="color:var(--muted);font-size:.85rem;"><?= htmlspecialchars($link['handle']) ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"
               style="color:var(--blue);font-size:.8rem;"><?= htmlspecialchars($link['url']) ?></a>
          </td>
          <td>
            <span class="badge <?= $link['active']?'badge-green':'badge-red' ?>">
              <?= $link['active']?'Aktív':'Rejtett' ?>
            </span>
          </td>
          <td>
            <button onclick="openModal(<?= htmlspecialchars(json_encode($link)) ?>)"
                    class="btn btn-sm" style="background:rgba(126,184,212,0.1);border:1px solid var(--border);color:var(--blue);">
              ✏️ Szerkesztés
            </button>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Törlöd ezt a linket?')">
              <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id"     value="<?= $link['id'] ?>">
              <button class="btn btn-sm btn-danger">🗑️</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-title">➕ Új social link hozzáadása</div>
  <form method="POST">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id"     value="0">
    <div class="form-row">
      <div class="form-group">
        <label>Platform</label>
        <select id="platformSelect" name="platform" class="form-control">
          <option value="facebook">Facebook</option>
          <option value="instagram">Instagram</option>
          <option value="tiktok">TikTok</option>
          <option value="youtube">YouTube</option>
          <option value="twitter">Twitter/X</option>
        </select>
      </div>
      <div class="form-group">
        <label>Ikonok</label>
        <input id="iconClass" type="text" name="icon_class" class="form-control" value="fb">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Megjelenő név</label>
        <input type="text" name="label" class="form-control" placeholder="pl. Facebook">
      </div>
      <div class="form-group">
        <label>Handle / Felirat</label>
        <input type="text" name="handle" class="form-control" placeholder="pl. @felhasznalonev">
      </div>
    </div>
    <div class="form-group">
      <label>URL</label>
      <input type="url" name="url" class="form-control" placeholder="https://...">
    </div>
    <button type="submit" class="btn btn-primary">➕ Hozzáadás</button>
  </form>
</div>

<!-- EDIT MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;
     align-items:center;justify-content:center;">
  <div style="background:#132040;border:1px solid var(--border);border-radius:16px;padding:2rem;
              width:100%;max-width:480px;margin:1rem;">
    <h3 style="font-family:inherit;margin-bottom:1.5rem;">✏️ Link szerkesztése</h3>
    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id"     id="eId">
      <div class="form-group">
        <label>Megjelenő név</label>
        <input type="text" name="label" id="eLabel" class="form-control">
      </div>
      <div class="form-group">
        <label>Handle / Felirat</label>
        <input type="text" name="handle" id="eHandle" class="form-control">
      </div>
      <div class="form-group">
        <label>URL</label>
        <input type="url" name="url" id="eUrl" class="form-control">
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:.75rem;">
        <label class="toggle">
          <input type="checkbox" name="active" id="eActive" value="1">
          <span class="toggle-slider"></span>
        </label>
        <span style="font-size:.88rem;">Aktív (megjelenik a weboldalon)</span>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1rem;">
        <button type="submit" class="btn btn-primary">💾 Mentés</button>
        <button type="button" onclick="closeModal()"
                class="btn" style="background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--muted);">
          Mégse
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(data) {
  document.getElementById('eId').value     = data.id;
  document.getElementById('eLabel').value  = data.label;
  document.getElementById('eHandle').value = data.handle;
  document.getElementById('eUrl').value    = data.url;
  document.getElementById('eActive').checked = data.active == 1;
  document.getElementById('editModal').style.display = 'flex';
}
function closeModal() { document.getElementById('editModal').style.display = 'none'; }
document.getElementById('editModal').addEventListener('click', e => { if(e.target===document.getElementById('editModal')) closeModal(); });

(function(){
  const platformInput = document.getElementById('platformSelect');
  const iconInput = document.getElementById('iconClass');
  const platformMap = {
    facebook: 'fb',
    instagram: 'ig',
    tiktok: 'tt',
    youtube: 'yt',
    twitter: 'tw'
  };

  function syncIcon() {
    const value = platformInput.value;
    iconInput.value = platformMap[value] || '';
  }

  if (platformInput && iconInput) {
    platformInput.addEventListener('change', syncIcon);
    syncIcon();
  }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
