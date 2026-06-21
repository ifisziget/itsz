<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$is_kf = is_campaign_manager();
$page_title  = 'Felhasználók';
$active_page = 'users';
$pdo = db();

function password_is_strong(string $pw): bool {
    return strlen($pw) >= 6
        && preg_match('/[a-z]/', $pw)
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>\/\\|?]/', $pw);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Saját profil – mindenki szerkesztheti
    if ($action === 'own_profile') {
        $new_username  = trim($_POST['new_username'] ?? '');
        $new_password  = $_POST['new_password']  ?? '';
        $new_password2 = $_POST['new_password2'] ?? '';
        $current_pw    = $_POST['current_password'] ?? '';

        $me = $pdo->prepare('SELECT * FROM admin_users WHERE id=?');
        $me->execute([$_SESSION['admin_id']]);
        $me = $me->fetch();

        if (!password_verify($current_pw, $me['password'])) {
            flash('error', 'A jelenlegi jelszó nem megfelelő!');
        } elseif (empty($new_username)) {
            flash('error', 'A felhasználónév nem lehet üres!');
        } else {
            $chk = $pdo->prepare('SELECT id FROM admin_users WHERE username=? AND id!=?');
            $chk->execute([$new_username, $_SESSION['admin_id']]);
            if ($chk->fetch()) {
                flash('error', 'Ez a felhasználónév már foglalt!');
            } else {
                $pdo->prepare('UPDATE admin_users SET username=? WHERE id=?')
                    ->execute([$new_username, $_SESSION['admin_id']]);

                if (!empty($new_password)) {
                    if ($new_password !== $new_password2) {
                        flash('error', 'A két jelszó nem egyezik!');
                        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
                    }
                    if (!password_is_strong($new_password)) {
                        flash('error', 'A jelszónak tartalmaznia kell: kis- és nagybetűt, speciális karaktert, min. 6 kar.!');
                        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
                    }
                    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
                    $pdo->prepare('UPDATE admin_users SET password=? WHERE id=?')
                        ->execute([$hash, $_SESSION['admin_id']]);
                }
                log_action('Saját profil frissítve', "user: $new_username");
                flash('success', 'Profil sikeresen frissítve!');
            }
        }
        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
    }

    // Többi művelet csak superadminnak
    if (!is_superadmin()) {
        flash('error', 'Nincs jogosultságod.');
        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
    }

    if ($action === 'create') {
        $username  = trim($_POST['username']);
        $password  = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $role      = in_array($_POST['role'] ?? '', ['superadmin','admin','kampanyfonok']) ? $_POST['role'] : 'admin';

        if (!password_is_strong($password)) {
            flash('error', 'A jelszó nem elég erős! (min. 6 kar., kis- és nagybetű, speciális karakter)');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            try {
                $pdo->prepare('INSERT INTO admin_users (username,password,full_name,role) VALUES (?,?,?,?)')
                    ->execute([$username, $hash, $full_name, $role]);
                $new_id = (int)$pdo->lastInsertId();
                if ($role === 'kampanyfonok') {
                    $rep_ids = $_POST['rep_ids'] ?? [];
                    if (is_array($rep_ids)) {
                        $stmtA = $pdo->prepare('UPDATE representatives SET manager_id=? WHERE id=?');
                        foreach ($rep_ids as $rid) { $stmtA->execute([$new_id, (int)$rid]); }
                    }
                }
                log_action('Felhasználó létrehozva', $username);
                flash('success', $full_name . ' sikeresen létrehozva.');
            } catch (Exception $e) {
                flash('error', 'Ez a felhasználónév már foglalt.');
            }
        }
        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
    }

    if ($action === 'update') {
        $id        = (int)$_POST['id'];
        $full_name = trim($_POST['full_name']);
        $role      = in_array($_POST['role'] ?? '', ['superadmin','admin','kampanyfonok']) ? $_POST['role'] : 'admin';
        $password  = $_POST['password'];

        if ($id === (int)$_SESSION['admin_id']) {
            flash('error', 'Saját fiókodat a "Saját fiók" szekcióban szerkeszd!');
        } else {
            $pdo->prepare('UPDATE admin_users SET full_name=?,role=? WHERE id=?')
                ->execute([$full_name, $role, $id]);
            if (!empty($password)) {
                if (!password_is_strong($password)) {
                    flash('error', 'A jelszó nem elég erős!');
                    header('Location: ' . SITE_URL . '/admin/users.php'); exit;
                }
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $pdo->prepare('UPDATE admin_users SET password=? WHERE id=?')->execute([$hash, $id]);
            }
            $pdo->prepare('UPDATE representatives SET manager_id=NULL WHERE manager_id=?')->execute([$id]);
            if ($role === 'kampanyfonok') {
                $rep_ids = $_POST['rep_ids'] ?? [];
                if (is_array($rep_ids)) {
                    $stmtA = $pdo->prepare('UPDATE representatives SET manager_id=? WHERE id=?');
                    foreach ($rep_ids as $rid) { $stmtA->execute([$id, (int)$rid]); }
                }
            }
            log_action('Felhasználó frissítve', "ID: $id");
            flash('success', 'Felhasználó frissítve!');
        }
        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$_SESSION['admin_id']) {
            flash('error', 'Saját magad nem törölheted!');
        } else {
            $pdo->prepare('UPDATE representatives SET manager_id=NULL WHERE manager_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);
            log_action('Felhasználó törölve', "ID: $id");
            flash('success', 'Felhasználó törölve.');
        }
        header('Location: ' . SITE_URL . '/admin/users.php'); exit;
    }
}

$me = current_user();
$rep_list   = [];
$rep_assign = [];
if (is_superadmin()) {
    try {
        $hasCol = (bool)$pdo->query("SHOW COLUMNS FROM representatives LIKE 'manager_id'")->fetch();
        if ($hasCol) {
            $rep_list = $pdo->query('SELECT id, full_name FROM representatives ORDER BY sort_order')->fetchAll();
            foreach ($pdo->query('SELECT id, manager_id FROM representatives')->fetchAll() as $r) {
                if ($r['manager_id']) $rep_assign[$r['manager_id']][] = (int)$r['id'];
            }
        }
    } catch (Exception $e) {}
}
$users = is_superadmin()
    ? $pdo->query('SELECT * FROM admin_users ORDER BY FIELD(role,"superadmin","admin","kampanyfonok"), id')->fetchAll()
    : [];

include __DIR__ . '/includes/header.php';
?>
<style>
.pw-wrap{position:relative;}.pw-wrap input{padding-right:2.5rem;}
.pw-eye{position:absolute;right:.7rem;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;font-size:1rem;color:var(--text-muted);}
.pw-reqs{margin-top:.4rem;font-size:.77rem;padding:0;}
.pw-reqs li{list-style:none;padding:.1rem 0;color:var(--text-muted);}
.pw-reqs li::before{content:"○ ";}
.pw-reqs li.ok{color:#2ecc71;}.pw-reqs li.ok::before{content:"✓ ";}
.pw-reqs li.bad{color:#e74c3c;}.pw-reqs li.bad::before{content:"✗ ";}

/* Felhasználók reszponzív kártyák mobilon */
.user-cards { display: none; }
@media (max-width: 700px) {
  .user-table-wrap { display: none; }
  .user-cards { display: flex; flex-direction: column; gap: .75rem; }
}
.user-card {
  background: var(--navy-mid);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.user-card-header {
  display: flex;
  align-items: center;
  gap: .75rem;
}
.user-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  background: var(--blue);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .95rem;
  color: var(--navy);
  flex-shrink: 0;
}
.user-card-info { flex: 1; min-width: 0; }
.user-card-name {
  font-weight: 600;
  font-size: .92rem;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-card-meta {
  font-size: .78rem;
  color: var(--text-muted);
  margin-top: .15rem;
  font-family: monospace;
}
.user-card-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: .82rem;
  color: var(--text-muted);
  border-top: 1px solid var(--border);
  padding-top: .5rem;
}
.user-card-actions {
  display: flex;
  gap: .5rem;
  border-top: 1px solid var(--border);
  padding-top: .6rem;
}
</style>

<!-- SAJÁT FIÓK -->
<div class="card" style="border-color:rgba(126,184,212,.35);">
  <div class="card-title">👤 Saját fiók
    <span style="margin-left:.5rem;font-size:.72rem;color:var(--blue);background:rgba(126,184,212,.12);padding:.2rem .6rem;border-radius:20px;">
      <?= htmlspecialchars($me['username']) ?>
    </span>
  </div>
  <form method="POST" id="ownForm">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="own_profile">
    <div class="form-row">
      <div class="form-group">
        <label>Felhasználónév</label>
        <input type="text" name="new_username" class="form-control"
               value="<?= htmlspecialchars($me['username']) ?>" required>
        <div class="form-hint">Változtatás után ezzel kell belépni.</div>
      </div>
      <div class="form-group">
        <label>Szerepkör</label>
        <input type="text" class="form-control" disabled style="opacity:.5;"
               value="<?= $me['role']==='superadmin'?'Főadmin':($me['role']==='kampanyfonok'?'Kampányfőnök':'Admin') ?>">
      </div>
    </div>
    <div style="background:var(--hover-bg);border:1px solid var(--border);border-radius:10px;padding:1.25rem;margin-bottom:1.25rem;">
      <div style="font-size:.78rem;font-weight:700;color:var(--blue);letter-spacing:.1em;text-transform:uppercase;margin-bottom:1rem;">
        🔒 Jelszó megváltoztatása (opcionális)
      </div>
      <div class="form-row">
        <div class="form-group" style="margin:0;">
          <label>Új jelszó</label>
          <div class="pw-wrap">
            <input type="password" id="own_pw" name="new_password" class="form-control"
                   placeholder="Hagyd üresen ha nem változtatod" autocomplete="new-password"
                   oninput="checkPw(this,'own_reqs')">
            <button type="button" class="pw-eye" onclick="eyeToggle(this)">👁️</button>
          </div>
          <ul class="pw-reqs" id="own_reqs">
            <li data-r="len">Min. 6 karakter</li>
            <li data-r="low">Kisbetű (a–z)</li>
            <li data-r="up">Nagybetű (A–Z)</li>
            <li data-r="sp">Speciális karakter</li>
          </ul>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Megerősítés</label>
          <div class="pw-wrap">
            <input type="password" name="new_password2" class="form-control"
                   placeholder="Jelszó mégegyszer" autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="eyeToggle(this)">👁️</button>
          </div>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Jelenlegi jelszó <span style="color:var(--red);">*</span></label>
      <div class="pw-wrap">
        <input type="password" name="current_password" class="form-control"
               placeholder="Kötelező a mentéshez" required autocomplete="current-password">
        <button type="button" class="pw-eye" onclick="eyeToggle(this)">👁️</button>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 Mentés</button>
  </form>
</div>

<?php if (is_superadmin()): ?>
<!-- FELHASZNÁLÓK LISTÁJA -->
<div class="card">
  <div class="card-title">👥 Admin felhasználók (<?= count($users) ?> db)</div>

  <?php
  // Adatok előkészítése (desktop + mobil közös)
  $user_rows = [];
  foreach ($users as $u) {
    if ($u['role']==='superadmin') $rl=['Főadmin','badge-green'];
    elseif ($u['role']==='kampanyfonok') $rl=['Kampányfőnök','badge-blue'];
    else $rl=['Admin','badge-blue'];
    $asgn = $rep_assign[$u['id']] ?? [];
    $names = [];
    foreach ($asgn as $rid) {
      foreach ($rep_list as $r) {
        if ((int)$r['id'] === (int)$rid) { $names[] = $r['full_name']; break; }
      }
    }
    $u['_rl'] = $rl;
    $u['_names'] = $names;
    $u['_asgn'] = $asgn;
    $user_rows[] = $u;
  }
  ?>

  <!-- DESKTOP TÁBLA -->
  <div class="user-table-wrap table-wrap">
    <table>
      <thead>
        <tr><th>Név</th><th>Felhasználónév</th><th>Szerepkör</th><th>Hozzárendelt képviselők</th><th>Utolsó belépés</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($user_rows as $u): $rl=$u['_rl']; $names=$u['_names']; $asgn=$u['_asgn']; ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.6rem;">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:var(--navy);">
                <?= strtoupper(substr($u['full_name']??$u['username'],0,1)) ?>
              </div>
              <?= htmlspecialchars($u['full_name']??'') ?>
              <?php if($u['id']==$_SESSION['admin_id']): ?><span class="badge badge-blue" style="font-size:.65rem;">Te</span><?php endif; ?>
            </div>
          </td>
          <td style="font-family:monospace;font-size:.85rem;"><?= htmlspecialchars($u['username']) ?></td>
          <td><span class="badge <?= $rl[1] ?>"><?= $rl[0] ?></span></td>
          <td style="font-size:.82rem;color:var(--text-muted);"><?= $names?implode(', ',$names):'–' ?></td>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= !empty($u['last_login'])?date('Y.m.d H:i',strtotime($u['last_login'])):'Soha' ?></td>
          <td>
            <?php $u['assigned_reps']=$asgn; if($u['id']!=$_SESSION['admin_id']): ?>
              <button onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)"
                class="btn btn-sm" style="background:rgba(126,184,212,.1);border:1px solid var(--border);color:var(--blue);">✏️</button>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Törlöd?')">
                <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                <button class="btn btn-sm btn-danger">🗑️</button>
              </form>
            <?php else: ?><span style="font-size:.75rem;color:var(--text-muted);">↑ Fent</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- MOBIL KÁRTYÁK -->
  <div class="user-cards">
    <?php foreach ($user_rows as $u): $rl=$u['_rl']; $names=$u['_names']; $asgn=$u['_asgn']; ?>
    <div class="user-card">
      <div class="user-card-header">
        <div class="user-avatar"><?= strtoupper(substr($u['full_name']??$u['username'],0,1)) ?></div>
        <div class="user-card-info">
          <div class="user-card-name">
            <?= htmlspecialchars($u['full_name']??'') ?>
            <?php if($u['id']==$_SESSION['admin_id']): ?>
              <span class="badge badge-blue" style="font-size:.65rem;margin-left:.3rem;">Te</span>
            <?php endif; ?>
          </div>
          <div class="user-card-meta">@<?= htmlspecialchars($u['username']) ?></div>
        </div>
        <span class="badge <?= $rl[1] ?>"><?= $rl[0] ?></span>
      </div>
      <?php if($names): ?>
      <div style="font-size:.8rem;color:var(--text-muted);">
        👤 <?= htmlspecialchars(implode(', ',$names)) ?>
      </div>
      <?php endif; ?>
      <div class="user-card-row">
        <span>Utolsó belépés</span>
        <span><?= !empty($u['last_login'])?date('Y.m.d H:i',strtotime($u['last_login'])):'Soha' ?></span>
      </div>
      <?php if($u['id']!=$_SESSION['admin_id']): $u['assigned_reps']=$asgn; ?>
      <div class="user-card-actions">
        <button onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)"
          class="btn btn-sm" style="flex:1;background:rgba(126,184,212,.1);border:1px solid var(--border);color:var(--blue);">✏️ Szerkesztés</button>
        <form method="POST" onsubmit="return confirm('Törlöd ezt a felhasználót?')">
          <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id"     value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-danger">🗑️ Törlés</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ÚJ FELHASZNÁLÓ -->
<div class="card">
  <div class="card-title">➕ Új admin létrehozása</div>
  <form method="POST">
    <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group">
        <label>Teljes név</label>
        <input type="text" name="full_name" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Felhasználónév</label>
        <input type="text" name="username" class="form-control" required autocomplete="off">
      </div>
    </div>
    <div class="form-group">
      <label>Jelszó</label>
      <div class="pw-wrap">
        <input type="password" id="cr_pw" name="password" class="form-control"
               required autocomplete="new-password" oninput="checkPw(this,'cr_reqs')">
        <button type="button" class="pw-eye" onclick="eyeToggle(this)">👁️</button>
      </div>
      <ul class="pw-reqs" id="cr_reqs">
        <li data-r="len">Min. 6 karakter</li><li data-r="low">Kisbetű</li>
        <li data-r="up">Nagybetű</li><li data-r="sp">Speciális karakter</li>
      </ul>
    </div>
    <div class="form-group">
      <label>Szerepkör</label>
      <select name="role" class="form-control" onchange="toggleRepsBlock('crReps',this.value)">
        <option value="admin">Admin (teljes szerkesztés)</option>
        <option value="kampanyfonok">Kampányfőnök (csak képviselők)</option>
        <option value="superadmin">Főadmin (mindent kezel)</option>
      </select>
    </div>
    <?php if(!empty($rep_list)): ?>
    <div class="form-group" id="crReps" style="display:none;">
      <label>Hozzárendelt képviselők</label>
      <select name="rep_ids[]" class="form-control" multiple size="<?= min(count($rep_list),5) ?>">
        <?php foreach($rep_list as $r): ?>
          <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-hint">Ctrl+kattintással több is kijelölhető.</div>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">➕ Létrehozás</button>
  </form>
</div>

<!-- SZERKESZTÉS MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--navy-mid);border:1px solid var(--border);border-radius:16px;padding:2rem;width:100%;max-width:500px;margin:1rem;max-height:90vh;overflow-y:auto;">
    <h3 style="margin-bottom:1.5rem;">✏️ Felhasználó szerkesztése</h3>
    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id"     id="eId">
      <div class="form-group">
        <label>Teljes név</label>
        <input type="text" name="full_name" id="eName" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Szerepkör</label>
        <select name="role" id="eRole" class="form-control" onchange="toggleRepsBlock('eReps',this.value)">
          <option value="admin">Admin</option>
          <option value="kampanyfonok">Kampányfőnök</option>
          <option value="superadmin">Főadmin</option>
        </select>
      </div>
      <?php if(!empty($rep_list)): ?>
      <div class="form-group" id="eReps" style="display:none;">
        <label>Hozzárendelt képviselők</label>
        <select name="rep_ids[]" id="eRepsSel" class="form-control" multiple size="<?= min(count($rep_list),5) ?>">
          <?php foreach($rep_list as $r): ?>
            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Ctrl+kattintással több is kijelölhető.</div>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label>Új jelszó (üresen hagyható)</label>
        <div class="pw-wrap">
          <input type="password" id="ed_pw" name="password" class="form-control"
                 autocomplete="new-password" oninput="checkPw(this,'ed_reqs')">
          <button type="button" class="pw-eye" onclick="eyeToggle(this)">👁️</button>
        </div>
        <ul class="pw-reqs" id="ed_reqs">
          <li data-r="len">Min. 6 karakter</li><li data-r="low">Kisbetű</li>
          <li data-r="up">Nagybetű</li><li data-r="sp">Speciális karakter</li>
        </ul>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1rem;">
        <button type="submit" class="btn btn-primary">💾 Mentés</button>
        <button type="button" onclick="closeEdit()" class="btn" style="background:var(--hover-bg);border:1px solid var(--border);color:var(--text-muted);">Mégse</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function eyeToggle(btn){const i=btn.previousElementSibling;i.type=i.type==="password"?"text":"password";btn.textContent=i.type==="password"?"👁️":"🙈";}
function checkPw(inp,rid){
  const v=inp.value,rules={len:v.length>=6,low:/[a-z]/.test(v),up:/[A-Z]/.test(v),sp:/[!@#$%^&*()\-_=+\[\]{};:'",.<>\/\\|?~`]/.test(v)};
  document.querySelectorAll("#"+rid+" li").forEach(li=>{const r=li.dataset.r;li.className=rules[r]?"ok":(v.length>0?"bad":"");});
}
function toggleRepsBlock(id,role){const w=document.getElementById(id);if(w)w.style.display=role==="kampanyfonok"?"block":"none";}
function openEdit(u){
  document.getElementById("eId").value=u.id;
  document.getElementById("eName").value=u.full_name||"";
  const re=document.getElementById("eRole");if(re){re.value=u.role||"admin";toggleRepsBlock("eReps",re.value);}
  const rs=document.getElementById("eRepsSel");
  if(rs){Array.from(rs.options).forEach(o=>o.selected=false);(u.assigned_reps||[]).forEach(id=>{const o=rs.querySelector("option[value='"+id+"']");if(o)o.selected=true;});}
  document.getElementById("editModal").style.display="flex";
}
function closeEdit(){document.getElementById("editModal").style.display="none";}
var _em=document.getElementById("editModal");if(_em)_em.addEventListener("click",e=>{if(e.target===document.getElementById("editModal"))closeEdit();});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>