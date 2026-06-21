<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title  = 'Galéria kezelése';
$active_page = 'gallery';
$pdo = db();

// FELTÖLTÉS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check();

    if ($_POST['action'] === 'upload' && !empty($_FILES['images'])) {
        $files   = $_FILES['images'];
        $count   = count($files['name']);
        $success = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > MAX_FILE_SIZE)     continue;
            $err = validate_image_upload(['error'=>$files['error'][$i],'size'=>$files['size'][$i],'tmp_name'=>$files['tmp_name'][$i]]);
            if ($err) continue;
            $filename = safe_upload_filename('img_', $files['tmp_name'][$i]);
            $dest     = UPLOAD_DIR . $filename;
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $alt     = htmlspecialchars(pathinfo($files['name'][$i], PATHINFO_FILENAME));
                $caption = trim($_POST['captions'][$i] ?? '');
                $span2   = !empty($_POST['span2'][$i]) ? 1 : 0;
                $stmt_order = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM gallery');
                $stmt_order->execute();
                $order = (int)$stmt_order->fetchColumn();
                $pdo->prepare('INSERT INTO gallery (filename,alt_text,caption,span2,sort_order) VALUES (?,?,?,?,?)')
                    ->execute([$filename, $alt, $caption, $span2, $order]);
                $success++;
            }
        }
        log_action("Galéria feltöltés", "$success kép feltöltve");
        flash('success', "$success kép sikeresen feltöltve!");
        header('Location: ' . SITE_URL . '/admin/gallery.php'); exit;
    }

    if ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE gallery SET active = 1 - active WHERE id=?')->execute([$id]);
        header('Location: ' . SITE_URL . '/admin/gallery.php'); exit;
    }

    if ($_POST['action'] === 'delete') {
        $id  = (int)$_POST['id'];
        $row = $pdo->prepare('SELECT filename FROM gallery WHERE id=?');
        $row->execute([$id]);
        $file = $row->fetchColumn();
        if ($file) {
            @unlink(UPLOAD_DIR . $file);
            $pdo->prepare('DELETE FROM gallery WHERE id=?')->execute([$id]);
        }
        log_action("Galéria törlés", "ID: $id");
        flash('success', 'Kép törölve.');
        header('Location: ' . SITE_URL . '/admin/gallery.php'); exit;
    }

    if ($_POST['action'] === 'edit') {
        $id      = (int)$_POST['id'];
        $alt     = trim($_POST['alt_text']);
        $caption = trim($_POST['caption']);
        $span2   = !empty($_POST['span2']) ? 1 : 0;
        $pdo->prepare('UPDATE gallery SET alt_text=?,caption=?,span2=? WHERE id=?')
            ->execute([$alt, $caption, $span2, $id]);
        flash('success', 'Kép adatai frissítve.');
        header('Location: ' . SITE_URL . '/admin/gallery.php'); exit;
    }

    if ($_POST['action'] === 'reorder') {
        $ids = explode(',', $_POST['order'] ?? '');
        foreach ($ids as $i => $id) {
            $pdo->prepare('UPDATE gallery SET sort_order=? WHERE id=?')->execute([$i+1, (int)$id]);
        }
        echo 'ok'; exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM gallery ORDER BY sort_order, id');
$stmt->execute();
$images = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<style>
/* ── Galéria kártyák ── */
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}
.gallery-card {
  background: var(--navy-mid);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  transition: box-shadow .2s;
  display: flex;
  flex-direction: column;
}
.gallery-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.3); }
.gallery-card.inactive { opacity: .45; }

.gallery-thumb-wrap {
  position: relative;
  width: 100%;
  padding-top: 66%; /* 3:2 arány */
  overflow: hidden;
  cursor: pointer;
}
.gallery-thumb-wrap img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .3s;
}
.gallery-thumb-wrap:hover img { transform: scale(1.04); }
.gallery-thumb-wrap .zoom-hint {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  opacity: 0;
  transition: .2s;
}
.gallery-thumb-wrap:hover .zoom-hint { opacity: 1; background: rgba(0,0,0,.25); }

.gallery-card-body {
  padding: .75rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: .5rem;
}
.gallery-caption {
  font-size: .83rem;
  color: var(--text);
  border-top: 1px solid var(--border);
  padding-top: .45rem;
  min-height: 1.5rem;
  word-break: break-word;
}
.gallery-caption.empty { opacity: .35; font-size: .75rem; font-style: italic; }

/* ── Lightbox ── */
#lightbox {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.92);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  flex-direction: column;
}
#lightbox.open { display: flex; }

#lightbox-img-wrap {
  position: relative;
  max-width: 92vw;
  max-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
#lightbox-img {
  max-width: 88vw;
  max-height: 78vh;
  border-radius: 10px;
  box-shadow: 0 8px 40px rgba(0,0,0,.6);
  object-fit: contain;
}
#lightbox-caption {
  margin-top: .9rem;
  color: rgba(255,255,255,.85);
  font-size: .9rem;
  text-align: center;
  max-width: 80vw;
  min-height: 1.4rem;
}
#lightbox-counter {
  color: rgba(255,255,255,.45);
  font-size: .78rem;
  margin-top: .3rem;
}
.lb-btn {
  position: fixed;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(255,255,255,.12);
  border: none;
  color: #fff;
  font-size: 1.6rem;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
  z-index: 1001;
}
.lb-btn:hover { background: rgba(255,255,255,.25); }
#lb-prev { left: 12px; }
#lb-next { right: 12px; }
#lb-close {
  position: fixed;
  top: 14px;
  right: 16px;
  background: rgba(255,255,255,.12);
  border: none;
  color: #fff;
  font-size: 1.3rem;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1001;
}
#lb-close:hover { background: rgba(255,255,255,.25); }

/* QR kattintható */
.qr-img-wrap {
  cursor: pointer;
  display: inline-block;
  border-radius: 10px;
  overflow: hidden;
  transition: box-shadow .2s;
}
.qr-img-wrap:hover { box-shadow: 0 0 0 3px var(--blue); }
</style>

<div class="card">
  <div class="card-title">📤 Képek feltöltése</div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="upload">
    <div class="form-group">
      <label>Képfájlok (JPG, PNG, GIF, WEBP – max 8MB/db)</label>
      <input type="file" name="images[]" class="form-control" multiple accept="image/*" id="fileInput">
      <div class="form-hint">Több képet is kijelölhetsz egyszerre (Ctrl+kattintás)</div>
    </div>
    <div id="previewArea" style="display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem;"></div>
    <button type="submit" class="btn btn-primary">📤 Feltöltés</button>
  </form>
</div>

<div class="card">
  <div class="card-title">🖼️ Galéria képek (<?= count($images) ?> db)
    <span style="margin-left:auto;font-size:.75rem;color:var(--text-muted);font-weight:400;">Húzd át a sorrendet · kattints a nagyításhoz</span>
  </div>

  <?php if (empty($images)): ?>
    <p style="color:var(--text-muted);">Még nincs feltöltött kép.</p>
  <?php else: ?>
  <div id="sortableGrid" class="gallery-grid">
    <?php foreach ($images as $idx => $img): ?>
    <div class="gallery-card <?= !$img['active'] ? 'inactive' : '' ?>"
         data-id="<?= $img['id'] ?>">

      <!-- Kattintható kép -->
      <div class="gallery-thumb-wrap"
           onclick="openLightbox(<?= $idx ?>)"
           title="Kattints a nagyításhoz">
        <img src="<?= UPLOAD_URL . htmlspecialchars($img['filename']) ?>"
             alt="<?= htmlspecialchars($img['alt_text']) ?>"
             loading="lazy">
        <div class="zoom-hint">🔍</div>
        <div style="position:absolute;top:6px;right:6px;display:flex;gap:4px;">
          <span style="background:rgba(0,0,0,.65);color:#fff;border-radius:4px;padding:2px 6px;font-size:.7rem;">
            #<?= $img['sort_order'] ?>
          </span>
          <?php if ($img['span2']): ?>
            <span style="background:rgba(126,184,212,.7);color:#0c1a3a;border-radius:4px;padding:2px 6px;font-size:.7rem;">2×</span>
          <?php endif; ?>
        </div>
        <div class="sort-handle" style="position:absolute;top:6px;left:6px;background:rgba(0,0,0,.5);
             border-radius:4px;padding:2px 6px;cursor:grab;font-size:.9rem;">⠿</div>
      </div>

      <div class="gallery-card-body">
        <!-- Felirat -->
        <?php if (!empty($img['caption'])): ?>
          <div class="gallery-caption">📝 <?= htmlspecialchars($img['caption']) ?></div>
        <?php else: ?>
          <div class="gallery-caption empty">– nincs felirat –</div>
        <?php endif; ?>

        <!-- Gombok -->
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
          <button onclick="openEdit(<?= $img['id'] ?>,'<?= htmlspecialchars(addslashes($img['alt_text'])) ?>','<?= htmlspecialchars(addslashes($img['caption'])) ?>',<?= $img['span2'] ?>)"
                  class="btn btn-sm" style="background:rgba(126,184,212,0.1);border:1px solid var(--border);color:var(--blue);">✏️</button>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id"     value="<?= $img['id'] ?>">
            <button class="btn btn-sm <?= $img['active'] ? 'btn-success' : 'btn-danger' ?>"
                    title="<?= $img['active'] ? 'Elrejtés' : 'Megjelenítés' ?>">
              <?= $img['active'] ? '👁️' : '🙈' ?>
            </button>
          </form>
          <form method="POST" style="display:inline;"
                onsubmit="return confirm('Biztosan törlöd ezt a képet?')">
            <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id"     value="<?= $img['id'] ?>">
            <button class="btn btn-sm btn-danger">🗑️</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- EDIT MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;padding:2rem;width:100%;max-width:440px;margin:1rem;">
    <h3 style="font-family:inherit;margin-bottom:1.5rem;color:var(--text);">✏️ Kép szerkesztése</h3>
    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id"     id="editId">
      <div class="form-group">
        <label>Alt szöveg (akadálymentesség)</label>
        <input type="text" name="alt_text" id="editAlt" class="form-control">
      </div>
      <div class="form-group">
        <label>Felirat (opcionális)</label>
        <input type="text" name="caption" id="editCaption" class="form-control" placeholder="Pl.: Megnyitó 2024. október">
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:.75rem;">
        <label class="toggle">
          <input type="checkbox" name="span2" id="editSpan2" value="1">
          <span class="toggle-slider"></span>
        </label>
        <span style="font-size:.88rem;">Dupla szélesség (2×)</span>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1rem;">
        <button type="submit" class="btn btn-primary">💾 Mentés</button>
        <button type="button" onclick="closeEdit()" class="btn"
                style="background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-muted);">
          Mégse
        </button>
      </div>
    </form>
  </div>
</div>

<!-- LIGHTBOX -->
<div id="lightbox">
  <button id="lb-close" onclick="closeLightbox()" title="Bezárás">✕</button>
  <button class="lb-btn" id="lb-prev" onclick="lbNav(-1)" title="Előző">‹</button>
  <div id="lightbox-img-wrap">
    <img id="lightbox-img" src="" alt="">
  </div>
  <div id="lightbox-caption"></div>
  <div id="lightbox-counter"></div>
  <button class="lb-btn" id="lb-next" onclick="lbNav(1)" title="Következő">›</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
// ── Képadatok a lightboxhoz ──
const galleryData = <?= json_encode(array_map(fn($img) => [
  'src'     => UPLOAD_URL . $img['filename'],
  'caption' => $img['caption'] ?? '',
  'alt'     => $img['alt_text'] ?? '',
], $images)) ?>;

let lbIndex = 0;

function openLightbox(idx) {
  lbIndex = idx;
  renderLightbox();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
function renderLightbox() {
  const item = galleryData[lbIndex];
  document.getElementById('lightbox-img').src     = item.src;
  document.getElementById('lightbox-img').alt     = item.alt;
  document.getElementById('lightbox-caption').textContent = item.caption;
  document.getElementById('lightbox-counter').textContent = (lbIndex + 1) + ' / ' + galleryData.length;
}
function lbNav(dir) {
  lbIndex = (lbIndex + dir + galleryData.length) % galleryData.length;
  renderLightbox();
}

// Billentyűzet navigáció
document.addEventListener('keydown', function(e) {
  const lb = document.getElementById('lightbox');
  if (!lb.classList.contains('open')) return;
  if (e.key === 'ArrowLeft')  lbNav(-1);
  if (e.key === 'ArrowRight') lbNav(1);
  if (e.key === 'Escape')     closeLightbox();
});

// Háttérre kattintva bezár
document.getElementById('lightbox').addEventListener('click', function(e) {
  if (e.target === this || e.target === document.getElementById('lightbox-img-wrap')) closeLightbox();
});

// ── Feltöltés előnézet ──
document.getElementById('fileInput').addEventListener('change', function() {
  const area = document.getElementById('previewArea');
  area.innerHTML = '';
  for (const file of this.files) {
    const url = URL.createObjectURL(file);
    const div = document.createElement('div');
    div.style.cssText = 'text-align:center;font-size:.72rem;color:var(--text-muted);';
    div.innerHTML = `<img src="${url}" style="width:90px;height:70px;object-fit:cover;border-radius:6px;border:1px solid var(--border);display:block;margin-bottom:4px;">
      ${file.name.substring(0,14)}`;
    area.appendChild(div);
  }
});

// ── Drag-and-drop sorrend ──
Sortable.create(document.getElementById('sortableGrid'), {
  handle: '.sort-handle',
  animation: 150,
  onEnd: function() {
    const ids = [...document.querySelectorAll('.gallery-card')].map(el => el.dataset.id).join(',');
    const fd = new FormData();
    fd.append('action','reorder');
    fd.append('order', ids);
    fd.append('csrf',  '<?= csrf_token() ?>');
    fetch('', { method:'POST', body: fd });
  }
});

// ── Edit modal ──
function openEdit(id, alt, caption, span2) {
  document.getElementById('editId').value      = id;
  document.getElementById('editAlt').value     = alt;
  document.getElementById('editCaption').value = caption;
  document.getElementById('editSpan2').checked = span2 == 1;
  document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() {
  document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
