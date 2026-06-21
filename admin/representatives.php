<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// Csak superadmin vagy kampányfőnök léphet be
if (!is_superadmin() && !is_campaign_manager()) {
    flash('error', 'Nincs jogosultságod!');
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$page_title  = 'Képviselőink kezelése';
$active_page = 'representatives';
$pdo = db();

// ──────────────────────────────────────────────────────────
// MŰVELETEK
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ─── SZERKESZTÉS (csak kampányfőnök) ───────────────────
    if ($action === 'edit' && is_campaign_manager()) {
        $id        = (int)$_POST['id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $bio       = trim($_POST['bio'] ?? '');
        $extra     = trim($_POST['extra_text'] ?? '');
        $link_url  = trim($_POST['link_url'] ?? '');

        if (empty($full_name)) {
            flash('error', 'A név nem lehet üres!');
        } else {
            // Ellenőrizzük, hogy a képviselő a kampányfőnökhöz tartozik-e
            $stmtCheck = $pdo->prepare('SELECT manager_id FROM representatives WHERE id=?');
            $stmtCheck->execute([$id]);
            $owner = $stmtCheck->fetchColumn();
            if ((int)$owner !== (int)$_SESSION['admin_id']) {
                flash('error', 'Nincs jogosultságod ennek a képviselőnek a módosításához.');
                header('Location: ' . SITE_URL . '/admin/representatives.php');
                exit;
            }

            $pdo->prepare('UPDATE representatives SET full_name=?, bio=?, extra_text=?, link_url=? WHERE id=?')
                ->execute([$full_name, $bio, $extra, $link_url, $id]);

            // Profilkép törlése
            if (!empty($_POST['delete_image'])) {
                $stmt_old = $pdo->prepare('SELECT filename FROM representatives WHERE id=?');
                $stmt_old->execute([$id]);
                $old_file = $stmt_old->fetchColumn();
                if ($old_file && file_exists(UPLOAD_DIR . $old_file)) @unlink(UPLOAD_DIR . $old_file);
                $pdo->prepare('UPDATE representatives SET filename=NULL WHERE id=?')->execute([$id]);
            }

            // Tartalom kép törlése
            if (!empty($_POST['delete_content_image'])) {
                $stmt_old = $pdo->prepare('SELECT content_filename FROM representatives WHERE id=?');
                $stmt_old->execute([$id]);
                $old_file = $stmt_old->fetchColumn();
                if ($old_file && file_exists(UPLOAD_DIR . $old_file)) @unlink(UPLOAD_DIR . $old_file);
                $pdo->prepare('UPDATE representatives SET content_filename=NULL WHERE id=?')->execute([$id]);
            }

            // Profilkép feltöltés
            if (!empty($_FILES['image']['name'])) {
                $file    = $_FILES['image'];
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= MAX_FILE_SIZE) {
                    $stmt_old = $pdo->prepare('SELECT filename FROM representatives WHERE id=?');
                    $stmt_old->execute([$id]);
                    $old_file = $stmt_old->fetchColumn();
                    if ($old_file && file_exists(UPLOAD_DIR . $old_file)) @unlink(UPLOAD_DIR . $old_file);
                    $uploadErr = validate_image_upload($file);
                    if ($uploadErr) { flash('error', $uploadErr); header('Location: ' . SITE_URL . '/admin/representatives.php'); exit; }
                    $filename = safe_upload_filename('rep_', $file['tmp_name']);
                    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                        $pdo->prepare('UPDATE representatives SET filename=? WHERE id=?')->execute([$filename, $id]);
                    }
                } else {
                    flash('error', 'Képfeltöltési hiba (méret/típus).');
                }
            }

            // Tartalom kép feltöltés
            if (!empty($_FILES['content_image']['name'])) {
                $file    = $_FILES['content_image'];
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= MAX_FILE_SIZE) {
                    $uploadErr2 = validate_image_upload($file);
                    if ($uploadErr2) { flash('error', $uploadErr2); header('Location: ' . SITE_URL . '/admin/representatives.php'); exit; }
                    $cfn = safe_upload_filename('rep_content_', $file['tmp_name']);
                    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $cfn)) {
                        $pdo->prepare('UPDATE representatives SET content_filename=? WHERE id=?')->execute([$cfn, $id]);
                    }
                }
            }

            log_action('Képviselő szerkesztve', "$full_name (ID: $id)");
            flash('success', 'Képviselő adatai frissítve!');
        }
        header('Location: ' . SITE_URL . '/admin/representatives.php');
        exit;
    }

    // ─── FŐADMIN MŰVELETEK (create, delete, reorder) – toggle NINCS ───
    if (is_superadmin()) {
        // Új képviselő felvétele
        if ($action === 'create') {
            $full_name = trim($_POST['full_name'] ?? '');
            $bio       = trim($_POST['bio'] ?? '');
            if ($full_name !== '') {
                $pdo->prepare("INSERT INTO representatives (full_name, bio, active, sort_order) VALUES (?,?,1,999)")
                    ->execute([$full_name, $bio]);
                log_action('Új képviselő létrehozva', $full_name);
                flash('success', 'Új képviselő létrehozva!');
            } else {
                flash('error', 'A név megadása kötelező!');
            }
            header('Location: ' . SITE_URL . '/admin/representatives.php');
            exit;
        }

        // Törlés
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $row = $pdo->prepare('SELECT filename, content_filename FROM representatives WHERE id=?');
            $row->execute([$id]);
            $row = $row->fetch();
            if ($row['filename'] && file_exists(UPLOAD_DIR . $row['filename'])) @unlink(UPLOAD_DIR . $row['filename']);
            if ($row['content_filename'] && file_exists(UPLOAD_DIR . $row['content_filename'])) @unlink(UPLOAD_DIR . $row['content_filename']);
            $pdo->prepare('DELETE FROM representatives WHERE id=?')->execute([$id]);
            log_action('Képviselő törölve', "ID: $id");
            flash('success', 'Képviselő törölve.');
            header('Location: ' . SITE_URL . '/admin/representatives.php');
            exit;
        }

        // Sorrendezés (drag & drop)
        if ($action === 'reorder') {
            $ids = explode(',', $_POST['order'] ?? '');
            foreach ($ids as $i => $rid) {
                $pdo->prepare('UPDATE representatives SET sort_order=? WHERE id=?')->execute([$i+1, (int)$rid]);
            }
            echo 'ok'; exit;
        }
    }
}

// ──────────────────────────────────────────────────────────
// ADATOK BETÖLTÉSE
// ──────────────────────────────────────────────────────────
$hasManagerCol = (bool)$pdo->query("SHOW COLUMNS FROM representatives LIKE 'manager_id'")->fetch();

if (is_superadmin()) {
    // Főadmin minden képviselőt lát
    $stmt = $pdo->prepare('SELECT * FROM representatives ORDER BY sort_order ASC');
    $stmt->execute();
} elseif ($hasManagerCol) {
    // Kampányfőnök csak a saját képviselőit látja
    $stmt = $pdo->prepare('SELECT * FROM representatives WHERE manager_id=? ORDER BY sort_order ASC');
    $stmt->execute([$_SESSION['admin_id']]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM representatives WHERE 1=0');
    $stmt->execute();
    flash('error', 'Nincs beállítva manager hozzárendelés. Kérd a főadmint!');
}
$representatives = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- ── LISTA KÁRTYA ─────────────────────────────────────── -->
<div class="card">
  <div class="card-title">
    👥 Képviselőink (<?= count($representatives) ?> fő)
    <?php if (is_superadmin()): ?>
      <span style="margin-left:auto;font-size:.75rem;color:var(--text-muted);font-weight:400;">Húzd a ⠿ ikont a sorrend változtatásához</span>
    <?php endif; ?>
  </div>

  <?php if (empty($representatives)): ?>
    <p style="color:var(--text-muted);">Nincsenek képviselők.</p>
  <?php else: ?>
  <div id="sortableGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
    <?php foreach ($representatives as $rep): ?>
    <div class="representative-item" data-id="<?= $rep['id'] ?>"
         style="background:var(--navy-mid);border:1px solid var(--border);border-radius:12px;overflow:hidden;<?= !$rep['active'] ? 'opacity:.45;' : '' ?>">
      <div style="position:relative;height:140px;overflow:hidden;">
        <?php if ($rep['filename'] && file_exists(UPLOAD_DIR . $rep['filename'])): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($rep['filename']) ?>?t=<?= time() ?>"
               style="width:100%;height:100%;object-fit:cover;display:block;" alt="<?= htmlspecialchars($rep['full_name']) ?>">
        <?php else: ?>
          <div style="width:100%;height:100%;background:rgba(126,184,212,0.1);display:flex;align-items:center;justify-content:center;color:var(--text-muted);">📷 Nincs kép</div>
        <?php endif; ?>
        <?php if (is_superadmin()): ?>
          <div class="sort-handle" style="position:absolute;top:6px;left:6px;background:rgba(0,0,0,.5);border-radius:4px;padding:2px 6px;cursor:grab;font-size:.9rem;color:#fff;">⠿</div>
        <?php endif; ?>
      </div>
      <div style="padding:.75rem;">
        <div style="font-size:.88rem;font-weight:600;margin-bottom:.5rem;"><?= htmlspecialchars($rep['full_name']) ?></div>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
          <?php if (is_campaign_manager()): ?>
            <!-- Szerkesztés gomb csak kampányfőnöknek -->
            <button onclick="openEdit(<?= htmlspecialchars(json_encode([
                'id'               => $rep['id'],
                'full_name'        => $rep['full_name'],
                'bio'              => $rep['bio'] ?? '',
                'link_url'         => $rep['link_url'] ?? '',
                'extra_text'       => $rep['extra_text'] ?? '',
                'has_image'        => !empty($rep['filename']) && file_exists(UPLOAD_DIR . $rep['filename']),
                'has_content_image'=> !empty($rep['content_filename']) && file_exists(UPLOAD_DIR . $rep['content_filename']),
            ])) ?>)"
                class="btn btn-sm" style="background:rgba(126,184,212,0.1);border:1px solid var(--border);color:var(--blue);" title="Szerkesztés">✏️</button>
          <?php endif; ?>
          <?php if (is_superadmin()): ?>
            <!-- Törlés gomb superadminnak (toggle nincs) -->
            <form method="POST" style="display:inline;" onsubmit="return confirm('Biztosan törlöd ezt a képviselőt?')">
              <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id"     value="<?= $rep['id'] ?>">
              <button class="btn btn-sm btn-danger" title="Törlés">🗑️</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── ÚJ KÉPVISELŐ FELVÉTELE (csak superadmin) ─────────── -->
<?php if (is_superadmin()): ?>
<div class="card">
  <div class="card-title">➕ Új képviselő felvétele</div>
  <form method="POST">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group">
        <label>Teljes név <span style="color:var(--red);">*</span></label>
        <input type="text" name="full_name" class="form-control" required placeholder="Képviselő neve">
      </div>
      <div class="form-group">
        <label>Bio / Bemutatkozás (opcionális)</label>
        <textarea name="bio" class="form-control" rows="2" placeholder="Rövid bemutatkozás..."></textarea>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">➕ Létrehozás</button>
  </form>
</div>
<?php endif; ?>

<!-- ── SZERKESZTÉSI MODAL (csak kampányfőnök) ─────────────── -->
<?php if (is_campaign_manager()): ?>
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:200;align-items:center;justify-content:center;overflow-y:auto;">
  <div style="background:#132040;border:1px solid var(--border);border-radius:16px;padding:2rem;width:100%;max-width:560px;margin:2rem auto;">
    <h3 style="font-family:inherit;margin-bottom:1.5rem;">✏️ Képviselő szerkesztése</h3>
    <form method="POST" enctype="multipart/form-data" id="editRepForm">
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id"     id="editId">

      <div class="form-group">
        <label>Teljes név</label>
        <div style="display:flex;align-items:center;gap:.5rem;">
          <input type="text" id="editName" name="full_name" class="form-control" required>
          <button type="button" class="rep-emoji-btn" data-target="input" data-input="editName" title="Emoji beszúrása">😊</button>
        </div>
      </div>

      <!-- Bio Quill szerkesztő -->
      <div class="form-group">
        <label>Bio / Bemutatkozás</label>
        <div style="position:relative;">
          <div id="bioEditor" style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;min-height:100px;"></div>
          <button type="button" class="rep-emoji-btn" data-target="quill" data-quill="bio" title="Emoji beszúrása" style="position:absolute;top:.35rem;right:.4rem;z-index:10;">😊</button>
        </div>
        <input type="hidden" id="editBio" name="bio">
        <div class="form-hint">Használhatsz formázást (félkövér, dőlt, felsorolás, link).</div>
      </div>

      <div class="form-group">
        <label>Link URL (opcionális)</label>
        <input type="url" id="editUrl" name="link_url" class="form-control" placeholder="https://...">
      </div>

      <div class="form-group">
        <label>További szöveg (extra)</label>
        <div style="position:relative;">
          <div id="extraTextEditor" style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;min-height:120px;"></div>
          <button type="button" class="rep-emoji-btn" data-target="quill" data-quill="extra" title="Emoji beszúrása" style="position:absolute;top:.35rem;right:.4rem;z-index:10;">😊</button>
        </div>
        <input type="hidden" id="editExtra" name="extra_text">
        <div class="form-hint">Gazdag szöveges tartalom a képviselő részletes bemutatásához.</div>
      </div>

      <div class="form-group">
        <label>Profilkép (JPG, PNG, WEBP)</label>
        <div id="currentImageRow" style="display:none;align-items:center;gap:.75rem;margin-bottom:.5rem;">
          <span style="font-size:.82rem;color:var(--text-muted);">✅ Van feltöltött profilkép</span>
          <button type="button" onclick="deleteImage('image')"
                  style="background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.4);color:#e05c6a;border-radius:7px;padding:.25rem .7rem;font-size:.78rem;cursor:pointer;">
            🗑️ Törlés
          </button>
        </div>
        <input type="hidden" name="delete_image" id="deleteImageFlag" value="">
        <input type="file" name="image" class="form-control" accept="image/*">
        <div class="form-hint">Hagyd üresen, ha nem változtatod</div>
      </div>

      <div class="form-group">
        <label>Tartalom kép (opcionális)</label>
        <div id="currentContentImageRow" style="display:none;align-items:center;gap:.75rem;margin-bottom:.5rem;">
          <span style="font-size:.82rem;color:var(--text-muted);">✅ Van feltöltött tartalom kép</span>
          <button type="button" onclick="deleteImage('content_image')"
                  style="background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.4);color:#e05c6a;border-radius:7px;padding:.25rem .7rem;font-size:.78rem;cursor:pointer;">
            🗑️ Törlés
          </button>
        </div>
        <input type="hidden" name="delete_content_image" id="deleteContentImageFlag" value="">
        <input type="file" name="content_image" class="form-control" accept="image/*">
        <div class="form-hint">Extra kép a részletes szöveg mellé</div>
      </div>

      <div style="display:flex;gap:1rem;margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary" style="flex:1;">💾 Mentés</button>
        <button type="button" onclick="closeEdit()" class="btn" style="flex:1;background:rgba(126,184,212,.1);border:1px solid var(--border);color:var(--blue);">Mégse</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
var bioQuill = null, extraQuill = null;
document.addEventListener('DOMContentLoaded', function(){
  if (typeof Quill !== 'undefined') {
    bioQuill = new Quill('#bioEditor', {
      theme: 'snow',
      placeholder: 'Rövid bemutatkozás...',
      modules: { toolbar: [['bold','italic','underline'],[{'list':'ordered'},{'list':'bullet'}],['link'],['clean']] }
    });
    extraQuill = new Quill('#extraTextEditor', {
      theme: 'snow',
      placeholder: 'Ide írd a részletes szöveget...',
      modules: { toolbar: [['bold','italic','underline'],[{'list':'ordered'},{'list':'bullet'}],['link'],['clean']] }
    });
  }
});

function openEdit(rep){
  document.getElementById('editId').value = rep.id;
  document.getElementById('editName').value = rep.full_name;
  if(bioQuill) bioQuill.root.innerHTML = rep.bio || '';
  if(extraQuill) extraQuill.root.innerHTML = rep.extra_text || '';
  document.getElementById('editUrl').value = rep.link_url || '';

  // Kép sorok megjelenítése
  var imgRow = document.getElementById('currentImageRow');
  imgRow.style.display = rep.has_image ? 'flex' : 'none';
  document.getElementById('deleteImageFlag').value = '';

  var cImgRow = document.getElementById('currentContentImageRow');
  cImgRow.style.display = rep.has_content_image ? 'flex' : 'none';
  document.getElementById('deleteContentImageFlag').value = '';

  document.getElementById('editModal').style.display = 'flex';
}
function closeEdit(){ document.getElementById('editModal').style.display = 'none'; }
function deleteImage(type){
  if (type === 'image') {
    document.getElementById('deleteImageFlag').value = '1';
    document.getElementById('currentImageRow').style.display = 'none';
  } else {
    document.getElementById('deleteContentImageFlag').value = '1';
    document.getElementById('currentContentImageRow').style.display = 'none';
  }
}
document.getElementById('editRepForm').addEventListener('submit', function(){
  if(bioQuill) document.getElementById('editBio').value = bioQuill.root.innerHTML;
  if(extraQuill) document.getElementById('editExtra').value = extraQuill.root.innerHTML;
});
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target === this) closeEdit(); });
</script>
<?php endif; ?>

<!-- ── SORRENDEZÉS (drag & drop, csak superadmin) ────────── -->
<?php if (is_superadmin()): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
(function(){
  const grid = document.getElementById('sortableGrid');
  if (grid) {
    new Sortable(grid, {
      handle: '.sort-handle',
      animation: 150,
      onEnd: function() {
        const ids = Array.from(grid.children).map(el => el.dataset.id).join(',');
        fetch('<?= SITE_URL ?>/admin/representatives.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'csrf=<?= csrf_token() ?>&action=reorder&order=' + ids
        });
      }
    });
  }
})();
</script>
<?php endif; ?>


<style>
.rep-emoji-btn {
  flex-shrink: 0;
  background: var(--hover-bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  width: 2.2rem;
  height: 2.2rem;
  font-size: 1.1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
  line-height: 1;
}
.rep-emoji-btn:hover { background: var(--active-bg); }
#rep-emoji-picker {
  position: fixed;
  z-index: 9999;
  background: var(--card, #1e2330);
  border: 1px solid var(--border, #2e3547);
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0,0,0,.45);
  padding: .75rem;
  width: 320px;
  max-height: 340px;
  display: none;
  flex-direction: column;
  gap: .5rem;
}
#rep-emoji-picker.open { display: flex; }
#rep-emoji-search {
  width: 100%;
  padding: .4rem .7rem;
  border-radius: 8px;
  border: 1px solid var(--border, #2e3547);
  background: var(--hover-bg, #252b3b);
  color: inherit;
  font-size: .85rem;
  box-sizing: border-box;
}
#rep-emoji-tabs {
  display: flex;
  gap: .3rem;
  flex-wrap: wrap;
}
#rep-emoji-tabs button {
  background: none;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  padding: .2rem .35rem;
  opacity: .6;
  transition: .15s;
}
#rep-emoji-tabs button.active,
#rep-emoji-tabs button:hover { opacity: 1; border-color: var(--border, #2e3547); background: var(--hover-bg,#252b3b); }
#rep-emoji-grid {
  display: flex;
  flex-wrap: wrap;
  gap: .15rem;
  overflow-y: auto;
  max-height: 220px;
  padding-right: .2rem;
}
#rep-emoji-grid button {
  background: none;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 1.35rem;
  cursor: pointer;
  padding: .2rem;
  transition: .12s;
  line-height: 1;
}
#rep-emoji-grid button:hover { background: var(--hover-bg,#252b3b); border-color: var(--border,#2e3547); }
</style>

<div id="rep-emoji-picker">
  <input id="rep-emoji-search" type="text" placeholder="Keresés kategória szerint...">
  <div id="rep-emoji-tabs"></div>
  <div id="rep-emoji-grid"></div>
</div>

<script>
(function(){
  var EMOJIS = {
    'Arcok':['😀','😁','😂','🤣','😃','😄','😅','😆','😇','😈','😉','😊','😋','😌','😍','🥰','😎','😏','😐','😑','😒','😓','😔','😕','😖','😗','😘','😙','😚','😛','😜','😝','😞','😟','😠','😡','😢','😣','😤','😥','😦','😧','😨','😩','😪','😫','😬','😭','😮','😯','😰','😱','😲','😳','😴','😵','😶','😷','🙁','🙂','🙃','🙄','🤐','🤑','🤒','🤓','🤔','🤕','🤗','🤠','🤡','🤢','🤤','🤥','🤧','🤨','🤩','🤪','🤫','🤬','🤭','🤮','🤯','🥱','🥲','🥳','🥴','🥵','🥶','🥸','🥹','🫠','🫡','🫢','🫣','🫤','🫥'],
    'Kéz':['👋','🤚','🖐️','✋','🖖','👌','🤌','🤏','✌️','🤞','🫰','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','🫵','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🙏','✍️','💅','🤳','💪'],
    'Szívek':['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','💕','💞','💓','💗','💖','💘','💝','💟','♥️'],
    'Csillagok':['⭐','🌟','✨','💫','⚡','🔥','🌈','☀️','🌤️','⛅','🌥️','☁️','🌦️','🌧️','⛈️','🌩️','🌨️','❄️','💨','🌊','🌀','🌙','🌛','🌜','🌚','🌝','🌞','🪐','🌍','🌎','🌏','🌌','🌠','🎇','🎆'],
    'Ünnep':['🎉','🎊','🎈','🎁','🎀','🏆','🥇','🥈','🥉','🏅','🎖️','🎭','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🎷','🎺','🎸','🎻','🎲','🎯','🎳','🎮','🎰'],
    'Természet':['🌱','🌲','🌳','🌴','🌵','🌾','🌿','☘️','🍀','🍁','🍂','🍃','🌺','🌸','🌼','🌻','🌹','🥀','🌷','💐','🍄','🌰','🌊','🏔️','⛰️','🌋','🏕️','🏖️','🏜️','🏝️','🏞️'],
    'Állatok':['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🦇','🐺','🐴','🦄','🐝','🦋','🐢','🐍','🦎','🐙','🐟','🐬','🐳','🦈','🐊','🐘','🦒','🐕','🐈','🐇','🦝','🦔'],
    'Étel':['🍕','🍔','🌮','🌯','🥙','🥚','🍳','🥘','🍲','🥗','🍿','🍱','🍣','🍜','🍝','🍦','🍧','🍨','🍩','🍪','🎂','🍰','🧁','🍫','🍬','🍭','☕','🍵','🍶','🍾','🍷','🍸','🍹','🍺','🍻','🥂'],
    'Sport':['⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🎱','🏓','🏸','⛳','🥊','🥋','🎽','🛹','⛷️','🏋️','🤸','🏄','🏊','🚴','🏆','🥇'],
    'Politika':['🗳️','🏛️','📜','⚖️','🤝','🕊️','🗣️','📢','📣','🔔','🏴','🚩','🏳️','🌐','🗺️','🏙️','🌆','🌇','🏟️','📊','📈','📉','💼','🖊️','📋','📌','📍','✅','☑️','🔑','🗝️'],
    'Szimbólum':['❗','❓','‼️','⁉️','✅','❌','⭕','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🔺','🔻','🔷','🔶','🔹','🔸','▶️','⏸️','🔔','🔕','💡','🔒','🔓','🔑','⚙️','🛠️','📌','📍'],
  };

  var cats = Object.keys(EMOJIS);
  var currentCat = cats[0];
  var currentTarget = null;

  var picker = document.getElementById('rep-emoji-picker');
  var grid   = document.getElementById('rep-emoji-grid');
  var search = document.getElementById('rep-emoji-search');
  var tabs   = document.getElementById('rep-emoji-tabs');

  cats.forEach(function(cat){
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.title = cat;
    btn.textContent = EMOJIS[cat][0];
    if (cat === currentCat) btn.classList.add('active');
    btn.addEventListener('click', function(){
      currentCat = cat;
      tabs.querySelectorAll('button').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      search.value = '';
      renderGrid(EMOJIS[cat]);
    });
    tabs.appendChild(btn);
  });

  function renderGrid(list){
    grid.innerHTML = '';
    list.forEach(function(emoji){
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = emoji;
      btn.addEventListener('click', function(){ insertEmoji(emoji); });
      grid.appendChild(btn);
    });
  }

  function insertEmoji(emoji){
    if (!currentTarget) return;
    if (currentTarget.type === 'input'){
      var inp = currentTarget.el;
      var s = inp.selectionStart, e = inp.selectionEnd;
      inp.value = inp.value.slice(0, s) + emoji + inp.value.slice(e);
      inp.selectionStart = inp.selectionEnd = s + emoji.length;
      inp.focus();
    } else {
      var q = currentTarget.quill;
      if (q){
        var range = q.getSelection(true);
        var idx = range ? range.index : q.getLength();
        q.insertText(idx, emoji);
        q.setSelection(idx + emoji.length);
      }
    }
    closePicker();
  }

  function closePicker(){
    picker.classList.remove('open');
    currentTarget = null;
  }

  function openPicker(btn, target){
    currentTarget = target;
    search.value = '';
    renderGrid(EMOJIS[currentCat]);
    picker.classList.add('open');
    var rect = btn.getBoundingClientRect();
    var top = rect.bottom + 6;
    var left = rect.left;
    var pw = 320, ph = 340;
    if (left + pw > window.innerWidth - 8) left = window.innerWidth - pw - 8;
    if (top + ph > window.innerHeight - 8) top = rect.top - ph - 6;
    if (left < 8) left = 8;
    picker.style.top  = top + 'px';
    picker.style.left = left + 'px';
  }

  document.addEventListener('click', function(e){
    if (!picker.contains(e.target) && !e.target.classList.contains('rep-emoji-btn')){
      closePicker();
    }
  }, true);

  search.addEventListener('input', function(){
    var q = search.value.trim().toLowerCase();
    if (!q){ renderGrid(EMOJIS[currentCat]); return; }
    var matches = [];
    Object.entries(EMOJIS).forEach(function(entry){
      if (entry[0].toLowerCase().includes(q)){
        entry[1].forEach(function(e){ if(!matches.includes(e)) matches.push(e); });
      }
    });
    if (matches.length === 0){
      Object.values(EMOJIS).forEach(function(emojis){
        emojis.forEach(function(e){ if(!matches.includes(e)) matches.push(e); });
      });
    }
    renderGrid(matches);
  });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.rep-emoji-btn');
    if (!btn) return;
    e.stopPropagation();
    var type = btn.dataset.target;
    if (type === 'input'){
      var inp = document.getElementById(btn.dataset.input);
      if (inp) openPicker(btn, {type:'input', el: inp});
    } else if (type === 'quill'){
      var q = btn.dataset.quill === 'bio' ? bioQuill : extraQuill;
      if (q) openPicker(btn, {type:'quill', quill: q});
    }
  });

  renderGrid(EMOJIS[currentCat]);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>