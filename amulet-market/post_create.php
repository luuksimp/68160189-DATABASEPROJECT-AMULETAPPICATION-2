<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login();

$errors = [];
// พระของผู้ใช้ที่ลงขายไว้ (ให้แนบกับโพสต์ได้)
$st = db()->prepare('SELECT Amulet_ID, Name FROM amulets WHERE Seller_ID = ? ORDER BY Created_At DESC');
$st->execute([$me['User_ID']]);
$myAmulets = $st->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $type = in_array($_POST['post_type'] ?? '', ['ลงขาย', 'โชว์พระ', 'สอบถามข้อมูล'], true) ? $_POST['post_type'] : 'โชว์พระ';
    $content = mb_substr(trim((string)($_POST['content'] ?? '')), 0, 2000);
    $amuletId = (int)($_POST['amulet_id'] ?? 0) ?: null;

    if ($amuletId) {
        $chk = db()->prepare('SELECT 1 FROM amulets WHERE Amulet_ID = ? AND Seller_ID = ?');
        $chk->execute([$amuletId, $me['User_ID']]);
        if (!$chk->fetch()) $amuletId = null;
    }
    $img = null;
    try { $img = save_image($_FILES['image'] ?? []); } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
    if ($content === '' && !$img && !$amuletId) $errors[] = 'กรุณากรอกข้อความ แนบรูป หรือเลือกพระเครื่อง';

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO posts (User_ID, Amulet_ID, Post_Type, Content, Image_URL) VALUES (?,?,?,?,?)')
                ->execute([$me['User_ID'], $amuletId, $type, $content, $img]);
        } catch (Throwable $ex) {
            delete_files([$img]); // กันรูปค้างเมื่อบันทึกโพสต์ไม่สำเร็จ
            throw $ex;
        }
        flash('โพสต์เรียบร้อย');
        redirect('feed.php');
    } else {
        delete_files([$img]);
    }
}

$pageTitle = 'สร้างโพสต์';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow-lg">
  <h1>สร้างโพสต์</h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="post_type">ประเภทโพสต์</label>
    <select id="post_type" name="post_type">
      <option>โชว์พระ</option><option>สอบถามข้อมูล</option><option>ลงขาย</option>
    </select>
    <label for="content">ข้อความ</label><textarea id="content" name="content" maxlength="2000" placeholder="เล่าเรื่องพระองค์นี้ หรือสอบถามเพื่อน ๆ ในกลุ่ม"></textarea>
    <label for="image">รูปภาพ (JPG/PNG/WEBP ไม่เกิน 3MB)</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
    <?php if ($myAmulets): ?>
      <label for="amulet_id">แนบพระเครื่องที่ลงขายไว้ (ถ้ามี)</label>
      <select id="amulet_id" name="amulet_id">
        <option value="">— ไม่แนบ —</option>
        <?php foreach ($myAmulets as $a): ?><option value="<?= (int)$a['Amulet_ID'] ?>"><?= e($a['Name']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <p><button>โพสต์</button></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
