<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_seller();

$cats = db()->query('SELECT * FROM categories ORDER BY Category_ID')->fetchAll();
$slots = ['front' => 'รูปด้านหน้า (รูปหลัก) *', 'back' => 'รูปด้านหลัง', 'edge' => 'รูปด้านข้าง / ขอบ', 'mark' => 'รูปโค้ด / ตำหนิ'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = fn($k, $max = 150) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $name = $f('name', 200);
    $catId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $year = (int)($_POST['year'] ?? 0) ?: null;

    if ($name === '') $errors[] = 'กรุณากรอกชื่อพระเครื่อง';
    if ($price <= 0 || $price > 99999999) $errors[] = 'ราคาต้องมากกว่า 0 และไม่เกิน 99,999,999 บาท';
    if ($year !== null && ($year < 1 || $year > 2700)) $errors[] = 'ปี พ.ศ. ไม่ถูกต้อง';
    $chk = db()->prepare('SELECT 1 FROM categories WHERE Category_ID = ?'); $chk->execute([$catId]);
    if (!$chk->fetch()) $errors[] = 'กรุณาเลือกหมวดหมู่';
    if (($_FILES['img_front']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) $errors[] = 'กรุณาแนบรูปด้านหน้าของพระเครื่อง';

    $saved = [];
    if (!$errors) {
        foreach ($slots as $key => $label) {
            try {
                $path = save_image($_FILES['img_' . $key] ?? []);
                if ($path) $saved[] = $path;
            } catch (RuntimeException $ex) { $errors[] = trim(str_replace('*', '', $label)) . ': ' . $ex->getMessage(); }
        }
        if ($errors) { delete_files($saved); $saved = []; }
    }

    if (!$errors) {
        try {
            $aid = transact(function (PDO $pdo) use ($me, $name, $catId, $price, $year, $f, $saved) {
                $pdo->prepare(
                    'INSERT INTO amulets (Seller_ID, Name, Category_ID, Monk_Creator, Temple, Year_Created, Material, Condition_Desc, Price, Certificate_Info, Guarantee_Policy, Description)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$me['User_ID'], $name, $catId, $f('monk'), $f('temple'), $year, $f('material', 100), $f('condition', 100), $price, $f('certificate', 200), $f('guarantee', 255), $f('description', 5000)]);
                $aid = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare('INSERT INTO amulet_images (Amulet_ID, Image_URL, Is_Primary) VALUES (?,?,?)');
                foreach ($saved as $i => $path) $ins->execute([$aid, $path, $i === 0 ? 1 : 0]);
                // สร้างโพสต์ประเภท "ลงขาย" ขึ้นฟีดอัตโนมัติ
                $pdo->prepare('INSERT INTO posts (User_ID, Amulet_ID, Post_Type, Content) VALUES (?,?,?,?)')
                    ->execute([$me['User_ID'], $aid, 'ลงขาย', 'ลงขายพระใหม่: ' . $name]);
                return $aid;
            });
            flash('ลงขายพระเรียบร้อย');
            redirect('amulet.php?id=' . $aid);
        } catch (Throwable $ex) {
            delete_files($saved); // บันทึกไม่สำเร็จ ลบรูปที่อัปโหลดไปแล้ว ไม่ให้เหลือเป็นขยะ
            error_log('[amulet-market] amulet_add: ' . $ex->getMessage());
            $errors[] = 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
        }
    }
}

$pageTitle = 'ลงขายพระเครื่อง';
require __DIR__ . '/includes/header.php';
?>
<div class="narrow-lg">
  <p class="eyebrow">SELLER SPACE</p>
  <h1>ลงขายพระเครื่อง</h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <form class="panel" style="margin-top:18px" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>ข้อมูลพระเครื่อง</h2>
    <label for="name">ชื่อพระเครื่อง *</label><input id="name" type="text" name="name" maxlength="200" value="<?= old('name') ?>" placeholder="เช่น พระสมเด็จวัดระฆัง พิมพ์ใหญ่" required>
    <div class="row">
      <div><label for="category_id">หมวดหมู่ *</label>
        <select id="category_id" name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['Category_ID'] ?>" <?= (int)($_POST['category_id'] ?? 0) === (int)$c['Category_ID'] ? 'selected' : '' ?>><?= e($c['Category_Name']) ?></option><?php endforeach; ?></select></div>
      <div><label for="price">ราคาเช่าบูชา (บาท) *</label><input id="price" type="number" name="price" min="1" max="99999999" step="1" value="<?= old('price') ?>" required></div>
    </div>
    <div class="row">
      <div><label for="monk">พระเกจิอาจารย์ผู้สร้าง</label><input id="monk" type="text" name="monk" maxlength="150" value="<?= old('monk') ?>" placeholder="เช่น หลวงปู่ทวด"></div>
      <div><label for="temple">วัดที่ออก</label><input id="temple" type="text" name="temple" maxlength="150" value="<?= old('temple') ?>" placeholder="เช่น วัดช้างให้"></div>
    </div>
    <div class="row">
      <div><label for="year">ปี พ.ศ. ที่สร้าง</label><input id="year" type="number" name="year" min="1" max="2700" value="<?= old('year') ?>" placeholder="2505"></div>
      <div><label for="material">เนื้อพระ / วัสดุ</label><input id="material" type="text" name="material" maxlength="100" value="<?= old('material') ?>" placeholder="เนื้อผง, ทองแดง, นวโลหะ"></div>
    </div>
    <div class="row">
      <div><label for="condition">สภาพพระ</label><input id="condition" type="text" name="condition" maxlength="100" value="<?= old('condition') ?>" placeholder="สวยแชมป์, สภาพดี, ผ่านการใช้"></div>
      <div><label for="certificate">ใบรับรองพระแท้ (เลขที่ / สถาบัน)</label><input id="certificate" type="text" name="certificate" maxlength="200" value="<?= old('certificate') ?>" placeholder="เว้นว่างถ้าไม่มี"></div>
    </div>
    <label for="guarantee">เงื่อนไขการรับประกัน</label><input id="guarantee" type="text" name="guarantee" maxlength="255" value="<?= old('guarantee') ?>" placeholder="เช่น รับประกันแท้ คืนเงินเต็มจำนวนใน 7 วัน">
    <label for="description">ตำหนิ ประวัติ และรายละเอียดเพิ่มเติม</label><textarea id="description" name="description" maxlength="5000"><?= old('description') ?></textarea>
    <h2 style="margin-top:22px">รูปภาพ (JPG / PNG / WEBP ไม่เกิน 3MB ต่อรูป)</h2>
    <div class="row">
      <?php foreach ($slots as $key => $label): ?>
        <div><label for="img_<?= $key ?>"><?= e($label) ?></label>
          <input id="img_<?= $key ?>" type="file" name="img_<?= $key ?>" accept="image/jpeg,image/png,image/webp" <?= $key === 'front' ? 'required' : '' ?>></div>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:22px"><button>ลงขาย</button></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
