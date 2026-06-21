CREATE DATABASE IF NOT EXISTS itsz_gyor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE itsz_gyor;

-- ─── ADMIN FELHASZNÁLÓK ───────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(80)  NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  full_name   VARCHAR(120) NOT NULL,
  role        ENUM('superadmin','admin','kampanyfonok') NOT NULL DEFAULT 'admin',
  last_login  DATETIME,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ─── WEBOLDAL TARTALOM ────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_content (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  section      VARCHAR(60)  NOT NULL,
  key_name     VARCHAR(80)  NOT NULL,
  label        VARCHAR(120) NOT NULL,
  value        MEDIUMTEXT,
  type         ENUM('text','textarea','html','url','email','image') NOT NULL DEFAULT 'text',
  sort_order   INT DEFAULT 0,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY section_key (section, key_name)
) ENGINE=InnoDB;

INSERT INTO site_content (section, key_name, label, value, type, sort_order) VALUES
-- HERO szekció
('hero','badge',        'Jelvény szöveg',        'Győr · Ifjúsági Közösség',           'text',     1),
('hero','title_line1',  'Főcím 1. sor',          'Építsük együtt',                      'text',     2),
('hero','title_line2',  'Főcím 2. sor (kék)',    'Győr jövőjét!',                       'text',     3),
('hero','lead',         'Bevezető szöveg',        'Győr nemcsak a folyók és az ipar, hanem a <strong>fiatalok városa</strong> is. Hatalmas, tenni akaró ifjúsági bázissal rendelkezünk – és hiszünk abban, hogy a város valódi motorjai <strong>Ti vagytok!</strong>', 'html', 4),
('hero','btn_primary',  'Elsődleges gomb szöveg', 'Csatlakozz hozzánk!',                'text',     5),
('hero','btn_secondary','Másodlagos gomb szöveg', 'Tudj meg többet',                    'text',     6),

-- RÓLUNK szekció
('about','section_title', 'Szekció cím',  'Közélet. Kultúra. Közösség.',  'text', 1),
('about','card1_icon',    'Kártya 1 ikon','🤝',                           'text', 2),
('about','card1_title',   'Kártya 1 cím', 'Közösség, ahol számít a szavad','text',3),
('about','card1_text',    'Kártya 1 szöveg','Egy olyan tér, ahol a kultúra, a szakmai fejlődés és a felelős ifjúságpolitika kéz a kézben jár. Ahol nem rólad döntenek, hanem veled.','textarea',4),
('about','card2_icon',    'Kártya 2 ikon','🏛️',                          'text', 5),
('about','card2_title',   'Kártya 2 cím', 'Képviselet és beleszólás',     'text', 6),
('about','card2_text',    'Kártya 2 szöveg','Országgyűlési képviselőinkkel – köztük Diószegi Judittal – azon dolgozunk, hogy a politika ismét az emberekről szóljon. Az ő tanári és addiktológiai konzultánsi háttere pontosan ismeri generációtok kihívásait.','textarea',7),
('about','card3_icon',    'Kártya 3 ikon','🎭',                           'text', 8),
('about','card3_title',   'Kártya 3 cím', 'Kulturális programok és élet', 'text', 9),
('about','card3_text',    'Kártya 3 szöveg','Minőségi kulturális programokat, pezsgő közösségi életet biztosítunk – hogy a győri fiatalok számára ne a költözés legyen az egyetlen alternatíva.','textarea',10),
('about','card4_icon',    'Kártya 4 ikon','🚀',                           'text', 11),
('about','card4_title',   'Kártya 4 cím', 'Nem ígérgetünk – cselekszünk','text', 12),
('about','card4_text',    'Kártya 4 szöveg','Ha eleged van a felszínes válaszokból és valódi beleszólást akarsz a város ügyeibe, ahol megértik a problémáidat – itt a helyed!','textarea',13),

-- CÉLJAINK szekció
('goals','section_title','Szekció cím',   'Csatlakozz az Ifjúsági Tisza Szigethez!','text',1),
('goals','para1',        'Bekezdés 1',    'Az <strong>Ifjúsági Tisza Sziget</strong> azért jött létre, hogy a fiatalokról ne csak a fejük felett hozzanak döntéseket, hanem Ti magatok alakíthassátok a helyi közéletet.','html',2),
('goals','box1',         'Kiemelő doboz 1','🤝 <strong>Mit kínálunk?</strong> Egy olyan közösséget, ahol a kultúra, a szakmai fejlődés és a felelős ifjúságpolitika kéz a kézben jár. Azon dolgozunk, hogy a politika ismét az emberekről szóljon.','html',3),
('goals','para2',        'Bekezdés 2',    'Célunk, hogy a győri fiatalok számára ne a <strong>költözés</strong> legyen az egyetlen alternatíva: minőségi kulturális programokat, pezsgő közösségi életet és valódi beleszólást biztosítunk a város ügyeibe.','html',4),
('goals','box2',         'Kiemelő doboz 2','🛡️ <strong>Közélet. Kultúra. Közösség.</strong> Nem ígérgetünk, hanem cselekszünk. Ha eleged van a felszínes válaszokból, és szeretnél egy olyan csapathoz tartozni, ahol a véleményed számít – <strong>itt a helyed!</strong>','html',5),
('goals','para3',        'Bekezdés 3',    '🚀 Vedd a kezedbe az irányítást! Csatlakozz a győri Ifjúsági Tisza Szigethez, és mutassuk meg, mekkora ereje van a tudatos, helyi fiataloknak! Vedd fel velünk a kapcsolatot, gyere el a következő találkozónkra, és <strong>formáljuk együtt a városunkat!</strong>','html',6),

-- CSATLAKOZZ szekció
('join','section_title','Szekció cím',      'Vedd a kezedbe az irányítást!',      'text',1),
('join','para1',        'Bekezdés 1',       'Csatlakozz a győri Ifjúsági Tisza Szigethez, és mutassuk meg, mekkora ereje van a <strong>tudatos, helyi fiataloknak!</strong>','html',2),
('join','para2',        'Bekezdés 2',       'Vedd fel velünk a kapcsolatot, gyere el a következő találkozónkra, és formáljuk együtt a városunkat!','html',3),
('join','btn_text',     'Gomb szöveg',      'Regisztrálj most →',                 'text',4),
('join','join_url',     'Csatlakozási link','https://tiszavilag.hu/login?invitation=41ad7d25-ea07-476e-a450-6326c0621a03&pool=da0afc7d-7f3c-4441-b735-c9b08bc33fcf','url',5),
('join','qr_label',     'QR kód felirat',   'Olvasd le a QR kódot a csatlakozáshoz','text',6),

-- KAPCSOLAT szekció
('contact','email',     'E-mail cím',       'gyor@ifisziget.hu',                  'email',1),
('contact','org_name',  'Szervezet neve',   'Ifjúsági TISZA Sziget – Győr',       'text', 2),
('contact','location',  'Helyszín',         '📍 Győr, Magyarország',              'text', 3),
('contact','join_url',  'Regisztrációs link','https://tiszavilag.hu/login?invitation=41ad7d25-ea07-476e-a450-6326c0621a03&pool=da0afc7d-7f3c-4441-b735-c9b08bc33fcf','url',4),

-- FOOTER
('footer','copyright',  'Copyright szöveg', '© 2025 Ifjúsági TISZA Sziget Győr · Minden jog fenntartva','text',1),
('footer','nav_brand',  'Nav márkanév',     "Ifjúsági TISZA Sziget\nGőr",         'text',2);

-- ─── SOCIAL MÉDIA ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS social_links (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  platform   VARCHAR(30)  NOT NULL,
  label      VARCHAR(60)  NOT NULL,
  handle     VARCHAR(120) NOT NULL,
  url        VARCHAR(500) NOT NULL,
  icon_class VARCHAR(30)  NOT NULL DEFAULT 'fb',
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO social_links (platform, label, handle, url, icon_class, sort_order) VALUES
('facebook',  'Facebook',  'Ifjúsági TISZA Sziget – Győr',  'https://www.facebook.com/people/Ifj%C3%BAs%C3%A1gi-Tisza-Sziget-Gy%C5%91r/61585316303221/', 'fb', 1),
('instagram', 'Instagram', '@ifjusagi_tisza_sziget_gyor',   'https://www.instagram.com/ifjusagi_tisza_sziget_gyor/',                                     'ig', 2),
('tiktok',    'TikTok',    '@ifjusagitiszaszigetgyor',       'https://www.tiktok.com/@ifjusagitiszaszigetgyor',                                           'tt', 3);

-- ─── GALÉRIA ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS gallery (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  filename    VARCHAR(255) NOT NULL,
  alt_text    VARCHAR(255) NOT NULL DEFAULT '',
  caption     VARCHAR(255) NOT NULL DEFAULT '',
  span2       TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  INT DEFAULT 0,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── KÉPVISELŐINK ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS representatives (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  full_name       VARCHAR(120) NOT NULL,
  bio             TEXT,
  extra_text      TEXT,
  filename        VARCHAR(255),
  content_filename VARCHAR(255),
  link_url        VARCHAR(500),
  manager_id      INT,
  sort_order      INT DEFAULT 0,
  active          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Initial representatives (Diószegi Judit Dr Bóka Zsolt)
INSERT INTO representatives (full_name, bio, sort_order, active) VALUES
('Diószegi Judit', '', 1, 1),
('Dr Bóka Zsolt', '', 3, 1);

-- ─── TEVÉKENYSÉGNAPLÓ ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT,
  action     VARCHAR(120) NOT NULL,
  details    TEXT,
  ip         VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── ÉVSZAK & TÉMA BEÁLLÍTÁSOK ────────────────────────────
INSERT IGNORE INTO site_content (section, key_name, label, value, type, sort_order) VALUES
-- Évszak ütemezés (MM-DD formátum)
('seasons','winter_start',  'Tél kezdete (HH-NN)',    '12-01', 'text', 1),
('seasons','spring_start',  'Tavasz kezdete (HH-NN)', '03-01', 'text', 2),
('seasons','summer_start',  'Nyár kezdete (HH-NN)',   '06-01', 'text', 3),
('seasons','autumn_start',  'Ősz kezdete (HH-NN)',    '09-01', 'text', 4),
-- Melyik évszak aktív (auto = automatikus, winter/spring/summer/autumn = kézi)
('seasons','active_season', 'Aktív évszak', 'auto', 'text', 5),
-- Szezonális effektek be/ki
('seasons','effects_enabled','Szezonális effektek', '1', 'text', 6),
-- Karácsony visszaszámlálás
('seasons','xmas_enabled',  'Karácsony visszaszámlálás', '1', 'text', 7),
-- Húsvét visszaszámlálás
('seasons','easter_enabled', 'Húsvét visszaszámlálás', '1', 'text', 8),
-- Webfejlesztő adatok
('footer','developer_name', 'Webfejlesztő neve',   'Kertvéllesy László Zsolt',             'text', 10),
('footer','developer_url',  'Webfejlesztő linkje', 'mailto:kertvellesy.laszlo.zsolt@gmail.com', 'url', 11),
('footer','developer_icon', 'Webfejlesztő ikon',   '💻',                                    'text', 12),
('footer','developer_show', 'Webfejlesztő megjelenítése', '1',                               'text', 13);
