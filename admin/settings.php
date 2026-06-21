<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title  = 'Évszakok & Témák';
$active_page = 'settings';

$seasons_opts = [
  'auto'    => ['emoji'=>'🔄','label'=>'Automatikus'],
  'default' => ['emoji'=>'🏠','label'=>'Alap téma'],
  'winter'  => ['emoji'=>'❄️','label'=>'Tél'],
  'spring'  => ['emoji'=>'🌸','label'=>'Tavasz'],
  'summer'  => ['emoji'=>'🌞','label'=>'Nyár'],
  'autumn'  => ['emoji'=>'🍂','label'=>'Ősz'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = [
        'active_season'   => trim($_POST['active_season']   ?? 'auto'),
        'winter_start'    => trim($_POST['winter_start']    ?? '12-01'),
        'spring_start'    => trim($_POST['spring_start']    ?? '03-01'),
        'summer_start'    => trim($_POST['summer_start']    ?? '06-01'),
        'autumn_start'    => trim($_POST['autumn_start']    ?? '09-01'),
        'effects_enabled' => isset($_POST['effects_enabled']) ? '1' : '0',
        'xmas_enabled'    => isset($_POST['xmas_enabled'])    ? '1' : '0',
        'easter_enabled'  => isset($_POST['easter_enabled'])  ? '1' : '0',
    ];
    foreach ($fields as $k => $v) {
        $exists = db()->prepare('SELECT id FROM site_content WHERE section=? AND key_name=?');
        $exists->execute(['seasons', $k]);
        if ($exists->fetch()) {
            db()->prepare('UPDATE site_content SET value=? WHERE section=? AND key_name=?')->execute([$v,'seasons',$k]);
        } else {
            db()->prepare('INSERT INTO site_content (section,key_name,label,value,type,sort_order) VALUES (?,?,?,?,?,?)')->execute(['seasons',$k,$k,$v,'text',0]);
        }
    }
    log_action('Évszak beállítások mentve');
    flash('success', 'Beállítások mentve!');
    header('Location: ' . SITE_URL . '/admin/settings.php');
    exit;
}

$seasons = get_section('seasons');

function detect_auto(array $s): string {
    $today = date('m-d');
    $wi = $s['winter_start'] ?? '12-01';
    $sp = $s['spring_start'] ?? '03-01';
    $su = $s['summer_start'] ?? '06-01';
    $au = $s['autumn_start'] ?? '09-01';
    if ($today >= $wi || $today < $sp) return 'winter';
    if ($today >= $sp && $today < $su) return 'spring';
    if ($today >= $su && $today < $au) return 'summer';
    return 'autumn';
}

$active   = $seasons['active_season'] ?? 'auto';
$auto_now = detect_auto($seasons);
$current  = $active === 'auto' ? $auto_now : $active;

include __DIR__ . '/includes/header.php';
?>

<!-- AKTÍV TÉMA KÁRTYÁK -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
  <?php
  $card_info = [
    'default' => ['emoji'=>'🏠','label'=>'Alap téma','color'=>'var(--blue)','desc'=>'Az eredeti TISZA kék design'],
    'winter'  => ['emoji'=>'❄️','label'=>'Tél','color'=>'#a8d8ff','desc'=>'Havas, kékes, karácsonyos'],
    'spring'  => ['emoji'=>'🌸','label'=>'Tavasz','color'=>'#5cbf6a','desc'=>'Zöld, virágos, húsvétos'],
    'summer'  => ['emoji'=>'🌞','label'=>'Nyár','color'=>'#ffb830','desc'=>'Napos, meleg, arany'],
    'autumn'  => ['emoji'=>'🍂','label'=>'Ősz','color'=>'#e8843a','desc'=>'Meleg barna, narancs'],
    'auto'    => ['emoji'=>'🔄','label'=>'Auto','color'=>'var(--text-muted)','desc'=>"Most: $auto_now"],
  ];
  foreach ($card_info as $sk => $si):
    $is_cur = $current === $sk || ($sk === 'auto' && $active === 'auto');
    $is_active_setting = $active === $sk;
  ?>
  <div style="background:var(--card);border:<?= $is_active_setting?"2px solid {$si['color']}":"1px solid var(--border)" ?>;
       border-radius:14px;padding:1.25rem;text-align:center;position:relative;
       transition:.2s;">
    <?php if ($is_active_setting): ?>
      <div style="position:absolute;top:.5rem;right:.6rem;background:<?= $si['color'] ?>;color:#fff;
                  font-size:.65rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;">AKTÍV</div>
    <?php endif; ?>
    <div style="font-size:2rem;"><?= $si['emoji'] ?></div>
    <div style="font-family:'Syne',sans-serif;font-weight:700;margin:.4rem 0 .2rem;
                color:<?= $si['color'] ?>;"><?= $si['label'] ?></div>
    <div style="font-size:.75rem;color:var(--text-muted);"><?= $si['desc'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<form method="POST">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

  <!-- AKTÍV TÉMA VÁLASZTÓ -->
  <div class="card">
    <div class="card-title">🎨 Aktív téma választása</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.5rem;">
      <?php foreach ($seasons_opts as $val => $info): ?>
      <label style="cursor:pointer;">
        <input type="radio" name="active_season" value="<?= $val ?>"
               <?= $active===$val?'checked':'' ?> class="season-radio" style="display:none;">
        <div class="season-opt" data-val="<?= $val ?>"
             style="display:flex;align-items:center;gap:.6rem;padding:.8rem 1rem;border-radius:10px;
                    border:2px solid <?= $active===$val?'var(--blue)':'var(--border)' ?>;
                    font-size:.88rem;font-weight:600;transition:.2s;cursor:pointer;
                    background:<?= $active===$val?'var(--active-bg)':'var(--hover-bg)' ?>;">
          <span style="font-size:1.2rem;"><?= $info['emoji'] ?></span>
          <?= $info['label'] ?>
          <?php if ($val==='auto'): ?>
            <span style="font-size:.7rem;color:var(--text-muted);margin-left:auto;">(most: <?= $auto_now ?>)</span>
          <?php endif; ?>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- ÜTEMEZÉS -->
    <div style="background:var(--hover-bg);border:1px solid var(--border);border-radius:10px;padding:1.25rem;margin-bottom:1.25rem;">
      <div style="font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;color:var(--blue);
                  letter-spacing:.1em;text-transform:uppercase;margin-bottom:1rem;">
        📅 Automatikus ütemezés – mikor kezdődjön melyik évszak (HH-NN)
      </div>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr;">
        <?php
        $sched = [
          ['winter_start','❄️ Tél','12-01'],
          ['spring_start','🌸 Tavasz','03-01'],
          ['summer_start','🌞 Nyár','06-01'],
          ['autumn_start','🍂 Ősz','09-01'],
        ];
        foreach ($sched as [$name,$label,$def]): ?>
        <div class="form-group" style="margin:0;">
          <label><?= $label ?></label>
          <input type="text" name="<?= $name ?>" class="form-control"
                 value="<?= htmlspecialchars($seasons[$name] ?? $def) ?>" placeholder="<?= $def ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- KAPCSOLÓK -->
    <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.5rem;">
      <?php
      $toggles = [
        ['effects_enabled', '✨ Szezonális részecske effektek (hó, virágok, levelek...)'],
        ['xmas_enabled',    '🎄 Karácsony visszaszámlálás (dec. 25. előtt 60 napig)'],
        ['easter_enabled',  '🐣 Húsvét visszaszámlálás (húsvét előtt 40 napig)'],
      ];
      foreach ($toggles as [$name,$label]): ?>
      <label style="display:flex;align-items:center;gap:.75rem;cursor:pointer;padding:.75rem 1rem;
                    border-radius:10px;border:1px solid var(--border);background:var(--hover-bg);">
        <label class="toggle">
          <input type="checkbox" name="<?= $name ?>" value="1"
                 <?= ($seasons[$name]??'1')==='1'?'checked':'' ?>>
          <span class="toggle-slider"></span>
        </label>
        <span><?= $label ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary">💾 Mentés</button>
  </div>
</form>

<script>
document.querySelectorAll('.season-radio').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.season-opt').forEach(o => {
      const sel = o.dataset.val === r.value;
      o.style.borderColor = sel ? 'var(--blue)' : 'var(--border)';
      o.style.background  = sel ? 'var(--active-bg)' : 'var(--hover-bg)';
    });
  });
});
document.querySelectorAll('.season-opt').forEach(o => {
  o.addEventListener('click', () => {
    const r = document.querySelector(`.season-radio[value="${o.dataset.val}"]`);
    if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>