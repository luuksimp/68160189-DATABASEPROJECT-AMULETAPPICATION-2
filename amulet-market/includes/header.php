<?php
require_once __DIR__ . '/functions.php';
$me = current_user();
$pageTitle = $pageTitle ?? SITE_NAME;
$showHero = $showHero ?? false;
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isSeller = $me && in_array($me['Role'], ['Seller', 'Admin'], true);

$links = [['index.php', 'ตลาดพระ'], ['feed.php', 'ฟีด']];
if ($me) {
    if ($isSeller) { $links[] = ['dashboard.php', 'แดชบอร์ดผู้ขาย']; $links[] = ['amulet_add.php', 'ลงขายพระ']; }
    $links[] = ['orders.php', 'คำสั่งซื้อ'];
    $links[] = ['profile.php', 'โปรไฟล์'];
} else {
    $links[] = ['login.php', 'เข้าสู่ระบบ'];
    $links[] = ['register.php', 'สมัครสมาชิก'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&family=Pridi:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="index.php"><i aria-hidden="true">๛</i><?= e(SITE_NAME) ?></a>
    <nav class="nav" aria-label="เมนูหลัก">
      <?php foreach ($links as [$href, $label]): ?>
        <a href="<?= e($href) ?>"<?= $cur === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($me): ?><span class="who"><?= e($me['Username']) ?></span><a href="logout.php">ออก</a><?php endif; ?>
    </nav>
  </div>
</header>
<main>
<?php if (!empty($_SESSION['flash'])): ?><div class="wrap">
<?php foreach ($_SESSION['flash'] as [$msg, $type]): ?>
  <div class="flash <?= $type === 'err' ? 'err' : 'ok' ?>" role="status"><?= e($msg) ?></div>
<?php endforeach; unset($_SESSION['flash']); ?></div><?php endif; ?>
<?php if (!$showHero): ?><div class="wrap page"><?php endif; ?>
