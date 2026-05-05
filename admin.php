<?php
// ===== أريج للأثاث المنزلي — لوحة تحكم المشرف =====
session_start();

// ── الإعدادات ──────────────────────────────────────────
define('ADMIN_PASSWORD', 'Areej#Riyadh2026');
define('BASE_DIR',       __DIR__ . '/assets/');
define('COVERS_DIR',     BASE_DIR . 'covers/');
define('COVERS_JSON',    COVERS_DIR . 'covers.json');
define('HOMEPAGE_DIR',   BASE_DIR . 'homepage/');
define('HOMEPAGE_JSON',  HOMEPAGE_DIR . 'gallery.json');
define('MAX_SIZE',       10 * 1024 * 1024); // 10 MB

$SECTIONS = [
    'bedroom'    => 'غرف النوم',
    'beds'       => 'السراير',
    'wardrobes'  => 'الدواليب',
    'mattresses' => 'المراتب',
    'sofas'      => 'المجالس والكنب',
];
$ALLOWED_EXT = ['jpg','jpeg','png','webp'];

// ── المصادقة ───────────────────────────────────────────
if (isset($_POST['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) { $_SESSION['admin'] = true; }
    else { $loginError = 'كلمة المرور غير صحيحة. حاول مجدداً.'; }
}
$loggedIn = !empty($_SESSION['admin']);

// ── وظائف مساعدة ──────────────────────────────────────

/** إعادة توليد gallery.json لقسم معين */
function regenerateJSON(string $section): void {
    $dir  = BASE_DIR . $section . '/';
    $json = $dir . 'gallery.json';
    $files = glob($dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    $names = array_map('basename', $files);
    natsort($names);
    file_put_contents($json, json_encode(['images' => array_values($names)], JSON_UNESCAPED_UNICODE));
}

/** قراءة covers.json */
function getCovers(): array {
    if (!file_exists(COVERS_JSON)) return [];
    return json_decode(file_get_contents(COVERS_JSON), true) ?: [];
}

/** حفظ covers.json */
function saveCovers(array $data): void {
    @mkdir(COVERS_DIR, 0755, true);
    file_put_contents(COVERS_JSON, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/** قراءة صور معرض الرئيسية */
function getHomepageImages(): array {
    if (!file_exists(HOMEPAGE_JSON)) return [];
    $data = json_decode(file_get_contents(HOMEPAGE_JSON), true) ?: [];
    return $data['images'] ?? [];
}

/** حفظ homepage/gallery.json */
function saveHomepageGallery(array $images): void {
    @mkdir(HOMEPAGE_DIR, 0755, true);
    file_put_contents(HOMEPAGE_JSON, json_encode(['images' => array_values($images)], JSON_UNESCAPED_UNICODE));
}

/** رفع ملف صورة مع التحقق */
function uploadFile(string $tmp, string $origName, string $targetDir): string|false {
    global $ALLOWED_EXT;
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT)) return false;
    if (filesize($tmp) > MAX_SIZE)     return false;
    @mkdir($targetDir, 0755, true);
    $newName = time() . '_' . mt_rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($tmp, $targetDir . $newName)) return $newName;
    return false;
}

// ── معالجة الإجراءات ──────────────────────────────────
$message = '';
$msgType = 'success';

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ─── رفع غلاف قسم ───────────────────────────────
    if ($action === 'upload_cover') {
        $section = $_POST['section'] ?? '';
        if (!array_key_exists($section, $SECTIONS)) {
            $message = 'قسم غير صالح.'; $msgType = 'error';
        } elseif (empty($_FILES['cover']['tmp_name'])) {
            $message = 'لم تختر صورة للغلاف.'; $msgType = 'error';
        } else {
            $newName = uploadFile(
                $_FILES['cover']['tmp_name'],
                $_FILES['cover']['name'],
                COVERS_DIR
            );
            if ($newName) {
                // حذف الغلاف القديم من مجلد covers/ إن وجد
                $covers = getCovers();
                $old = $covers[$section] ?? '';
                if (str_starts_with($old, 'covers/')) {
                    $oldPath = BASE_DIR . $old;
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $covers[$section] = 'covers/' . $newName;
                saveCovers($covers);
                $message = 'تم تحديث غلاف "' . $SECTIONS[$section] . '" بنجاح ✓';
            } else {
                $message = 'فشل رفع الصورة. تأكد من النوع والحجم.'; $msgType = 'error';
            }
        }
    }

    // ─── رفع صور معرض الرئيسية ──────────────────────
    if ($action === 'upload_homepage') {
        if (empty($_FILES['images']['name'][0])) {
            $message = 'لم تختر أي صور.'; $msgType = 'error';
        } else {
            $current = getHomepageImages();
            $uploaded = 0; $failed = 0;
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                $newName = uploadFile($tmp, $_FILES['images']['name'][$i], HOMEPAGE_DIR);
                if ($newName) { $current[] = 'homepage/' . $newName; $uploaded++; }
                else          { $failed++; }
            }
            saveHomepageGallery($current);
            $message = "تم رفع {$uploaded} صورة للمعرض الرئيسي" . ($failed ? " (فشل {$failed})" : '') . ' ✓';
            if ($uploaded === 0) $msgType = 'error';
        }
    }

    // ─── حذف صورة من معرض الرئيسية ─────────────────
    if ($action === 'delete_homepage') {
        $imgPath = trim($_POST['imgpath'] ?? '');
        if (empty($imgPath)) {
            $message = 'مسار غير صالح.'; $msgType = 'error';
        } else {
            // حذف الملف إن كان في مجلد homepage/
            if (str_starts_with($imgPath, 'homepage/')) {
                $fullPath = BASE_DIR . basename(substr($imgPath, 9));
                $fullPath = HOMEPAGE_DIR . basename(substr($imgPath, 9));
                if (file_exists($fullPath)) @unlink($fullPath);
            }
            // إزالة من الـ JSON
            $current = getHomepageImages();
            $current = array_values(array_filter($current, fn($v) => $v !== $imgPath));
            saveHomepageGallery($current);
            $message = 'تم حذف الصورة من المعرض الرئيسي ✓';
        }
    }

    // ─── رفع صور قسم (معرض الصفحة الداخلية) ────────
    if ($action === 'upload') {
        $section = $_POST['section'] ?? '';
        if (!array_key_exists($section, $SECTIONS)) {
            $message = 'قسم غير صالح.'; $msgType = 'error';
        } elseif (empty($_FILES['images']['name'][0])) {
            $message = 'لم تختر أي صور.'; $msgType = 'error';
        } else {
            $dir = BASE_DIR . $section . '/';
            $uploaded = 0; $failed = 0;
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $ALLOWED_EXT) || filesize($tmp) > MAX_SIZE) { $failed++; continue; }
                $newName = $section . '-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . $newName)) $uploaded++;
                else $failed++;
            }
            regenerateJSON($section);
            $message = "تم رفع {$uploaded} صورة في «{$SECTIONS[$section]}»" . ($failed ? " (فشل {$failed})" : '') . ' ✓';
            if ($uploaded === 0) $msgType = 'error';
        }
    }

    // ─── حذف صورة من قسم ────────────────────────────
    if ($action === 'delete') {
        $section  = $_POST['section']  ?? '';
        $filename = basename($_POST['filename'] ?? '');
        if (!array_key_exists($section, $SECTIONS) || empty($filename)) {
            $message = 'طلب حذف غير صالح.'; $msgType = 'error';
        } else {
            $path = BASE_DIR . $section . '/' . $filename;
            if (file_exists($path) && is_file($path)) {
                unlink($path);
                regenerateJSON($section);
                $message = "تم حذف «{$filename}» من «{$SECTIONS[$section]}» ✓";
            } else {
                $message = 'الصورة غير موجودة.'; $msgType = 'error';
            }
        }
    }
}

// ── بيانات العرض ──────────────────────────────────────
function getSectionImages(string $section): array {
    $files = glob(BASE_DIR . $section . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    $names = array_map('basename', $files);
    natsort($names);
    return array_values($names);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>لوحة التحكم — أريج للأثاث</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet" />
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --primary: #4A2C17; --primary-light: #7B4A2D;
    --accent: #C9924A; --accent-light: #E8B87A;
    --bg: #FAF6F1; --white: #FFFFFF;
    --gray-100: #F5EFE8; --gray-300: #D4C4B0;
    --gray-600: #6B5B4E; --gray-800: #2C1A0E;
    --green: #1a7a4a; --red: #c0392b; --blue: #1a4a7a;
}
body { font-family:'Tajawal',sans-serif; background:var(--bg); color:var(--gray-800); min-height:100vh; }

/* Header */
.adm-header { background:linear-gradient(135deg,var(--gray-800),var(--primary)); color:#fff; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 12px rgba(0,0,0,.3); }
.adm-logo { font-size:1.2rem; font-weight:900; display:flex; align-items:center; gap:10px; }
.adm-logo span { color:var(--accent-light); }
.adm-logout { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); color:#fff; padding:7px 18px; border-radius:8px; cursor:pointer; font-family:'Tajawal',sans-serif; font-size:.9rem; transition:.2s; }
.adm-logout:hover { background:rgba(255,255,255,.22); }

/* Login */
.login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
.login-card { background:var(--white); border-radius:20px; padding:40px; box-shadow:0 8px 40px rgba(74,44,23,.12); width:100%; max-width:400px; text-align:center; }
.login-icon { font-size:3rem; margin-bottom:16px; }
.login-card h1 { font-size:1.6rem; font-weight:900; color:var(--primary); margin-bottom:6px; }
.login-card p  { color:var(--gray-600); margin-bottom:28px; font-size:.95rem; }
.input-group { margin-bottom:16px; text-align:right; }
.input-group label { display:block; font-weight:700; color:var(--gray-800); margin-bottom:6px; font-size:.9rem; }
.input-group input { width:100%; padding:12px 16px; border:2px solid var(--gray-300); border-radius:10px; font-family:'Tajawal',sans-serif; font-size:1rem; background:var(--gray-100); transition:.2s; outline:none; }
.input-group input:focus { border-color:var(--accent); background:var(--white); }
.btn-login { width:100%; padding:13px; background:linear-gradient(135deg,var(--primary),var(--primary-light)); color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:700; font-family:'Tajawal',sans-serif; cursor:pointer; transition:.2s; }
.btn-login:hover { transform:translateY(-1px); box-shadow:0 4px 16px rgba(74,44,23,.3); }
.login-error { background:#ffeaea; color:var(--red); border:1px solid #f5c6c6; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:.9rem; }

/* Message */
.adm-msg { margin:20px 24px; padding:12px 18px; border-radius:10px; font-size:.95rem; font-weight:500; }
.adm-msg.success { background:#e8f5e9; color:var(--green); border:1px solid #c3e6cb; }
.adm-msg.error   { background:#ffeaea; color:var(--red);   border:1px solid #f5c6c6; }

/* Main */
.adm-main { padding:24px; max-width:1200px; margin:0 auto; }
.adm-welcome { margin-bottom:28px; }
.adm-welcome h2 { font-size:1.4rem; font-weight:900; color:var(--primary); }
.adm-welcome p  { color:var(--gray-600); font-size:.95rem; margin-top:4px; }

/* Section card */
.section-card { background:var(--white); border-radius:16px; margin-bottom:28px; box-shadow:0 2px 16px rgba(74,44,23,.08); overflow:hidden; }
.section-card-header { color:#fff; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; }
.section-card-header.type-cover    { background:linear-gradient(135deg,#1a3a5c,#2d5e8b); }
.section-card-header.type-homepage { background:linear-gradient(135deg,#1a5c2d,#2d8b4a); }
.section-card-header.type-gallery  { background:linear-gradient(135deg,var(--primary),var(--primary-light)); }
.section-card-header h3 { font-size:1.15rem; font-weight:700; display:flex; align-items:center; gap:8px; }
.section-count { background:rgba(255,255,255,.18); padding:3px 12px; border-radius:20px; font-size:.85rem; }
.section-card-body { padding:24px; }

/* Covers grid */
.covers-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }
.cover-card { border-radius:12px; overflow:hidden; border:2px solid var(--gray-300); transition:.2s; }
.cover-card:hover { border-color:var(--accent); }
.cover-img-wrap { aspect-ratio:4/3; overflow:hidden; background:var(--gray-100); position:relative; }
.cover-img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
.cover-img-placeholder { width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--gray-600); font-size:2rem; }
.cover-card-body { padding:10px 12px 12px; background:var(--white); }
.cover-card-label { font-weight:700; font-size:.9rem; color:var(--primary); margin-bottom:8px; }
.cover-upload-zone input[type=file] { display:none; }
.cover-upload-zone label { display:flex; align-items:center; justify-content:center; gap:6px; background:var(--accent); color:#fff; padding:7px 12px; border-radius:7px; cursor:pointer; font-size:.82rem; font-weight:700; transition:.2s; width:100%; }
.cover-upload-zone label:hover { background:var(--primary-light); }
.cover-file-hint { display:block; color:var(--gray-600); font-size:.75rem; margin-top:5px; text-align:center; }
.btn-cover-submit { width:100%; margin-top:7px; background:var(--primary); color:#fff; border:none; padding:7px; border-radius:7px; font-family:'Tajawal',sans-serif; font-size:.85rem; font-weight:700; cursor:pointer; transition:.2s; }
.btn-cover-submit:hover { background:var(--primary-light); }

/* Upload form */
.upload-form { margin-bottom:24px; }
.upload-zone { border:2px dashed var(--accent); border-radius:12px; padding:20px; text-align:center; background:rgba(201,146,74,.04); margin-bottom:12px; }
.upload-zone input[type=file] { display:none; }
.upload-zone label { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; padding:9px 20px; border-radius:8px; cursor:pointer; font-weight:700; font-size:.9rem; transition:.2s; }
.upload-zone label:hover { background:var(--primary-light); }
.upload-zone .file-hint    { display:block; color:var(--gray-600); font-size:.83rem; margin-top:8px; }
.upload-zone .file-selected { display:block; color:var(--primary); font-size:.88rem; margin-top:6px; font-weight:500; }
.btn-upload { background:var(--primary); color:#fff; border:none; padding:9px 24px; border-radius:8px; font-family:'Tajawal',sans-serif; font-size:.95rem; font-weight:700; cursor:pointer; transition:.2s; }
.btn-upload:hover { background:var(--primary-light); transform:translateY(-1px); }

/* Images grid */
.images-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; }
.img-card { position:relative; border-radius:10px; overflow:hidden; aspect-ratio:1; }
.img-card img { width:100%; height:100%; object-fit:cover; display:block; }
.img-card-overlay { position:absolute; inset:0; background:rgba(44,26,14,.6); opacity:0; transition:.2s; display:flex; align-items:center; justify-content:center; }
.img-card:hover .img-card-overlay { opacity:1; }
.img-name { position:absolute; bottom:0; right:0; left:0; background:rgba(0,0,0,.55); color:#fff; font-size:.72rem; padding:4px 8px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; }
.btn-delete { background:#fff; color:var(--red); border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,.2); transition:.2s; }
.btn-delete:hover { background:var(--red); color:#fff; transform:scale(1.1); }
.empty-state { text-align:center; color:var(--gray-600); padding:32px; font-size:.95rem; }
.img-source-badge { position:absolute; top:5px; right:5px; background:rgba(0,0,0,.6); color:#fff; font-size:.65rem; padding:2px 6px; border-radius:4px; }

@media (max-width:600px) {
    .adm-main { padding:16px; }
    .images-grid { grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:10px; }
    .covers-grid { grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
    .login-card { padding:28px 20px; }
}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ══════════════════ صفحة تسجيل الدخول ══════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-icon">🔒</div>
    <h1>لوحة التحكم</h1>
    <p>أريج للأثاث المنزلي — دخول المشرف</p>
    <?php if (!empty($loginError)): ?>
      <div class="login-error"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="input-group">
        <label for="password">كلمة المرور</label>
        <input type="password" id="password" name="password" placeholder="أدخل كلمة المرور" required autofocus />
      </div>
      <button type="submit" class="btn-login">دخول ←</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════ لوحة التحكم ══════════════════ -->
<header class="adm-header">
  <div class="adm-logo"><span>🏠</span> أريج — <span>لوحة التحكم</span></div>
  <form method="POST" style="display:inline">
    <button type="submit" name="logout" value="1" class="adm-logout">تسجيل الخروج</button>
  </form>
</header>

<?php if (!empty($message)): ?>
  <div class="adm-msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<main class="adm-main">
  <div class="adm-welcome">
    <h2>مرحباً بك في لوحة التحكم 👋</h2>
    <p>تحكم كامل في صور الموقع — الأغلفة، معرض الرئيسية، وصور كل قسم.</p>
  </div>

  <!-- ════════════════════════════════════════════════
       القسم 1 — صور الغلاف (الكاتالوج + من نحن)
  ════════════════════════════════════════════════ -->
  <?php $covers = getCovers(); ?>
  <div class="section-card">
    <div class="section-card-header type-cover">
      <h3>🖼️ صور الغلاف</h3>
      <span class="section-count">تُستخدم في الكاتالوج وقسم "من نحن"</span>
    </div>
    <div class="section-card-body">
      <p style="color:var(--gray-600);font-size:.9rem;margin-bottom:16px;">
        اختر غلافاً مميزاً لكل قسم — سيظهر في بطاقة القسم على الصفحة الرئيسية.
      </p>
      <div class="covers-grid">
        <?php foreach ($SECTIONS as $key => $label):
          $coverPath = $covers[$key] ?? '';
          $coverSrc  = $coverPath ? 'assets/' . $coverPath : '';
        ?>
        <div class="cover-card">
          <div class="cover-img-wrap">
            <?php if ($coverSrc): ?>
              <img src="<?= htmlspecialchars($coverSrc) ?>" alt="غلاف <?= htmlspecialchars($label) ?>" />
            <?php else: ?>
              <div class="cover-img-placeholder">📷</div>
            <?php endif; ?>
          </div>
          <div class="cover-card-body">
            <div class="cover-card-label"><?= htmlspecialchars($label) ?></div>
            <form method="POST" enctype="multipart/form-data" class="cover-upload-zone"
                  onsubmit="return validateSingle(this)">
              <input type="hidden" name="action"  value="upload_cover" />
              <input type="hidden" name="section" value="<?= $key ?>" />
              <input type="file" id="cov-<?= $key ?>" name="cover"
                     accept="image/jpeg,image/png,image/webp"
                     onchange="updateSingleLabel(this,'<?= $key ?>')" />
              <label for="cov-<?= $key ?>">📸 اختر صورة</label>
              <span class="cover-file-hint" id="cov-lbl-<?= $key ?>">JPG · PNG · WEBP</span>
              <button type="submit" class="btn-cover-submit">⬆️ رفع الغلاف</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       القسم 2 — معرض الصفحة الرئيسية
  ════════════════════════════════════════════════ -->
  <?php $homepageImgs = getHomepageImages(); $hpCount = count($homepageImgs); ?>
  <div class="section-card">
    <div class="section-card-header type-homepage">
      <h3>🏠 معرض الصفحة الرئيسية</h3>
      <span class="section-count"><?= $hpCount ?> صورة</span>
    </div>
    <div class="section-card-body">
      <p style="color:var(--gray-600);font-size:.9rem;margin-bottom:16px;">
        هذه الصور تظهر في قسم "لمسة من مجموعاتنا" في الصفحة الرئيسية. يمكنك رفع أي صور تريد عرضها هناك.
      </p>

      <!-- رفع صور جديدة -->
      <form class="upload-form" method="POST" enctype="multipart/form-data"
            onsubmit="return validateUpload(this)">
        <input type="hidden" name="action" value="upload_homepage" />
        <div class="upload-zone">
          <input type="file" id="hp-files" name="images[]"
                 accept="image/jpeg,image/png,image/webp" multiple
                 onchange="updateLabel(this,'hp')" />
          <label for="hp-files">📸 اختر صور للمعرض الرئيسي</label>
          <span class="file-hint">JPG · PNG · WEBP — بحد أقصى 10 ميجا للصورة</span>
          <span class="file-selected" id="lbl-hp">لم تختر أي ملف</span>
        </div>
        <button type="submit" class="btn-upload">⬆️ رفع الصور</button>
      </form>

      <!-- عرض الصور الحالية -->
      <?php if ($hpCount > 0): ?>
      <div class="images-grid">
        <?php foreach ($homepageImgs as $imgPath):
          $inHomepage = str_starts_with($imgPath, 'homepage/');
          $fname = basename($imgPath);
        ?>
        <div class="img-card">
          <img src="assets/<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($fname) ?>" loading="lazy" />
          <div class="img-card-overlay">
            <form method="POST" onsubmit="return confirm('حذف هذه الصورة من المعرض الرئيسي؟')">
              <input type="hidden" name="action"  value="delete_homepage" />
              <input type="hidden" name="imgpath" value="<?= htmlspecialchars($imgPath) ?>" />
              <button type="submit" class="btn-delete" title="حذف">🗑️</button>
            </form>
          </div>
          <?php if (!$inHomepage): ?>
            <span class="img-source-badge">مرجع</span>
          <?php endif; ?>
          <div class="img-name"><?= htmlspecialchars($fname) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">لا توجد صور في المعرض الرئيسي. ارفع أول صورة! 📷</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       الأقسام 3–7 — معارض صور الأقسام
  ════════════════════════════════════════════════ -->
  <?php foreach ($SECTIONS as $key => $label):
    $images = getSectionImages($key);
    $count  = count($images);
  ?>
  <div class="section-card">
    <div class="section-card-header type-gallery">
      <h3>📂 <?= htmlspecialchars($label) ?></h3>
      <span class="section-count"><?= $count ?> صورة</span>
    </div>
    <div class="section-card-body">

      <form class="upload-form" method="POST" enctype="multipart/form-data"
            onsubmit="return validateUpload(this)">
        <input type="hidden" name="action"  value="upload" />
        <input type="hidden" name="section" value="<?= $key ?>" />
        <div class="upload-zone">
          <input type="file" id="file-<?= $key ?>" name="images[]"
                 accept="image/jpeg,image/png,image/webp" multiple
                 onchange="updateLabel(this,'<?= $key ?>')" />
          <label for="file-<?= $key ?>">📸 اختر صور للرفع</label>
          <span class="file-hint">JPG · PNG · WEBP — بحد أقصى 10 ميجا للصورة</span>
          <span class="file-selected" id="lbl-<?= $key ?>">لم تختر أي ملف</span>
        </div>
        <button type="submit" class="btn-upload">⬆️ رفع الصور</button>
      </form>

      <?php if ($count > 0): ?>
      <div class="images-grid">
        <?php foreach ($images as $img): ?>
        <div class="img-card">
          <img src="assets/<?= $key ?>/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($img) ?>" loading="lazy" />
          <div class="img-card-overlay">
            <form method="POST" onsubmit="return confirm('حذف «<?= htmlspecialchars($img) ?>»؟')">
              <input type="hidden" name="action"   value="delete" />
              <input type="hidden" name="section"  value="<?= $key ?>" />
              <input type="hidden" name="filename" value="<?= htmlspecialchars($img) ?>" />
              <button type="submit" class="btn-delete" title="حذف">🗑️</button>
            </form>
          </div>
          <div class="img-name"><?= htmlspecialchars($img) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">لا توجد صور بعد. ارفع أول صورة! 📷</div>
      <?php endif; ?>

    </div>
  </div>
  <?php endforeach; ?>

</main>

<footer style="text-align:center;padding:24px;color:var(--gray-600);font-size:.85rem;border-top:1px solid var(--gray-300);margin-top:16px;">
  لوحة تحكم أريج للأثاث المنزلي — جميع الحقوق محفوظة
</footer>

<script>
function updateLabel(input, key) {
  var lbl = document.getElementById('lbl-' + key);
  if (!lbl) return;
  if      (!input.files.length)  lbl.textContent = 'لم تختر أي ملف';
  else if (input.files.length===1) lbl.textContent = 'تم اختيار: ' + input.files[0].name;
  else     lbl.textContent = 'تم اختيار ' + input.files.length + ' صور';
}
function updateSingleLabel(input, key) {
  var lbl = document.getElementById('cov-lbl-' + key);
  if (lbl && input.files.length) lbl.textContent = '✓ ' + input.files[0].name;
}
function validateUpload(form) {
  if (!form.querySelector('input[type=file]').files.length) {
    alert('يرجى اختيار صورة أو أكثر أولاً.'); return false;
  }
  return true;
}
function validateSingle(form) {
  if (!form.querySelector('input[type=file]').files.length) {
    alert('يرجى اختيار صورة للغلاف أولاً.'); return false;
  }
  return true;
}
</script>

<?php endif; ?>
</body>
</html>
