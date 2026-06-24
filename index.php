<?php
require_once __DIR__ . '/config.php';

// Load all sections
$hero    = get_section('hero');
$about   = get_section('about');
$goals   = get_section('goals');
$join    = get_section('join');
$contact = get_section('contact');
$footer  = get_section('footer');


// ─── ÉVSZAK DETEKTÁLÁS ───────────────────────────────────
$season_settings = get_section('seasons');
$effects_on   = ($season_settings['effects_enabled'] ?? '1') === '1';
$active_manual = $season_settings['active_season'] ?? 'auto';

function detect_season(array $s): string {
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

$current_season = ($active_manual !== 'auto') ? $active_manual : detect_season($season_settings);
if ($current_season === 'default') { $effects_on = false; }
$xmas_on   = ($season_settings['xmas_enabled']  ?? '1') === '1';
$easter_on = ($season_settings['easter_enabled'] ?? '1') === '1';

$now = new DateTime();
$xmas = new DateTime(date('Y') . '-12-25');
if ($now > $xmas) $xmas = new DateTime((date('Y')+1) . '-12-25');
$xmas_days = (int)$now->diff($xmas)->days;

function next_easter(): DateTime {
    $y = (int)date('Y');
    $e = new DateTime(date('Y-m-d', easter_date($y)));
    if (new DateTime() > $e) $e = new DateTime(date('Y-m-d', easter_date($y+1)));
    return $e;
}
$easter_obj  = next_easter();
$easter_days = (int)(new DateTime())->diff($easter_obj)->days;

$dev_name = get_setting('developer_name', 'Kertvéllesy László Zsolt') ?: 'Kertvéllesy László Zsolt';
$dev_url  = get_setting('developer_url',  'mailto:kertvellesy.laszlo.zsolt@gmail.com') ?: '#';
$dev_icon = get_setting('developer_icon', '💻') ?: '💻';
$dev_show = get_setting('developer_show', '1') === '1';

try {
    $stmt = db()->prepare('SELECT * FROM social_links WHERE active=? ORDER BY sort_order');
    $stmt->execute([1]);
    $social_links = $stmt->fetchAll();
} catch (Exception $e) {
    $social_links = [];
}

try {
    $stmt = db()->prepare('SELECT * FROM gallery WHERE active=? ORDER BY sort_order, id');
    $stmt->execute([1]);
    $gallery = $stmt->fetchAll();
} catch (Exception $e) {
    $gallery = [];
}

try {
    $stmt = db()->prepare('SELECT * FROM representatives WHERE active=? ORDER BY sort_order ASC');
    $stmt->execute([1]);
    $representatives = $stmt->fetchAll();
} catch (Exception $e) {
    $representatives = [];
}

function find_asset_url(string $name): string {
    $base = __DIR__ . '/assets/';
    foreach (['jpg','jpeg','png','webp','gif'] as $ext) {
        if (file_exists($base . "$name.$ext")) return SITE_URL . "/assets/$name.$ext";
    }
    return '';
}
$logo_url = find_asset_url('logo');
$qr_url   = find_asset_url('qr');
$theme_font_family = get_setting('font_family', 'Inter');
$theme_font_size   = get_setting('font_size', '18px');

function social_svg(string $platform): string {
    $svgs = [
        'facebook'  => '<svg width="44" height="44" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'instagram' => '<svg width="44" height="44" viewBox="0 0 24 24"><defs><linearGradient id="ig" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#f09433"/><stop offset="50%" style="stop-color:#dc2743"/><stop offset="100%" style="stop-color:#bc1888"/></linearGradient></defs><path fill="url(#ig)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
        'tiktok'    => '<svg width="44" height="44" viewBox="0 0 24 24"><path fill="#69c9d0" d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/><path fill="#ff004f" d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.79 1.52V6.74a4.85 4.85 0 01-1.02-.05z"/></svg>',
        'youtube'   => '<svg width="44" height="44" viewBox="0 0 24 24" fill="#FF0000"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
        'twitter'   => '<svg width="44" height="44" viewBox="0 0 24 24" fill="#fff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.747l7.73-8.835L1.254 2.25H8.08l4.259 5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
    ];
    return $svgs[$platform] ?? '<svg width="44" height="44" viewBox="0 0 24 24" fill="#7eb8d4"><circle cx="12" cy="12" r="10"/></svg>';
}

function social_bg(string $platform): string {
    $bgs = [
        'facebook'  => 'linear-gradient(135deg,rgba(24,119,242,.12),rgba(12,26,58,.8))',
        'instagram' => 'linear-gradient(135deg,rgba(225,48,108,.12),rgba(12,26,58,.8))',
        'tiktok'    => 'linear-gradient(135deg,rgba(0,0,0,.3),rgba(12,26,58,.8))',
        'youtube'   => 'linear-gradient(135deg,rgba(255,0,0,.12),rgba(12,26,58,.8))',
        'twitter'   => 'linear-gradient(135deg,rgba(29,161,242,.12),rgba(12,26,58,.8))',
    ];
    return $bgs[$platform] ?? 'rgba(126,184,212,0.08)';
}
?>
<!DOCTYPE html>
<html lang="hu" data-theme="dark" data-season="<?= htmlspecialchars($current_season ?? '', ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ifjúsági TISZA Sziget – Győr</title>
<?php
$fav = null;
foreach (["png","jpg","jpeg","webp","gif"] as $_e) {
    if (file_exists(__DIR__."/assets/favicon.$_e")) { $fav = SITE_URL."/assets/favicon.$_e"; break; }
}
if ($fav): ?>
<link rel="icon" type="image/png" href="<?= $fav ?>?v=<?= time() ?>">
<link rel="shortcut icon" href="<?= $fav ?>?v=<?= time() ?>">
<?php endif; ?>
<meta name="description" content="Ifjúsági TISZA Sziget Győr – Közélet, kultúra, közösség. Csatlakozz hozzánk!">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&family=Inter:wght@400;500;600;700&family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  [data-theme="dark"]{--navy:#0c1a3a;--navy-mid:#152248;--blue-ring:#7eb8d4;--red:#ce2939;--green:#477a3b;--white:#f5f7fa;--text-muted:#8fa3c2;--card-bg:rgba(255,255,255,0.04);--border:rgba(126,184,212,0.18);--hero-bg:#0c1a3a;--section-bg:#0c1a3a;}
  [data-theme="light"]{--navy:#ffffff;--navy-mid:#eef4ff;--blue-ring:#2a72b8;--red:#c02030;--green:#2e6b25;--white:#0d1e3a;--text-muted:#4a6080;--card-bg:rgba(42,114,184,0.06);--border:rgba(42,114,184,0.18);--hero-bg:#dce8f8;--section-bg:#f5f9ff;}
  [data-theme="light"] body{background:#f0f5ff;}
  [data-theme="light"] .hero-bg{background:radial-gradient(ellipse 60% 70% at 50% 0%,rgba(42,114,184,.15) 0%,transparent 70%),radial-gradient(ellipse 40% 50% at 20% 80%,rgba(192,32,48,.07) 0%,transparent 60%),#dce8f8 !important;}
  [data-theme="light"] nav{background:rgba(255,255,255,0.95) !important;}
  [data-theme="light"] .nav-brand,[data-theme="light"] .nav-links a,[data-theme="light"] .hero h1{color:#0d1e3a;}
  [data-theme="light"] .hero-lead{color:#4a6080;}
  [data-theme="light"] #fooldal{background:#dce8f8;}
  [data-theme="light"] section{background:#f0f5ff;}
  [data-theme="light"] footer{background:#e8f0fb;}
  [data-theme="light"] .mission-card{background:#fff;border-color:rgba(42,114,184,0.15);}
  [data-theme="light"] .mission-card h3{color:#0d1e3a;}
  [data-theme="light"] .contact-item{background:#fff;}
  [data-theme="light"] .social-card.fb{background:linear-gradient(135deg,rgba(24,119,242,0.08),#fff);}
  [data-theme="light"] .social-card.ig{background:linear-gradient(135deg,rgba(225,48,108,0.08),#fff);}
  [data-theme="light"] .social-card.tt{background:linear-gradient(135deg,rgba(0,0,0,0.04),#fff);}
  [data-theme="light"] .scroll-arrow{border-color:#4a6080;}
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{font-family:'<?= htmlspecialchars($theme_font_family) ?>',sans-serif;font-size:<?= htmlspecialchars($theme_font_size) ?>;background:var(--navy);color:var(--white);overflow-x:hidden;}
  nav, .nav-brand, .nav-links a, .nav-cta, .hero-badge, .hero h1, .hero-lead, .section-label, .section-title, .mission-card h3, .mission-card p, .text-block p, .highlight-box p, .social-name, .social-handle, .join-text p, .contact-item .ci-label, .contact-item .ci-value, footer, .footer-logo span {
    font-family:'<?= htmlspecialchars($theme_font_family) ?>',sans-serif;
  }
  nav{position:fixed;top:0;left:0;right:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:70px;background:rgba(12,26,58,.92);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);}
  .nav-logo{display:flex;align-items:center;gap:12px;}
  .nav-logo img{height:48px;width:48px;border-radius:50%;object-fit:cover;object-position:center;background:#fff;box-shadow:0 0 0 2px rgba(126,184,212,.3);}
  .nav-brand{font-family:'Inter',sans-serif;font-size:.82rem;font-weight:700;line-height:1.2;color:var(--white);max-width:140px;}
  .nav-links{display:flex;gap:2rem;list-style:none;}
  .nav-links a{font-family:'Inter',sans-serif;font-size:.85rem;font-weight:600;color:var(--blue-ring);text-decoration:none;letter-spacing:-.02em;transition:color .2s;}
  .nav-links a:hover{color:var(--white);}
  .nav-cta{background:var(--red);color:#fff;font-family:'Inter',sans-serif;font-size:.82rem;font-weight:700;letter-spacing:-.02em;padding:10px 22px;border-radius:40px;text-decoration:none;transition:background .2s,transform .2s;}
  .nav-cta:hover{background:#b01e2c;transform:scale(1.04);}
  .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:4px;}
  .hamburger span{display:block;width:26px;height:2px;background:var(--white);border-radius:2px;}
  .mobile-menu{display:none;position:fixed;top:70px;left:0;right:0;background:var(--navy-mid);border-bottom:1px solid var(--border);padding:1.5rem 5vw;flex-direction:column;gap:1rem;z-index:99;}
  .mobile-menu.open{display:flex;}
  .mobile-menu a{font-family:'Inter',sans-serif;font-size:1rem;font-weight:600;color:var(--blue-ring);text-decoration:none;}

  #fooldal{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:120px 5vw 80px;position:relative;overflow:hidden;}
  .hero-bg{position:absolute;inset:0;z-index:0;background:radial-gradient(ellipse 60% 70% at 50% 0%,rgba(126,184,212,.12) 0%,transparent 70%),radial-gradient(ellipse 40% 50% at 20% 80%,rgba(206,41,57,.08) 0%,transparent 60%),radial-gradient(ellipse 40% 50% at 80% 80%,rgba(71,122,59,.08) 0%,transparent 60%),var(--navy);}
  .hero-bg::after{content:'';position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%237eb8d4' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");}
  .hero-content{position:relative;z-index:1;max-width:820px;}
  .hero-logo-wrap{margin-bottom:2rem;}
  .hero-logo{width:160px;height:160px;border-radius:50%;object-fit:cover;object-position:center;background:#fff;box-shadow:0 0 80px rgba(126,184,212,.35),0 0 0 4px rgba(255,255,255,.15),0 0 0 10px rgba(126,184,212,.12);animation:floatLogo 4s ease-in-out infinite;}
  @keyframes floatLogo{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
  .hero-badge{display:inline-block;font-family:'Inter',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--blue-ring);border:1px solid var(--border);padding:6px 18px;border-radius:40px;margin-bottom:1.5rem;background:rgba(126,184,212,.08);}
  .hero h1{font-family:'Inter',sans-serif;font-size:clamp(2rem,5.5vw,4rem);font-weight:800;line-height:1.08;margin-bottom:1.5rem;color:var(--white);}
  .hero h1 em{font-style:normal;color:var(--blue-ring);}
  .hero-lead{font-size:clamp(1rem,2vw,1.18rem);font-weight:300;color:var(--text-muted);line-height:1.75;max-width:680px;margin:0 auto 2.5rem;}
  .hero-lead p{margin:0 0 1rem 0;}
  .hero-lead p:last-child{margin-bottom:0;}
  .hero-btns{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;}
  .btn-primary{background:var(--red);color:#fff;font-family:'Inter',sans-serif;font-weight:700;font-size:.9rem;letter-spacing:-.02em;padding:14px 34px;border-radius:40px;text-decoration:none;transition:background .2s,transform .2s,box-shadow .2s;box-shadow:0 4px 20px rgba(206,41,57,.3);}  .btn-primary:hover{background:#b01e2c;transform:translateY(-2px);box-shadow:0 8px 30px rgba(206,41,57,.4);}
  .btn-secondary{border:1.5px solid var(--blue-ring);color:var(--blue-ring);font-family:'Inter',sans-serif;font-weight:700;font-size:.9rem;letter-spacing:-.02em;padding:14px 34px;border-radius:40px;text-decoration:none;transition:background .2s,color .2s,transform .2s;}
  .btn-secondary:hover{background:var(--blue-ring);color:var(--navy);transform:translateY(-2px);}
  .scroll-hint{margin-top:4rem;display:flex;flex-direction:column;align-items:center;gap:.5rem;color:var(--text-muted);font-size:.78rem;letter-spacing:.1em;text-transform:uppercase;animation:pulse 2s infinite;}
  .scroll-arrow{width:20px;height:20px;border-right:2px solid var(--text-muted);border-bottom:2px solid var(--text-muted);transform:rotate(45deg);}
  @keyframes pulse{0%,100%{opacity:.5}50%{opacity:1}}

  section{padding:90px 5vw;max-width:1200px;margin:0 auto;}
  .section-label{font-family:'Inter',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--blue-ring);display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;}
  .section-label::after{content:'';flex:1;height:1px;background:var(--border);}
  .section-title{font-family:'Inter',sans-serif;font-size:clamp(1.6rem,3.5vw,2.6rem);font-weight:800;margin-bottom:1rem;line-height:1.12;}

  #rolunk{border-top:1px solid var(--border);}
  .mission-grid{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:2.5rem;}
  .mission-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:2rem;transition:border-color .3s,transform .3s;position:relative;overflow:hidden;}
  .mission-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--red),var(--green));opacity:0;transition:opacity .3s;}
  .mission-card:hover{border-color:rgba(126,184,212,.4);transform:translateY(-4px);}
  .mission-card:hover::before{opacity:1;}
  .mission-icon{font-size:2rem;margin-bottom:1rem;}
  .mission-card h3{font-family:'Inter',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:.75rem;color:var(--white);}
  .mission-card p{color:var(--text-muted);font-size:.95rem;line-height:1.7;}

  #celaink{border-top:1px solid var(--border);}
  .text-block{max-width:780px;}
  .text-block > div{color:var(--text-muted);font-size:1.05rem;line-height:1.85;margin-bottom:1.5rem;}
  .text-block > div:last-child{margin-bottom:0;}
  .highlight-box{border-left:3px solid var(--blue-ring);padding:1.2rem 1.5rem;background:rgba(126,184,212,.06);border-radius:0 12px 12px 0;margin:2rem 0;font-size:1.05rem;line-height:1.85;}
  .highlight-box > *{margin:0;}

  #kepek{border-top:1px solid var(--border);}
  .photo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:2.5rem;}
  .photo-item{border-radius:12px;overflow:hidden;position:relative;transition:transform .3s,box-shadow .3s;cursor:pointer;display:flex;flex-direction:column;}
  .photo-item:hover{transform:scale(1.03);box-shadow:0 12px 40px rgba(0,0,0,.4);}
  .photo-item.span2{grid-column:span 2;}
  .photo-item .photo-img-wrap{aspect-ratio:4/3;overflow:hidden;flex-shrink:0;}
  .photo-item.span2 .photo-img-wrap{aspect-ratio:16/9;}
  .photo-item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s;}
  .photo-item:hover img{transform:scale(1.06);}
  .photo-caption{padding:.55rem .75rem;font-size:.8rem;color:rgba(255,255,255,.75);background:rgba(10,20,50,.7);line-height:1.35;min-height:2rem;display:flex;align-items:center;}
  /* Lightbox */
  #lb{display:none;position:fixed;inset:0;background:rgba(0,0,0,.93);z-index:9000;align-items:center;justify-content:center;flex-direction:column;}
  #lb.open{display:flex;}
  #lb-img{max-width:92vw;max-height:78vh;border-radius:10px;object-fit:contain;box-shadow:0 8px 40px rgba(0,0,0,.6);}
  #lb-cap{margin-top:.85rem;color:rgba(255,255,255,.8);font-size:.9rem;text-align:center;max-width:80vw;}
  #lb-cnt{color:rgba(255,255,255,.4);font-size:.75rem;margin-top:.3rem;}
  .lb-arr{position:fixed;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.13);border:none;color:#fff;font-size:1.7rem;width:50px;height:50px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s;z-index:9001;}
  .lb-arr:hover{background:rgba(255,255,255,.28);}
  #lb-prev{left:12px;}#lb-next{right:12px;}
  #lb-close{position:fixed;top:14px;right:16px;background:rgba(255,255,255,.13);border:none;color:#fff;font-size:1.2rem;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:9001;}
  #lb-close:hover{background:rgba(255,255,255,.28);}

  #kovesd{border-top:1px solid var(--border);}
  .social-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-top:2.5rem;}
  .social-card{border-radius:16px;padding:2rem 1.5rem;text-align:center;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:1rem;border:1px solid var(--border);transition:transform .3s,box-shadow .3s,border-color .3s;}
  .social-card:hover{transform:translateY(-6px);}
  .social-icon{font-size:2.8rem;}
  .social-name{font-family:'Inter',sans-serif;font-weight:700;font-size:1.1rem;color:var(--white);}
  .social-handle{font-size:.85rem;color:var(--text-muted);}

  #csatlakozz{border-top:1px solid var(--border);}
  .join-layout{display:grid;grid-template-columns:1fr auto;gap:4rem;align-items:center;margin-top:2.5rem;}
  .join-text > div{color:var(--text-muted);font-size:1.05rem;line-height:1.8;margin-bottom:1.5rem;}
  .join-text > div:last-child{margin-bottom:0;}
  .qr-block{display:flex;flex-direction:column;align-items:center;gap:1rem;}
  .qr-wrap{background:#fff;border-radius:16px;padding:14px;box-shadow:0 8px 40px rgba(0,0,0,.3);position:relative;}
  .qr-wrap::before{content:'';position:absolute;inset:-2px;border-radius:18px;background:linear-gradient(135deg,var(--red),var(--blue-ring),var(--green));z-index:-1;}
  .qr-block img{width:200px;height:200px;border-radius:4px;display:block;object-fit:cover;}
  .qr-label{font-family:'Inter',sans-serif;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);text-align:center;}

  #kapcsolat{border-top:1px solid var(--border);}
  .contact-box{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:2.5rem;}
  .contact-item{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:2rem;display:flex;flex-direction:column;gap:.75rem;}
  .contact-item .ci-label{font-family:'Inter',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--blue-ring);}
  .contact-item .ci-value{font-size:1.1rem;color:var(--white);font-weight:500;}
  .contact-item a{color:var(--blue-ring);text-decoration:none;}
  .contact-item a:hover{text-decoration:underline;}

  footer{border-top:1px solid var(--border);padding:2.5rem 5vw;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;color:var(--text-muted);font-size:.82rem;}
  .footer-logo{display:flex;align-items:center;gap:10px;}
  .footer-logo img{height:40px;width:40px;border-radius:50%;object-fit:cover;background:#fff;}
  .footer-logo span{font-family:'Inter',sans-serif;font-weight:700;font-size:.8rem;}

  .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease;}
  .reveal.visible{opacity:1;transform:translateY(0);}

  @media(max-width:900px){
    .nav-links,.nav-cta{display:none;}
    .hamburger{display:flex;}
    .mission-grid{grid-template-columns:1fr;}
    .social-grid{grid-template-columns:1fr;}
    #kepviselok > div{grid-template-columns:1fr;}
    .join-layout{grid-template-columns:1fr;}
    .contact-box{grid-template-columns:1fr;}
    .photo-grid{grid-template-columns:1fr 1fr;}
    .photo-item.span2{grid-column:span 2;}
  }
  @media(max-width:600px){
    #kepviselok > div{grid-template-columns:1fr 1fr;}
  }
  @media(max-width:440px){
    #kepviselok > div{grid-template-columns:1fr;}
    .photo-grid{grid-template-columns:1fr;}
    .photo-item.span2{grid-column:span 1;}
  }

  .hero-lead p,
  .text-block p,
  .text-block .highlight-box p,
  .mission-card p,
  .join-text p {
    margin-bottom: 0;
    line-height: inherit;
  }
  .hero-lead p + p,
  .text-block p + p,
  .mission-card p + p,
  .join-text p + p {
    margin-top: 0.5em;
  }
  .hero-lead p:empty,
  .text-block p:empty,
  .mission-card p:empty,
  .join-text p:empty {
    margin: 0;
    line-height: 0.8;
  }

  /* ÉVSZAK TÉMÁK */
  [data-season="default"] .section-label { color:var(--blue-ring); }
  [data-season="default"] .hero-badge { color:var(--blue-ring); }

  [data-season="winter"] { --season-accent:#a8d8ff; --season-bg:rgba(168,216,255,0.06); }
  [data-season="winter"] .section-label { color:#a8d8ff; }
  [data-season="winter"] .section-label::after { background:rgba(168,216,255,.2); }
  [data-season="winter"] .hero-badge { color:#a8d8ff; border-color:rgba(168,216,255,.3); background:rgba(168,216,255,.08); }
  [data-season="winter"][data-theme="dark"] body { background:#060e24; }
  [data-season="winter"][data-theme="dark"] nav  { background:rgba(6,14,36,.92); }
  [data-season="winter"][data-theme="dark"] .mission-card { border-color:rgba(168,216,255,.15); }
  [data-season="winter"][data-theme="dark"] .hero-bg { background:
    radial-gradient(ellipse 60% 70% at 50% 0%,rgba(168,216,255,.14) 0%,transparent 70%),
    radial-gradient(ellipse 40% 50% at 20% 80%,rgba(100,180,255,.06) 0%,transparent 60%),
    #060e24; }
  [data-season="winter"][data-theme="light"] body { background:#e8f4ff; }
  [data-season="winter"][data-theme="light"] #fooldal { background:#d0e8ff; }
  [data-season="winter"][data-theme="light"] section { background:#e8f4ff; }

  [data-season="spring"] { --season-accent:#5cbf6a; --season-bg:rgba(92,191,106,0.06); }
  [data-season="spring"] .section-label { color:#5cbf6a; }
  [data-season="spring"] .section-label::after { background:rgba(92,191,106,.2); }
  [data-season="spring"] .hero-badge { color:#5cbf6a; border-color:rgba(92,191,106,.3); background:rgba(92,191,106,.08); }
  [data-season="spring"] .btn-primary { background:#2e9e3e; box-shadow:0 4px 20px rgba(46,158,62,.35); }
  [data-season="spring"] .btn-primary:hover { background:#1e7a2c; }
  [data-season="spring"][data-theme="dark"] body { background:#061806; }
  [data-season="spring"][data-theme="dark"] nav  { background:rgba(6,24,6,.92); }
  [data-season="spring"][data-theme="dark"] .hero-bg { background:
    radial-gradient(ellipse 60% 70% at 50% 0%,rgba(92,191,106,.12) 0%,transparent 70%),
    radial-gradient(ellipse 40% 50% at 30% 80%,rgba(255,200,0,.06) 0%,transparent 60%),
    #061806; }
  [data-season="spring"][data-theme="light"] body { background:#f0fff2; }
  [data-season="spring"][data-theme="light"] #fooldal { background:#d8f5dc; }
  [data-season="spring"][data-theme="light"] section { background:#f0fff2; }

  [data-season="summer"] { --season-accent:#ffb830; --season-bg:rgba(255,184,48,0.06); }
  [data-season="summer"] .section-label { color:#ffb830; }
  [data-season="summer"] .section-label::after { background:rgba(255,184,48,.2); }
  [data-season="summer"] .hero-badge { color:#ffb830; border-color:rgba(255,184,48,.3); background:rgba(255,184,48,.08); }
  [data-season="summer"] .btn-primary { background:#e07b00; box-shadow:0 4px 20px rgba(224,123,0,.35); }
  [data-season="summer"] .btn-primary:hover { background:#c06800; }
  [data-season="summer"][data-theme="dark"] body { background:#0d1408; }
  [data-season="summer"][data-theme="dark"] nav  { background:rgba(13,20,8,.92); }
  [data-season="summer"][data-theme="dark"] .hero-bg { background:
    radial-gradient(ellipse 60% 70% at 50% 0%,rgba(255,184,48,.12) 0%,transparent 70%),
    radial-gradient(ellipse 40% 50% at 20% 80%,rgba(0,180,120,.06) 0%,transparent 60%),
    #0d1408; }
  [data-season="summer"][data-theme="light"] body { background:#fffbf0; }
  [data-season="summer"][data-theme="light"] #fooldal { background:#fff3cc; }
  [data-season="summer"][data-theme="light"] section { background:#fffbf0; }

  [data-season="autumn"] { --season-accent:#e8843a; --season-bg:rgba(232,132,58,0.06); }
  [data-season="autumn"] .section-label { color:#e8843a; }
  [data-season="autumn"] .section-label::after { background:rgba(232,132,58,.2); }
  [data-season="autumn"] .hero-badge { color:#e8843a; border-color:rgba(232,132,58,.3); background:rgba(232,132,58,.08); }
  [data-season="autumn"] .btn-primary { background:#c0581a; box-shadow:0 4px 20px rgba(192,88,26,.35); }
  [data-season="autumn"] .btn-primary:hover { background:#a04010; }
  [data-season="autumn"][data-theme="dark"] body { background:#0e0900; }
  [data-season="autumn"][data-theme="dark"] nav  { background:rgba(14,9,0,.92); }
  [data-season="autumn"][data-theme="dark"] .hero-bg { background:
    radial-gradient(ellipse 60% 70% at 50% 0%,rgba(232,132,58,.12) 0%,transparent 70%),
    radial-gradient(ellipse 40% 50% at 20% 80%,rgba(180,60,0,.08) 0%,transparent 60%),
    #0e0900; }
  [data-season="autumn"][data-theme="light"] body { background:#fff8f0; }
  [data-season="autumn"][data-theme="light"] #fooldal { background:#ffe8cc; }
  [data-season="autumn"][data-theme="light"] section { background:#fff8f0; }

  #season-canvas { position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:9999; opacity:.7; }
  .season-banner { position:fixed; bottom:1.5rem; right:1.5rem; z-index:500; background:var(--card-bg); border:1px solid var(--border); backdrop-filter:blur(12px); border-radius:16px; padding:1rem 1.25rem; max-width:260px; box-shadow:0 8px 30px rgba(0,0,0,.3); animation:bannerIn .5s ease both; }
  @keyframes bannerIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
  .season-banner-title { font-size:.78rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; margin-bottom:.4rem; }
  .season-banner-count { font-size:2rem; font-weight:800; line-height:1; }
  .season-banner-sub   { font-size:.75rem; color:var(--text-muted); margin-top:.2rem; }
  .season-banner-close { position:absolute; top:.5rem; right:.6rem; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1rem; }

  .logo-hat-wrap { position:relative; display:inline-block; }
  .logo-hat { position:absolute; top:-18px; left:-8px; font-size:2.2rem; transform:rotate(-15deg); pointer-events:none; z-index:2; filter:drop-shadow(0 2px 4px rgba(0,0,0,.3)); animation:hatWiggle 3s ease-in-out infinite; }
  @keyframes hatWiggle { 0%,100%{transform:rotate(-15deg)} 50%{transform:rotate(-12deg) translateY(-2px)} }
</style>
</head>
<body>

<nav>
  <div class="nav-logo">
    <?php if ($logo_url): ?>
      <img src="<?= htmlspecialchars($logo_url) ?>?v=<?= time() ?>" alt="Logo">
    <?php endif; ?>
    <div class="nav-brand">Ifjúsági TISZA Sziget<br>Győr</div>
  </div>
  <ul class="nav-links">
    <li><a href="#rolunk">Rólunk</a></li>
    <li><a href="#celaink">Céljaink</a></li>
    <?php if (!empty($representatives)): ?><li><a href="#kepviselok">Képviselőink</a></li><?php endif; ?>
    <?php if (!empty($gallery)): ?><li><a href="#kepek">Képek</a></li><?php endif; ?>
    <?php if (!empty($social_links)): ?><li><a href="#kovesd">Közösség</a></li><?php endif; ?>
    <li><a href="#kapcsolat">Kapcsolat</a></li>
  </ul>
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="theme-toggle-btn" onclick="toggleTheme()" title="Világos/sötét" style="width:38px;height:38px;border-radius:10px;border:1.5px solid var(--border);background:var(--card-bg);color:var(--white);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">🌙</button>
    <a href="<?= htmlspecialchars($join['join_url'] ?? '#') ?>" class="nav-cta" target="_blank">Csatlakozz!</a>
  </div>
  <button class="hamburger" onclick="toggleMenu()" aria-label="Menü">
    <span></span><span></span><span></span>
  </button>
</nav>

<div class="mobile-menu" id="mobileMenu">
  <a href="#rolunk"    onclick="closeMenu()">Rólunk</a>
  <a href="#celaink"   onclick="closeMenu()">Céljaink</a>
  <?php if (!empty($representatives)): ?><a href="#kepviselok" onclick="closeMenu()">Képviselőink</a><?php endif; ?>
  <?php if (!empty($gallery)): ?><a href="#kepek" onclick="closeMenu()">Képek</a><?php endif; ?>
  <?php if (!empty($social_links)): ?><a href="#kovesd" onclick="closeMenu()">Közösség</a><?php endif; ?>
  <a href="#kapcsolat" onclick="closeMenu()">Kapcsolat</a>
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="theme-toggle-btn" onclick="toggleTheme()" title="Világos/sötét" style="width:38px;height:38px;border-radius:10px;border:1.5px solid var(--border);background:var(--card-bg);color:var(--white);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">🌙</button>
    <a href="<?= htmlspecialchars($join['join_url'] ?? '#') ?>" class="nav-cta" target="_blank">Csatlakozz!</a>
  </div>
</div>

<!-- HERO -->
<div id="fooldal">
  <div class="hero-bg"></div>
  <div class="hero-content hero">
    <?php if ($logo_url): ?>
    <div class="hero-logo-wrap">
      <div class="logo-hat-wrap">
        <?php if ($current_season === 'winter' && $effects_on): ?>
          <span class="logo-hat">🎅</span>
        <?php elseif ($current_season === 'spring' && $effects_on): ?>
          <span class="logo-hat" style="font-size:1.8rem;top:-14px;left:-4px;">🌸</span>
        <?php elseif ($current_season === 'summer' && $effects_on): ?>
          <span class="logo-hat" style="font-size:1.8rem;top:-14px;left:-2px;transform:rotate(10deg);">🌞</span>
        <?php elseif ($current_season === 'autumn' && $effects_on): ?>
          <span class="logo-hat" style="font-size:1.8rem;top:-14px;left:-4px;transform:rotate(-10deg);">🍂</span>
        <?php endif; ?>
        <img class="hero-logo" src="<?= htmlspecialchars($logo_url) ?>?v=<?= time() ?>" alt="Ifjúsági TISZA Sziget Győr">
      </div>
    </div>
    <?php endif; ?>
    <div class="hero-badge" data-preview-key="hero.badge"><?= htmlspecialchars($hero['badge'] ?? 'Győr · Ifjúsági Közösség') ?></div>
    <h1><span data-preview-key="hero.title_line1"><?= htmlspecialchars($hero['title_line1'] ?? 'Építsük együtt') ?></span><br>
        <em data-preview-key="hero.title_line2"><?= htmlspecialchars($hero['title_line2'] ?? 'Győr jövőjét!') ?></em></h1>
    <div class="hero-lead reveal" data-preview-key="hero.lead"><?= $hero['lead'] ?? '' ?></div>
    <div class="hero-btns">
      <a href="<?= htmlspecialchars($join['join_url'] ?? '#') ?>" class="btn-primary" target="_blank" data-preview-key="join.join_url" data-hrefkey="true">
        <span data-preview-key="hero.btn_primary"><?= htmlspecialchars($hero['btn_primary'] ?? 'Csatlakozz hozzánk!') ?></span>
      </a>
      <a href="#rolunk" class="btn-secondary" data-preview-key="hero.btn_secondary"><?= htmlspecialchars($hero['btn_secondary'] ?? 'Tudj meg többet') ?></a>
    </div>
    <div class="scroll-hint"><span>Görgess</span><div class="scroll-arrow"></div></div>
  </div>
</div>

<!-- RÓLUNK -->
<section id="rolunk">
  <div class="section-label">Rólunk</div>
  <h2 class="section-title reveal" data-preview-key="about.section_title"><?= htmlspecialchars($about['section_title'] ?? 'Közélet. Kultúra. Közösség.') ?></h2>
  <div class="mission-grid">
    <?php for ($i = 1; $i <= 4; $i++): ?>
    <div class="mission-card reveal">
      <div class="mission-icon" data-preview-key="about.card<?= $i ?>_icon"><?= htmlspecialchars($about["card{$i}_icon"] ?? '') ?></div>
      <h3 data-preview-key="about.card<?= $i ?>_title"><?= htmlspecialchars($about["card{$i}_title"] ?? '') ?></h3>
      <div class="mission-card-text" data-preview-key="about.card<?= $i ?>_text"><?= $about["card{$i}_text"] ?? '' ?></div>
    </div>
    <?php endfor; ?>
  </div>
</section>

<!-- CÉLJAINK -->
<section id="celaink">
  <div class="section-label">Céljaink</div>
  <h2 class="section-title reveal" data-preview-key="goals.section_title"><?= htmlspecialchars($goals['section_title'] ?? '') ?></h2>
  <div class="text-block">
    <div class="reveal" data-preview-key="goals.para1"><?= $goals['para1'] ?? '' ?></div>
    <div class="highlight-box reveal" data-preview-key="goals.box1"><?= $goals['box1'] ?? '' ?></div>
    <div class="reveal" data-preview-key="goals.para2"><?= $goals['para2'] ?? '' ?></div>
    <div class="highlight-box reveal" data-preview-key="goals.box2"><?= $goals['box2'] ?? '' ?></div>
    <div class="reveal" data-preview-key="goals.para3"><?= $goals['para3'] ?? '' ?></div>
  </div>
</section>

<!-- KÉPVISELŐINK -->
<?php if (!empty($representatives)): ?>
<section id="kepviselok" style="border-top:1px solid var(--border);">
  <div class="section-label">Képviseletek</div>
  <h2 class="section-title reveal">Képviselőink</h2>
  <!-- FLEXBOX + KÖZÉPRE IGAZÍTÁS -->
  <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:2.5rem;margin-top:2.5rem;">
    <?php foreach ($representatives as $rep): ?>
    <div class="representative-card reveal" style="flex:0 0 280px;max-width:100%;display:flex;flex-direction:column;align-items:center;text-align:center;">
      <!-- PROFILKÉP -->
      <div style="width:160px;height:160px;border-radius:50%;overflow:hidden;background:var(--card-bg);border:2px solid var(--border);margin-bottom:1.5rem;flex-shrink:0;">
        <?php if ($rep['link_url']): ?>
          <a href="<?= htmlspecialchars($rep['link_url']) ?>" target="_blank" style="display:block;width:100%;height:100%;">
        <?php endif; ?>
        <?php if ($rep['filename'] && file_exists(UPLOAD_DIR . $rep['filename'])): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($rep['filename']) ?>"
               style="width:100%;height:100%;object-fit:cover;display:block;"
               alt="<?= htmlspecialchars($rep['full_name']) ?>">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:2rem;">👤</div>
        <?php endif; ?>
        <?php if ($rep['link_url']): ?>
          </a>
        <?php endif; ?>
      </div>

      <h3 style="font-family:'Inter',sans-serif;font-size:1.1rem;font-weight:700;color:var(--white);margin:0 0 .5rem 0;">
        <?= htmlspecialchars($rep['full_name']) ?>
      </h3>

      <?php if ($rep['bio']): ?>
        <div style="color:var(--text-muted);font-size:.95rem;line-height:1.6;margin:0;max-width:220px;">
          <?= $rep['bio'] ?>
        </div>
      <?php endif; ?>

      <?php
        $has_content_img  = !empty($rep['content_filename']) && file_exists(UPLOAD_DIR . $rep['content_filename']);
        $has_extra_text   = trim(strip_tags($rep['extra_text'] ?? '')) !== '';
      ?>
      <?php if ($has_extra_text || $has_content_img): ?>
      <div style="width:100%;margin-top:1rem;padding:1rem;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);">
        <?php if ($has_content_img): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($rep['content_filename']) ?>?v=<?= time() ?>"
               style="width:100%;border-radius:12px;margin-bottom:1rem;"
               alt="Extra kép">
        <?php endif; ?>
        <?php $extra_clean = trim(strip_tags($rep['extra_text'] ?? '')); if ($has_extra_text): ?>
          <div style="color:var(--text-muted);line-height:1.7;text-align:left;">
            <?= $rep['extra_text'] ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- KÉPEK -->
<?php if (!empty($gallery)): ?>
<section id="kepek">
  <div class="section-label">Galéria</div>
  <h2 class="section-title reveal">Velünk volt, velünk lesz</h2>
  <div class="photo-grid" id="photoGrid">
    <?php foreach ($gallery as $idx => $img): ?>
    <div class="photo-item <?= $img['span2'] ? 'span2' : '' ?> reveal"
         onclick="lbOpen(<?= $idx ?>)" title="Kattints a nagyításhoz">
      <div class="photo-img-wrap">
        <img src="<?= UPLOAD_URL . htmlspecialchars($img['filename']) ?>"
             alt="<?= htmlspecialchars($img['alt_text']) ?>"
             loading="lazy">
      </div>
      <?php if (!empty($img['caption'])): ?>
        <div class="photo-caption"><?= htmlspecialchars($img['caption']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- LIGHTBOX -->
  <div id="lb">
    <button id="lb-close" onclick="lbClose()">✕</button>
    <button class="lb-arr" id="lb-prev" onclick="lbNav(-1)">‹</button>
    <img id="lb-img" src="" alt="">
    <div id="lb-cap"></div>
    <div id="lb-cnt"></div>
    <button class="lb-arr" id="lb-next" onclick="lbNav(1)">›</button>
  </div>
  <script>
  var _lbData = <?= json_encode(array_values(array_map(fn($img) => [
    'src'     => UPLOAD_URL . $img['filename'],
    'caption' => $img['caption'] ?? '',
    'alt'     => $img['alt_text'] ?? '',
  ], $gallery))) ?>;
  var _lbIdx = 0;
  function lbOpen(i){ _lbIdx=i; _lbRender(); document.getElementById('lb').classList.add('open'); document.body.style.overflow='hidden'; }
  function lbClose(){ document.getElementById('lb').classList.remove('open'); document.body.style.overflow=''; }
  function lbNav(d){ _lbIdx=(_lbIdx+d+_lbData.length)%_lbData.length; _lbRender(); }
  function _lbRender(){
    var it=_lbData[_lbIdx];
    document.getElementById('lb-img').src=it.src;
    document.getElementById('lb-img').alt=it.alt;
    document.getElementById('lb-cap').textContent=it.caption;
    document.getElementById('lb-cnt').textContent=(_lbIdx+1)+' / '+_lbData.length;
  }
  document.addEventListener('keydown',function(e){
    if(!document.getElementById('lb').classList.contains('open'))return;
    if(e.key==='ArrowLeft')lbNav(-1);
    if(e.key==='ArrowRight')lbNav(1);
    if(e.key==='Escape')lbClose();
  });
  document.getElementById('lb').addEventListener('click',function(e){ if(e.target===this)lbClose(); });
  </script>
</section>
<?php endif; ?>

<!-- SOCIAL -->
<?php if (!empty($social_links)): ?>
<section id="kovesd">
  <div class="section-label">Közösségi média</div>
  <h2 class="section-title reveal">Kövess minket!</h2>
  <div class="social-grid" style="grid-template-columns:repeat(<?= min(count($social_links),3) ?>,1fr);">
    <?php foreach ($social_links as $sl): ?>
    <a href="<?= htmlspecialchars($sl['url']) ?>" class="social-card reveal" target="_blank"
       style="background:<?= social_bg($sl['platform']) ?>;">
      <div class="social-icon"><?= social_svg($sl['platform']) ?></div>
      <div class="social-name"><?= htmlspecialchars($sl['label']) ?></div>
      <div class="social-handle"><?= htmlspecialchars($sl['handle']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- CSATLAKOZZ -->
<section id="csatlakozz">
  <div class="section-label">Csatlakozás</div>
  <h2 class="section-title reveal" data-preview-key="join.section_title"><?= htmlspecialchars($join['section_title'] ?? '') ?></h2>
  <div class="join-layout">
    <div class="join-text">
      <div class="reveal" data-preview-key="join.para1"><?= $join['para1'] ?? '' ?></div>
      <div class="reveal" data-preview-key="join.para2"><?= $join['para2'] ?? '' ?></div>
      <a href="<?= htmlspecialchars($join['join_url'] ?? '#') ?>" class="btn-primary reveal" target="_blank" style="display:inline-block;" data-preview-key="join.join_url" data-hrefkey="true">
        <span data-preview-key="join.btn_text"><?= htmlspecialchars($join['btn_text'] ?? 'Regisztrálj most →') ?></span>
      </a>
    </div>
    <?php if ($qr_url): ?>
    <div class="qr-block reveal">
      <div class="qr-wrap">
        <img src="<?= htmlspecialchars($qr_url) ?>?v=<?= time() ?>" alt="QR kód – Csatlakozás">
      </div>
      <div class="qr-label" data-preview-key="join.qr_label"><?= htmlspecialchars($join['qr_label'] ?? 'Olvasd le a QR kódot') ?></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- KAPCSOLAT -->
<section id="kapcsolat">
  <div class="section-label">Kapcsolat</div>
  <h2 class="section-title reveal">Írj nekünk!</h2>
  <div class="contact-box">
    <div class="contact-item reveal">
      <div class="ci-label">E-mail cím</div>
      <div class="ci-value"><a href="mailto:<?= htmlspecialchars($contact['email'] ?? '') ?>" data-preview-key="contact.email" data-mailto="true">
        <?= htmlspecialchars($contact['email'] ?? '') ?>
      </a></div>
    </div>
    <div class="contact-item reveal">
      <div class="ci-label">Csatlakozás</div>
      <div class="ci-value"><a href="<?= htmlspecialchars($contact['join_url'] ?? '#') ?>" target="_blank" data-preview-key="contact.join_url" data-hrefkey="true">
        Regisztrációs link →
      </a></div>
    </div>
    <div class="contact-item reveal">
      <div class="ci-label">Szervezet</div>
      <div class="ci-value" data-preview-key="contact.org_name"><?= htmlspecialchars($contact['org_name'] ?? '') ?></div>
    </div>
    <div class="contact-item reveal">
      <div class="ci-label">Helyszín</div>
      <div class="ci-value"><?= htmlspecialchars($contact['location'] ?? '') ?></div>
    </div>
  </div>
</section>

<footer id="footer">
  <div class="footer-logo">
    <?php if ($logo_url): ?>
      <img src="<?= htmlspecialchars($logo_url) ?>?v=<?= time() ?>" alt="Logo">
    <?php endif; ?>
    <span data-preview-key="contact.org_name"><?= htmlspecialchars($contact['org_name'] ?? 'Ifjúsági TISZA Sziget · Győr') ?></span>
  </div>
  <div data-preview-key="footer.copyright"><?= htmlspecialchars($footer['copyright'] ?? '© 2025 Ifjúsági TISZA Sziget Győr') ?></div>
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <?php if ($dev_show): ?>
    <a href="<?= htmlspecialchars($dev_url) ?>" <?= (str_starts_with($dev_url,'http')?'target="_blank"':'') ?> style="background:var(--blue-ring);color:#fff;padding:.85rem 1.2rem;border-radius:999px;text-decoration:none;font-weight:700;box-shadow:0 10px 20px rgba(0,0,0,.12);">
      <span style="display:inline-flex;align-items:center;gap:.5rem;">
        <span><?= htmlspecialchars($dev_icon) ?></span>
        Webfejlesztő: <?= htmlspecialchars($dev_name) ?>
      </span>
    </a>
    <?php endif; ?>
    <a href="mailto:<?= htmlspecialchars($contact['email'] ?? '') ?>" style="color:var(--blue-ring);text-decoration:none;" data-preview-key="contact.email" data-mailto="true">
      <?= htmlspecialchars($contact['email'] ?? '') ?>
    </a>
  </div>
</footer>

<?php if ($effects_on): ?>
<canvas id="season-canvas"></canvas>
<?php endif; ?>

<?php if ($effects_on && $current_season === 'winter' && $xmas_on && $xmas_days <= 60): ?>
<div class="season-banner" id="seasonBanner">
  <button class="season-banner-close" onclick="document.getElementById('seasonBanner').remove()">✕</button>
  <div class="season-banner-title">🎄 Karácsonyig</div>
  <div class="season-banner-count" style="color:#a8d8ff;"><?= $xmas_days ?></div>
  <div class="season-banner-sub">nap van hátra<br><?= $xmas->format('Y. december 25.') ?></div>
</div>
<?php elseif ($effects_on && $current_season === 'spring' && $easter_on && $easter_days <= 40): ?>
<div class="season-banner" id="seasonBanner">
  <button class="season-banner-close" onclick="document.getElementById('seasonBanner').remove()">✕</button>
  <div class="season-banner-title">🐣 Húsvétig</div>
  <div class="season-banner-count" style="color:#5cbf6a;"><?= $easter_days ?></div>
  <div class="season-banner-sub">nap van hátra<br><?= $easter_obj->format('Y. F j.') ?></div>
</div>
<?php endif; ?>

<script>
(function(){
  const saved = localStorage.getItem('itsz_theme_manual');
  if (saved) {
    document.documentElement.setAttribute('data-theme', saved);
  } else {
    const h = new Date().getHours();
    const auto = (h >= 19 || h < 7) ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', auto);
    localStorage.setItem('itsz_theme', auto);
  }
})();

function toggleTheme(){
  const cur  = document.documentElement.getAttribute('data-theme');
  const next = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('itsz_theme_manual', next);
  localStorage.setItem('itsz_theme', next);
  const btns = document.querySelectorAll('.theme-toggle-btn');
  btns.forEach(b => b.textContent = next === 'dark' ? '🌙' : '☀️');
}

document.addEventListener('DOMContentLoaded', function(){
  const saved = localStorage.getItem('itsz_theme_manual') || localStorage.getItem('itsz_theme') || 'dark';
  const btns = document.querySelectorAll('.theme-toggle-btn');
  btns.forEach(b => b.textContent = saved === 'dark' ? '🌙' : '☀️');
});

(function(){
  const canvas = document.getElementById('season-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const season = document.documentElement.getAttribute('data-season') || 'winter';

  const configs = {
    winter: { emoji: ['❄️','❅','❆','·'], count: 60, speed: 0.8, swing: 1.2, size: [10,22] },
    spring: { emoji: ['🌸','🌷','✿','·'], count: 35, speed: 0.5, swing: 1.5, size: [12,22] },
    summer: { emoji: ['🌿','🌞','·','✦'],  count: 25, speed: 0.4, swing: 0.8, size: [12,20] },
    autumn: { emoji: ['🍂','🍁','🍃','·'], count: 40, speed: 0.7, swing: 1.8, size: [12,22] },
  };
  const cfg = configs[season] || configs.winter;

  canvas.width  = window.innerWidth;
  canvas.height = window.innerHeight;
  window.addEventListener('resize', () => {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
  });

  const particles = Array.from({length: cfg.count}, () => ({
    x:     Math.random() * canvas.width,
    y:     Math.random() * canvas.height - canvas.height,
    size:  cfg.size[0] + Math.random() * (cfg.size[1] - cfg.size[0]),
    speed: cfg.speed * (0.5 + Math.random()),
    swing: cfg.swing * (Math.random() - 0.5),
    angle: Math.random() * Math.PI * 2,
    emoji: cfg.emoji[Math.floor(Math.random() * cfg.emoji.length)],
    opacity: 0.4 + Math.random() * 0.5,
  }));

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles.forEach(p => {
      ctx.save();
      ctx.globalAlpha = p.opacity;
      ctx.font = p.size + 'px serif';
      ctx.fillText(p.emoji, p.x, p.y);
      ctx.restore();
      p.y    += p.speed;
      p.x    += Math.sin(p.angle) * p.swing;
      p.angle += 0.02;
      if (p.y > canvas.height + 30) {
        p.y = -30;
        p.x = Math.random() * canvas.width;
      }
    });
    requestAnimationFrame(draw);
  }
  draw();
})();

document.addEventListener('DOMContentLoaded', function(){
  const preview = new URLSearchParams(window.location.search).get('preview');
  if (preview === '1') {
    const raw = localStorage.getItem('itsz_preview');
    if (raw) {
      try {
        const previewData = JSON.parse(raw);
        if (previewData && previewData.section && previewData.fields) {
          Object.entries(previewData.fields).forEach(([field, value]) => {
            const key = `${previewData.section}.${field}`;
            document.querySelectorAll(`[data-preview-key="${key}"]`).forEach(el => {
              if (el.dataset.hrefkey === 'true') {
                el.href = value || '#';
              }
              if (el.dataset.mailto === 'true') {
                el.href = 'mailto:' + (value || '');
              }
              if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.value = value;
              } else if (el.tagName === 'A' && el.dataset.hrefkey !== 'true') {
                el.textContent = value;
              } else {
                el.innerHTML = value;
              }
            });
          });
        }
      } catch (err) {
        console.warn('Preview data parse failed:', err);
      }
    }
  }
});

function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
function closeMenu(){document.getElementById('mobileMenu').classList.remove('open');}
const obs=new IntersectionObserver((e)=>{e.forEach(x=>{if(x.isIntersecting)x.target.classList.add('visible');})},{threshold:0.1});
document.querySelectorAll('.reveal').forEach(el=>obs.observe(el));
document.addEventListener('click',(e)=>{
  const m=document.getElementById('mobileMenu');
  if(m.classList.contains('open')&&!m.contains(e.target)&&!e.target.closest('.hamburger'))m.classList.remove('open');
});
</script>

</body>
</html>