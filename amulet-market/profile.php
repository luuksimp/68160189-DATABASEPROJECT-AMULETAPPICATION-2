<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$uid = (int)$me['User_ID'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update') {
        $name = mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 120);
        $phone = trim((string)($_POST['phone'] ?? ''));
        $addr = mb_substr(trim((string)($_POST['address'] ?? '')), 0, 500);
        if ($name === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) $errors[] = 'เบอร์โทรศัพท์ไม่ถูกต้อง';
        if (!$errors) {
            db()->prepare('UPDATE users SET Full_Name = ?, Phone = ?, Address = ? WHERE User_ID = ?')->execute([$name, $phone ?: null, $addr ?: null, $uid]);
            flash('บันทึกข้อมูลโปรไฟล์แล้ว');
            redirect('profile.php');
        }
    } elseif ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $me['Password_Hash'])) $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        elseif (strlen($new) < 8) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
        else {
            db()->prepare('UPDATE users SET Password_Hash = ? WHERE User_ID = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('เปลี่ยนรหัสผ่านแล้ว');
            redirect('profile.php');
        }
    } elseif ($action === 'become_seller' && $me['Role'] === 'Buyer') {
        db()->prepare("UPDATE users SET Role = 'Seller' WHERE User_ID = ? AND Role = 'Buyer'")->execute([$uid]);
        flash('เปลี่ยนเป็นบัญชีผู้ขายแล้ว');
        redirect('dashboard.php');
    }
}

$roleLabel = ['Buyer' => 'ผู้ซื้อ', 'Seller' => 'ผู้ขาย', 'Admin' => 'ผู้ดูแลระบบ'][$me['Role']] ?? $me['Role'];
$pageTitle = 'โปรไฟล์';
require __DIR__ . '/includes/header.php';
?>
<div class="narrow-lg">
  <h1>โปรไฟล์</h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <div class="panel" style="margin-top:18px">
    <p style="margin:0"><strong>@<?= e($me['Username']) ?></strong> · <?= e($me['Email']) ?>
      <span class="badge"><?= e($roleLabel) ?></span>
      <span class="badge <?= $me['Verification_Status'] === 'ยืนยันแล้ว' ? 'green' : 'gray' ?>"><?= e($me['Verification_Status']) ?></span></p>
    <?php if ($me['Role'] !== 'Buyer'): ?><p class="muted" style="margin:6px 0 0">คะแนนผู้ขาย <?= number_format((float)$me['Rating_Score'], 2) ?>/5</p><?php endif; ?>
    <p class="muted" style="margin:6px 0 0">การยืนยันตัวตนผู้ขายทำโดยผู้ดูแลระบบ ระบบนี้ไม่รับสำเนาบัตรประชาชน สมุดบัญชี หรือเลขบัญชีธนาคาร</p>
  </div>

  <form class="panel" method="post"><h2>ข้อมูลติดต่อและที่อยู่จัดส่ง</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="update">
    <label for="full_name">ชื่อ-นามสกุล *</label><input id="full_name" type="text" name="full_name" maxlength="120" required value="<?= e($_POST['full_name'] ?? $me['Full_Name']) ?>">
    <label for="phone">เบอร์โทรศัพท์</label><input id="phone" type="tel" name="phone" maxlength="20" value="<?= e($_POST['phone'] ?? $me['Phone'] ?? '') ?>">
    <label for="address">ที่อยู่จัดส่ง</label><textarea id="address" name="address" maxlength="500"><?= e($_POST['address'] ?? $me['Address'] ?? '') ?></textarea>
    <p><button>บันทึก</button></p>
  </form>

  <form class="panel" method="post"><h2>เปลี่ยนรหัสผ่าน</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label for="current">รหัสผ่านปัจจุบัน</label><input id="current" type="password" name="current" autocomplete="current-password" required>
    <label for="new">รหัสผ่านใหม่ (อย่างน้อย 8 ตัว)</label><input id="new" type="password" name="new" autocomplete="new-password" minlength="8" required>
    <p><button>เปลี่ยนรหัสผ่าน</button></p>
  </form>

  <?php if ($me['Role'] === 'Buyer'): ?>
  <form class="panel" method="post" onsubmit="return confirm('เปลี่ยนเป็นบัญชีผู้ขาย?')"><h2>อยากลงขายพระ?</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="become_seller">
    <p class="muted">เปลี่ยนเป็นบัญชีผู้ขายเพื่อใช้แดชบอร์ดและลงขายพระเครื่อง (ยังใช้เช่าบูชาได้ตามปกติ)</p>
    <button class="ghost">เปลี่ยนเป็นบัญชีผู้ขาย</button>
  </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
