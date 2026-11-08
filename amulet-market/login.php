<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = mb_substr(trim((string)($_POST['username'] ?? '')), 0, 120);
    if (login_blocked($id)) {
        $error = 'พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอประมาณ 15 นาทีแล้วลองใหม่';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE Username = ? OR Email = ?');
        $st->execute([$id, $id]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['Password_Hash'])) {
            login_clear($id);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['User_ID'];
            redirect('index.php');
        }
        login_failed($id);
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}

$pageTitle = 'เข้าสู่ระบบ';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
  <h1>เข้าสู่ระบบ</h1>
  <?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="username">ชื่อผู้ใช้ หรือ อีเมล</label><input id="username" type="text" name="username" autocomplete="username" required>
    <label for="password">รหัสผ่าน</label><input id="password" type="password" name="password" autocomplete="current-password" required>
    <p><button>เข้าสู่ระบบ</button> <a href="register.php">สมัครสมาชิก</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
