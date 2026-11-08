<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('index.php');

$errors = [];
$old = ['username' => '', 'email' => '', 'phone' => '', 'full_name' => '', 'role' => 'Buyer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = [
        'username'  => trim((string)($_POST['username'] ?? '')),
        'email'     => mb_substr(trim((string)($_POST['email'] ?? '')), 0, 120),
        'phone'     => trim((string)($_POST['phone'] ?? '')),
        'full_name' => mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 120),
        'role'      => ($_POST['role'] ?? '') === 'Seller' ? 'Seller' : 'Buyer',
    ];
    $pass = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) $errors[] = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ ความยาว 3-30 ตัว';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
    if ($old['full_name'] === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $old['phone'])) $errors[] = 'เบอร์โทรศัพท์ไม่ถูกต้อง';
    if (strlen($pass) < 8) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO users (Username, Password_Hash, Email, Phone, Role, Full_Name) VALUES (?,?,?,?,?,?)')
                ->execute([$old['username'], password_hash($pass, PASSWORD_DEFAULT), $old['email'], $old['phone'] ?: null, $old['role'], $old['full_name']]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            flash('สมัครสมาชิกสำเร็จ ยินดีต้อนรับ');
            redirect('index.php');
        } catch (PDOException $ex) {
            // UNIQUE ชน (สมัครซ้ำ/กดซ้ำพร้อมกัน) -> แจ้งผู้ใช้แทนหน้า error
            if ((string)$ex->getCode() !== '23000') throw $ex;
            $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
        }
    }
}

$pageTitle = 'สมัครสมาชิก';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
  <h1>สมัครสมาชิก</h1>
  <div class="notice">ระบบไม่ขอสำเนาบัตรประชาชน สมุดบัญชี หรือเลขบัญชีธนาคาร การยืนยันตัวตนผู้ขายดำเนินการโดยผู้ดูแลระบบ</div>
  <?php foreach ($errors as $er): ?><div class="flash err" role="alert"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="username">ชื่อผู้ใช้</label><input id="username" type="text" name="username" maxlength="30" value="<?= e($old['username']) ?>" autocomplete="username" required>
    <label for="full_name">ชื่อ-นามสกุล</label><input id="full_name" type="text" name="full_name" maxlength="120" value="<?= e($old['full_name']) ?>" autocomplete="name" required>
    <label for="email">อีเมล</label><input id="email" type="email" name="email" maxlength="120" value="<?= e($old['email']) ?>" autocomplete="email" required>
    <label for="phone">เบอร์โทร</label><input id="phone" type="tel" name="phone" maxlength="20" value="<?= e($old['phone']) ?>" autocomplete="tel">
    <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัว)</label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
    <label for="role">ประเภทบัญชี</label>
    <select id="role" name="role">
      <option value="Buyer" <?= $old['role'] === 'Buyer' ? 'selected' : '' ?>>ผู้ซื้อ / ผู้เช่าบูชา</option>
      <option value="Seller" <?= $old['role'] === 'Seller' ? 'selected' : '' ?>>ผู้ขาย</option>
    </select>
    <p><button>สมัครสมาชิก</button> <a href="login.php">มีบัญชีแล้ว?</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
