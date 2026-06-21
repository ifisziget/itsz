<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title  = 'Logó, QR kód & Tab ikon';
$active_page = 'assets';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $type    = $_POST['type'] ?? '';

    if (in_array($type, ['logo','qr','favicon']) && !empty($_FILES['file']['name'])) {
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Feltöltési hiba.');
        } elseif (!in_array($file['type'], $allowed)) {
            flash('error', 'Csak kép fájl tölthető fel!');
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            flash('error', 'A fájl mérete meghaladja a 8 MB-os határt.');
        } else {
            $uploadErr = validate_image_upload($file);
            if ($uploadErr) {
                flash('error', $uploadErr);
                header('Location: ' . SITE_URL . '/admin/assets.php'); exit;
            }
            $ext      = safe_image_ext($file['tmp_name']);
            $assetDir = dirname(UPLOAD_DIR);

            // favicon mindig PNG-ként mentjük (Pillow-val körbe vágjuk ha elérhető)
            if ($type === 'favicon') {
                // Töröljük a régit
                foreach (glob($assetDir . '/favicon.*') as $old) { @unlink($old); }
                $dest = $assetDir . '/favicon.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Megpróbáljuk körre vágni PHP GD-vel
                    $circle = make_circle_favicon($dest, $assetDir . '/favicon.png');
                    if ($circle && $dest !== $assetDir . '/favicon.png') @unlink($dest);
                    log_action('Tab ikon feltöltve', $file['name']);
                    flash('success', 'Tab ikon sikeresen frissítve!');
                } else {
                    flash('error', 'Nem sikerült a fájl mentése.');
                }
            } else {
                foreach (glob($assetDir . '/' . $type . '.*') as $old) { @unlink($old); }
                $dest = $assetDir . '/' . $type . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    log_action("$type feltöltve", $file['name']);
                    flash('success', strtoupper($type) . ' sikeresen frissítve!');
                } else {
                    flash('error', 'Nem sikerült a fájl mentése.');
                }
            }
        }
    }
    header('Location: ' . SITE_URL . '/admin/assets.php');
    exit;
}

// Kör alakú favicon generálás GD-vel
function make_circle_favicon(string $src, string $dest): bool {
    if (!extension_loaded('gd')) return false;
    $info = @getimagesize($src);
    if (!$info) return false;

    switch ($info[2]) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src);  break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src);  break;
        default: return false;
    }
    if (!$img) return false;

    $w = imagesx($img); $h = imagesy($img);
    $m = min($w, $h);
    $size = 256;

    // Négyzetre vágás középről
    $sq = imagecreatetruecolor($m, $m);
    imagecopy($sq, $img, 0, 0, ($w-$m)/2, ($h-$m)/2, $m, $m);
    imagedestroy($img);

    // Átméretezés
    $rs = imagecreatetruecolor($size, $size);
    imagecopyresampled($rs, $sq, 0,0,0,0, $size,$size, $m,$m);
    imagedestroy($sq);

    // Kör maszk
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $trans = imagecolorallocatealpha($out, 0,0,0,127);
    imagefill($out, 0, 0, $trans);

    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            $cx = $x - $size/2; $cy = $y - $size/2;
            if ($cx*$cx + $cy*$cy <= ($size/2)*($size/2)) {
                $col = imagecolorat($rs, $x, $y);
                $r = ($col >> 16) & 0xFF;
                $g = ($col >> 8)  & 0xFF;
                $b = $col & 0xFF;
                imagesetpixel($out, $x, $y, imagecolorallocate($out, $r, $g, $b));
            }
        }
    }
    imagedestroy($rs);
    $ok = imagepng($out, $dest);
    imagedestroy($out);
    return $ok;
}

function find_asset(string $name): ?string {
    foreach (['png','jpg','jpeg','webp','gif'] as $ext) {
        $path = dirname(UPLOAD_DIR) . "/$name.$ext";
        if (file_exists($path)) return SITE_URL . "/assets/$name.$ext";
    }
    return null;
}

$logo_url    = find_asset('logo');
$qr_url      = find_asset('qr');
$favicon_url = find_asset('favicon');
include __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">

  <!-- LOGÓ -->
  <style>
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
    <div class="card-title">🔵 Logó</div>
    <?php if ($logo_url): ?>
      <img src="<?= $logo_url ?>?t=<?= time() ?>" class="img-preview-lg"
           style="width:110px;height:110px;border-radius:50%;object-fit:cover;background:#fff;">
      <p style="font-size:.8rem;color:var(--text-muted);margin:.6rem 0 1rem;">Jelenlegi logó</p>
    <?php else: ?>
      <p style="color:var(--text-muted);margin-bottom:1rem;">Nincs logó feltöltve.</p>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="type" value="logo">
      <div class="form-group">
        <label>Új logó (JPG, PNG, WEBP)</label>
        <input type="file" name="file" class="form-control" accept="image/*" required>
        <div class="form-hint">Kör alakú kép ajánlott. A régi törlődik.</div>
      </div>
      <button type="submit" class="btn btn-primary">📤 Feltöltés</button>
    </form>
  </div>

  <!-- QR KÓD -->
  <div class="card">
    <div class="card-title">📱 QR kód</div>
    <?php if ($qr_url): ?>
      <img src="<?= $qr_url ?>?t=<?= time() ?>" class="img-preview-lg"
           style="width:110px;height:110px;object-fit:cover;background:#fff;border-radius:8px;padding:5px;">
      <p style="font-size:.8rem;color:var(--text-muted);margin:.6rem 0 1rem;">Jelenlegi QR kód</p>
    <?php else: ?>
      <p style="color:var(--text-muted);margin-bottom:1rem;">Nincs QR kód feltöltve.</p>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="type" value="qr">
      <div class="form-group">
        <label>Új QR kód (JPG, PNG, WEBP)</label>
        <input type="file" name="file" class="form-control" accept="image/*" required>
        <div class="form-hint">A régi QR kód törlődik.</div>
      </div>
      <button type="submit" class="btn btn-primary">📤 Feltöltés</button>
    </form>
  </div>

  <!-- TAB IKON (FAVICON) -->
  <div class="card">
    <div class="card-title">🌐 Tab ikon</div>
    <?php if ($favicon_url): ?>
      <img src="<?= $favicon_url ?>?t=<?= time() ?>"
           style="width:64px;height:64px;border-radius:50%;object-fit:cover;
                  background:#fff;border:1px solid var(--border);display:block;">
      <p style="font-size:.8rem;color:var(--text-muted);margin:.6rem 0 1rem;">
        Ez jelenik meg a böngésző fülén
      </p>
    <?php else: ?>
      <p style="color:var(--text-muted);margin-bottom:1rem;">Nincs tab ikon feltöltve.</p>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="type" value="favicon">
      <div class="form-group">
        <label>Tab ikon feltöltése (JPG, PNG, WEBP)</label>
        <input type="file" name="file" class="form-control" accept="image/*" required>
        <div class="form-hint">
          Automatikusan kör alakra lesz vágva, és minden oldalon megjelenik a fülön.
          A régi törlődik.
        </div>
      </div>
      <button type="submit" class="btn btn-primary">📤 Feltöltés</button>
    </form>
  </div>


</div>

<?php include __DIR__ . '/includes/footer.php'; ?>