<?php
// หน้ายืนยันคำสั่งซื้อ: กรอกชื่อ-เบอร์-ที่อยู่จัดส่ง (ดึงจากโปรไฟล์มาให้อัตโนมัติ)
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$aid = (int)($_GET['amulet_id'] ?? 0);

$st = db()->prepare('SELECT a.*, u.Username FROM amulets a JOIN users u ON u.User_ID = a.Seller_ID WHERE a.Amulet_ID = ?');
$st->execute([$aid]);
$a = $st->fetch();
if (!$a || $a['Stock_Status'] === 'ซ่อนประกาศ') render_error_page(404, 'ไม่พบพระเครื่องรายการนี้');
if ((int)$a['Seller_ID'] === (int)$me['User_ID']) { flash('ไม่สามารถเช่าพระของตัวเองได้', 'err'); redirect('amulet.php?id=' . $aid); }
if ($a['Stock_Status'] !== 'พร้อมเช่า') { flash('พระองค์นี้ไม่พร้อมให้เช่าแล้ว', 'err'); redirect('amulet.php?id=' . $aid); }

$pageTitle = 'ยืนยันการเช่าบูชา';
require __DIR__ . '/includes/header.php';
$v = fn($post, $fallback) => e($_SESSION['checkout_old'][$post] ?? $fallback);
?>
<div class="narrow-lg">
  <h1>ยืนยันการเช่าบูชา</h1>
  <div class="notice">ระบบนี้ยังไม่มีการรับชำระเงินจริง — ห้ามโอนเงินหรือส่งสลิปจริงผ่านเว็บ การชำระเงินเป็นขั้นตอนจำลองผ่านระบบตัวกลาง คำสั่งซื้อที่ไม่ชำระภายใน <?= (int)ORDER_HOLD_HOURS ?> ชั่วโมงจะถูกยกเลิกอัตโนมัติ</div>
  <p class="panel"><strong><?= e($a['Name']) ?></strong> · <span class="price"><?= baht($a['Price']) ?></span> · ผู้ขาย @<?= e($a['Username']) ?></p>
  <form class="panel" method="post" action="order.php">
    <?= csrf_field() ?><input type="hidden" name="amulet_id" value="<?= $aid ?>">
    <label for="ship_name">ชื่อผู้รับ *</label><input id="ship_name" type="text" name="ship_name" maxlength="120" required autocomplete="name" value="<?= $v('ship_name', $me['Full_Name']) ?>">
    <label for="ship_phone">เบอร์โทรศัพท์ *</label><input id="ship_phone" type="tel" name="ship_phone" maxlength="20" required inputmode="tel" pattern="[0-9+\-\s]{8,20}" value="<?= $v('ship_phone', $me['Phone'] ?? '') ?>">
    <label for="ship_address">ที่อยู่จัดส่ง *</label><textarea id="ship_address" name="ship_address" rows="3" maxlength="500" required><?= $v('ship_address', $me['Address'] ?? '') ?></textarea>
    <p class="muted">ข้อมูลนี้ผู้ขายจะเห็นเฉพาะคำสั่งซื้อนี้เพื่อใช้จัดส่งเท่านั้น</p>
    <p><button onclick="return confirm('ยืนยันการเช่าบูชาพระองค์นี้?')">ยืนยันคำสั่งซื้อ</button> <a class="btn ghost" href="amulet.php?id=<?= $aid ?>">ยกเลิก</a></p>
  </form>
</div>
<?php unset($_SESSION['checkout_old']); require __DIR__ . '/includes/footer.php'; ?>
