<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$section_map = [
  'hero'    => ['label'=>'Hero szekció',  'icon'=>'🏠'],
  'about'   => ['label'=>'Rólunk',        'icon'=>'ℹ️'],
  'goals'   => ['label'=>'Céljaink',      'icon'=>'🎯'],
  'join'    => ['label'=>'Csatlakozás',   'icon'=>'🚀'],
  'contact' => ['label'=>'Kapcsolat',     'icon'=>'📬'],
  'footer'  => ['label'=>'Footer',        'icon'=>'📄'],
];

$section = $_GET['section'] ?? 'hero';
if (!array_key_exists($section, $section_map)) $section = 'hero';
$page_title  = $section_map[$section]['label'];
$active_page = $section;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  foreach ($_POST['fields'] ?? [] as $key => $value) {
    $stmt = db()->prepare('UPDATE site_content SET value=? WHERE section=? AND key_name=?');
    $stmt->execute([trim($value), $section, $key]);
  }
  log_action("Tartalom frissítve: $section");
  flash('success', 'Tartalom sikeresen mentve!');
  header('Location: ' . SITE_URL . '/admin/content.php?section=' . $section);
  exit;
}

if ($section === 'footer') {
  // Ensure all developer fields exist
  $dev_defaults = [
    ['developer_name', 'Webfejlesztő neve',           'Kertvéllesy László Zsolt',                          'text',  10],
    ['developer_url',  'Webfejlesztő linkje (email vagy https://)', 'mailto:kertvellesy.laszlo.zsolt@gmail.com', 'url', 11],
    ['developer_icon', 'Webfejlesztő ikon (emoji)',   '💻',                                                 'text',  12],
    ['developer_show', 'Webfejlesztő megjelenítése',  '1',                                                  'text',  13],
  ];
  foreach ($dev_defaults as [$k, $lbl, $v, $t, $o]) {
    $chk = db()->prepare('SELECT COUNT(*) FROM site_content WHERE section=? AND key_name=?');
    $chk->execute(['footer', $k]);
    if (!$chk->fetchColumn()) {
      db()->prepare('INSERT INTO site_content (section,key_name,label,value,type,sort_order) VALUES (?,?,?,?,?,?)')
        ->execute(['footer',$k,$lbl,$v,$t,$o]);
    }
  }
}

$exclude_keys = ($section === 'footer') ? ['num_developers'] : [];
if ($exclude_keys) {
  $placeholders = implode(',', array_fill(0, count($exclude_keys), '?'));
  $stmt = db()->prepare("SELECT * FROM site_content WHERE section=? AND key_name NOT IN ($placeholders) ORDER BY sort_order");
  $stmt->execute(array_merge([$section], $exclude_keys));
} else {
  $stmt = db()->prepare('SELECT * FROM site_content WHERE section=? ORDER BY sort_order');
  $stmt->execute([$section]);
}
$fields = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;">
<?php foreach ($section_map as $s => $info): ?>
  <a href="?section=<?= $s ?>" style="padding:.45rem 1rem;border-radius:8px;font-size:.82rem;
     font-weight:600;text-decoration:none;
     <?= $s===$section
       ? 'background:var(--blue);color:#fff;'
       : 'background:var(--hover-bg);border:1px solid var(--border);color:var(--text-muted);' ?>">
    <?= $info['icon'] ?> <?= $info['label'] ?>
  </a>
<?php endforeach; ?>
</div>

<div class="card">
  <div class="card-title"><?= $section_map[$section]['icon'] ?> <?= $section_map[$section]['label'] ?></div>
  <form method="POST" id="contentForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <?php foreach ($fields as $field):
      $type = $field['type'];
      $key  = $field['key_name'];
      $val  = $field['value'] ?? '';
    ?>
    <div class="form-group">
      <label><?= htmlspecialchars($field['label']) ?></label>

      <?php if ($type === 'html' || $type === 'textarea'): ?>
        <?php
          // Convert plain HTML to something Quill can display nicely
          // We'll use a hidden input + Quill editor
          $quill_id = 'quill_' . preg_replace('/[^a-z0-9]/i','_',$key);
        ?>
        <div style="position:relative;">
          <div id="<?= $quill_id ?>_editor" style="margin-bottom:0;"></div>
          <button type="button" class="emoji-trigger-btn desktop-only"
                  data-target="quill" data-quill="<?= $quill_id ?>"
                  title="Emoji beszúrása">😊</button>
        </div>
        <input type="hidden"
               name="fields[<?= htmlspecialchars($key) ?>]"
               id="<?= $quill_id ?>_input"
               value="<?= htmlspecialchars($val) ?>">
        <script>
        document.addEventListener('DOMContentLoaded', function(){
          var q = new Quill('#<?= $quill_id ?>_editor', {
            theme: 'snow',
            placeholder: 'Ide írd a szöveget...',
            modules: { toolbar: [
              ['bold','italic','underline'],
              [{ 'list': 'ordered'}, { 'list': 'bullet' }],
              ['link'],
              ['clean']
            ]}
          });
          q.root.innerHTML = <?= json_encode($val) ?>;
          document.getElementById('contentForm').addEventListener('submit', function(){
            document.getElementById('<?= $quill_id ?>_input').value = q.root.innerHTML;
          });
          window.__quillInstances = window.__quillInstances || {};
          window.__quillInstances['<?= $quill_id ?>'] = q;
        });
        </script>

      <?php elseif ($type === 'url'): ?>
        <input type="url" name="fields[<?= htmlspecialchars($key) ?>]"
               class="form-control" value="<?= htmlspecialchars($val) ?>">
        <div class="form-hint">Teljes webcím, pl.: https://example.com</div>

      <?php elseif ($type === 'email'): ?>
        <input type="email" name="fields[<?= htmlspecialchars($key) ?>]"
               class="form-control" value="<?= htmlspecialchars($val) ?>">

      <?php else: ?>
        <div style="display:flex;align-items:center;gap:.5rem;">
          <input type="text" name="fields[<?= htmlspecialchars($key) ?>]"
                 class="form-control emoji-text-input" id="field_<?= htmlspecialchars($key) ?>"
                 value="<?= htmlspecialchars($val) ?>">
          <button type="button" class="emoji-trigger-btn desktop-only"
                  data-target="input" data-input="field_<?= htmlspecialchars($key) ?>"
                  title="Emoji beszúrása">😊</button>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:.75rem;margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);">
      <button type="submit" class="btn btn-primary">💾 Mentés</button>
      <button type="button" id="previewButton" class="btn"
         style="background:var(--hover-bg);border:1px solid var(--border);color:var(--blue);">
        🌐 Előnézet ↗
      </button>
    </div>
  </form>
</div>

<script>
(function(){
  const form = document.getElementById('contentForm');
  const previewButton = document.getElementById('previewButton');
  if (!form || !previewButton) return;

  function syncQuillEditors() {
    document.querySelectorAll('#contentForm [id$="_editor"]').forEach(function(editor){
      const input = document.getElementById(editor.id.replace(/_editor$/, '_input'));
      const content = editor.querySelector('.ql-editor');
      if (input && content) input.value = content.innerHTML;
    });
  }

  previewButton.addEventListener('click', function(){
    syncQuillEditors();

    const formData = new FormData(form);
    const fields = {};
    for (const [key, value] of formData.entries()) {
      if (!key.startsWith('fields[')) continue;
      const fieldName = key.slice(7, -1);
      fields[fieldName] = value;
    }

    localStorage.setItem('itsz_preview', JSON.stringify({
      section: <?= json_encode($section) ?>,
      fields: fields,
      timestamp: Date.now(),
    }));

    const anchors = { hero:'#fooldal', about:'#rolunk', goals:'#celaink', join:'#csatlakozz', contact:'#kapcsolat', footer:'#footer' };
    const anchor = anchors[<?= json_encode($section) ?>] || '';
    window.open('<?= SITE_URL ?>/index.php?preview=1' + anchor, '_blank');
  });
})();
</script>
<style>
@media (pointer: coarse), (max-width: 768px) {
  .desktop-only { display: none !important; }
}
.emoji-trigger-btn {
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
.emoji-trigger-btn:hover { background: var(--active-bg); }
div[style*="position:relative"] > .emoji-trigger-btn {
  position: absolute;
  top: .35rem;
  right: .4rem;
  z-index: 10;
}
#itsz-emoji-picker {
  position: fixed;
  z-index: 9999;
  background: var(--card, #1e2330);
  border: 1px solid var(--border, #2e3547);
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0,0,0,.35);
  padding: .75rem;
  width: 320px;
  max-height: 340px;
  display: none;
  flex-direction: column;
  gap: .5rem;
}
#itsz-emoji-picker.open { display: flex; }
#itsz-emoji-search {
  width: 100%;
  padding: .4rem .7rem;
  border-radius: 8px;
  border: 1px solid var(--border, #2e3547);
  background: var(--hover-bg, #252b3b);
  color: inherit;
  font-size: .85rem;
  box-sizing: border-box;
}
#itsz-emoji-tabs {
  display: flex;
  gap: .3rem;
  flex-wrap: wrap;
}
#itsz-emoji-tabs button {
  background: none;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  padding: .2rem .35rem;
  opacity: .6;
  transition: .15s;
}
#itsz-emoji-tabs button.active,
#itsz-emoji-tabs button:hover { opacity: 1; border-color: var(--border, #2e3547); background: var(--hover-bg,#252b3b); }
#itsz-emoji-grid {
  display: flex;
  flex-wrap: wrap;
  gap: .15rem;
  overflow-y: auto;
  max-height: 220px;
  padding-right: .2rem;
}
#itsz-emoji-grid button {
  background: none;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 1.35rem;
  cursor: pointer;
  padding: .2rem;
  transition: .12s;
  line-height: 1;
}
#itsz-emoji-grid button:hover { background: var(--hover-bg,#252b3b); border-color: var(--border,#2e3547); }
</style>

<div id="itsz-emoji-picker">
  <input id="itsz-emoji-search" type="text" placeholder="Keresés kategória szerint...">
  <div id="itsz-emoji-tabs"></div>
  <div id="itsz-emoji-grid"></div>
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
    'Utazás':['✈️','🚀','🛸','🚁','⛵','🚢','🚂','🚄','🚇','🚌','🚗','🛻','🚲','🛴','🗺️','🏎️','🚕','🚑','🚒','🚓'],
    'Iroda':['💼','📁','📂','📋','📊','📈','📉','📝','✏️','📌','📍','📎','✂️','🔒','🔓','🔑','🔧','⚙️','💡','💰','💳','✉️','📧','📦'],
    'Szimbólum':['❗','❓','‼️','⁉️','✅','❌','⭕','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🔺','🔻','🔷','🔶','🔹','🔸','▶️','⏸️','⏹️','⏺️','⏭️','⏮️','🔀','🔁','🔂','🆕','🆗','🆙','🆒','🆓','🔔','🔕','🔇','🔈','🔉','🔊'],
  };

  var cats = Object.keys(EMOJIS);
  var currentCat = cats[0];
  var currentTarget = null;

  var picker = document.getElementById('itsz-emoji-picker');
  var grid   = document.getElementById('itsz-emoji-grid');
  var search = document.getElementById('itsz-emoji-search');
  var tabs   = document.getElementById('itsz-emoji-tabs');

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
      var q = window.__quillInstances && window.__quillInstances[currentTarget.id];
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
    if (!picker.contains(e.target) && !e.target.classList.contains('emoji-trigger-btn')){
      closePicker();
    }
  }, true);

  search.addEventListener('input', function(){
    var q = search.value.trim().toLowerCase();
    if (!q){ renderGrid(EMOJIS[currentCat]); return; }
    var matches = [];
    Object.entries(EMOJIS).forEach(function(entry){
      var cat = entry[0], emojis = entry[1];
      if (cat.toLowerCase().includes(q)){
        emojis.forEach(function(e){ if(!matches.includes(e)) matches.push(e); });
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
    var btn = e.target.closest('.emoji-trigger-btn');
    if (!btn) return;
    e.stopPropagation();
    var type = btn.dataset.target;
    if (type === 'input'){
      var inp = document.getElementById(btn.dataset.input);
      if (inp) openPicker(btn, {type:'input', el: inp});
    } else if (type === 'quill'){
      openPicker(btn, {type:'quill', id: btn.dataset.quill});
    }
  });

  renderGrid(EMOJIS[currentCat]);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>