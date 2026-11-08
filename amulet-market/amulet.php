<?php
require_once __DIR__ . '/includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
$viewer = current_user();

$st = db()->prepare(
    'SELECT a.*, c.Category_Name, u.User_ID AS SellerUID, u.Username, u.Full_Name, u.Verification_Status, u.Rating_Score
     FROM amulets a JOIN categories c ON c.Category_ID = a.Category_ID JOIN users u ON u.User_ID = a.Seller_ID
     WHERE a.Amulet_ID = ?'
);
$st->execute([$id]);
$a = $st->fetch();
$isOwner = $viewer && $a && (int)$viewer['User_ID'] === (int)$a['SellerUID'];
if (!$a || ($a['Stock_Status'] === 'ซ่อนประกาศ' && !$isOwner)) render_error_page(404, 'ไม่พบพระเครื่องรายการนี้');

$imgs = db()->prepare('SELECT * FROM amulet_images WHERE Amulet_ID = ? ORDER BY Is_Primary DESC, Image_ID');
$imgs->execute([$id]);
$images = $imgs->fetchAll();
$isFav = $viewer ? isset(my_favorites()[$id]) : false;
$angles = ['ด้านหน้า', 'ด้านหลัง', 'ด้านข้าง/ขอบ', 'โค้ด/ตำหนิ'];

$pageTitle = $a['Name'];
require __DIR__ . '/includes/header.php';
?>
<a class="btn ghost small" href="index.php">← กลับไปตลาด</a>
<div class="detail" style="margin-top:18px">
  <div>
    <?php if ($images): ?>
      <img id="main-img" class="main-img" src="<?= e($images[0]['Image_URL']) ?>" alt="<?= e($a['Name']) ?>">
      <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach ($images as $i => $im): ?>
            <button type="button" class="ghost small" data-thumb data-src="<?= e($im['Image_URL']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($angles[$i] ?? 'รูปที่ ' . ($i + 1)) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?><div class="main-img noimg">ไม่มีรูป</div><?php endif; ?>
  </div>

  <div>
    <p class="eyebrow">รายละเอียดพระเครื่อง</p>
    <h1><?= e($a['Name']) ?></h1>
    <p class="price" style="font-size:1.6rem"><?= baht($a['Price']) ?>
      <span class="badge <?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'green' : 'gray' ?>"><?= e($a['Stock_Status']) ?></span></p>

    <div class="panel" style="margin-top:18px">
      <h2>ข้อมูลรายการ</h2>
      <dl class="fields">
        <dt>หมวดหมู่</dt><dd><?= e($a['Category_Name']) ?></dd>
        <dt>พระเกจิ/ผู้สร้าง</dt><dd><?= e($a['Monk_Creator'] ?: '-') ?></dd>
        <dt>วัด</dt><dd><?= e($a['Temple'] ?: '-') ?></dd>
        <dt>ปีที่สร้าง (พ.ศ.)</dt><dd><?= $a['Year_Created'] ? (int)$a['Year_Created'] : '-' ?></dd>
        <dt>เนื้อพระ</dt><dd><?= e($a['Material'] ?: '-') ?></dd>
        <dt>สภาพ</dt><dd><?= e($a['Condition_Desc'] ?: '-') ?></dd>
        <dt>ใบรับรอง</dt><dd><?= e($a['Certificate_Info'] ?: 'ไม่มี') ?></dd>
        <dt>รับประกัน</dt><dd><?= e($a['Guarantee_Policy'] ?: '-') ?></dd>
      </dl>
      <?php if ($a['Description']): ?><h3 style="margin-top:16px">ตำหนิ ประวัติ และรายละเอียดเพิ่มเติม</h3><p><?= nl2br(e($a['Description'])) ?></p><?php endif; ?>
    </div>

    <div class="panel">
      <h2>ข้อมูลผู้ขาย</h2>
      <p style="margin:0"><strong><?= e($a['Full_Name']) ?></strong> @<?= e($a['Username']) ?>
        <?php if ($a['Verification_Status'] === 'ยืนยันแล้ว'): ?><span class="badge green">ยืนยันตัวตนแล้ว</span><?php endif; ?></p>
      <p class="muted" style="margin:4px 0 0">คะแนน <?= number_format((float)$a['Rating_Score'], 2) ?>/5 — ติดต่อสอบถามผ่านโพสต์ในฟีดได้</p>
    </div>

    <div class="actions">
      <?php if ($a['Stock_Status'] === 'พร้อมเช่า' && !$isOwner): ?>
        <?php if ($viewer): ?><a class="btn" href="checkout.php?amulet_id=<?= $id ?>">เช่าบูชาเลย</a>
        <?php else: ?><a class="btn" href="login.php">เข้าสู่ระบบเพื่อเช่าบูชา</a><?php endif; ?>
      <?php endif; ?>
      <?php if ($viewer): ?>
        <form method="post" action="favorite.php"><?= csrf_field() ?>
          <input type="hidden" name="amulet_id" value="<?= $id ?>"><input type="hidden" name="back" value="amulet.php?id=<?= $id ?>">
          <button class="ghost" aria-pressed="<?= $isFav ? 'true' : 'false' ?>"><?= $isFav ? '♥ บันทึกแล้ว' : '♡ บันทึกรายการนี้' ?></button>
        </form>
      <?php endif; ?>
      <a class="btn ghost" href="feed.php">ถามในฟีด</a>
    </div>
    <?php if ($isOwner): ?><p class="muted">นี่คือพระของคุณ — จัดการได้ที่ <a href="dashboard.php">แดชบอร์ดผู้ขาย</a></p><?php endif; ?>
  </div>
</div>
<script>
document.querySelectorAll('[data-thumb]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('main-img').src = b.dataset.src;
    document.querySelectorAll('[data-thumb]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
  });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
