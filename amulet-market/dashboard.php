<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_seller();
$uid = (int)$me['User_ID'];

// ซ่อน / แสดงประกาศ (เปลี่ยนได้เฉพาะพระที่ยังไม่ถูกจอง/ขาย)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aid = (int)($_POST['amulet_id'] ?? 0);
    $to = ($_POST['to'] ?? '') === 'hide' ? 'ซ่อนประกาศ' : 'พร้อมเช่า';
    $from = $to === 'ซ่อนประกาศ' ? 'พร้อมเช่า' : 'ซ่อนประกาศ';
    $st = db()->prepare('UPDATE amulets SET Stock_Status = ? WHERE Amulet_ID = ? AND Seller_ID = ? AND Stock_Status = ?');
    $st->execute([$to, $aid, $uid, $from]);
    if ($st->rowCount()) flash($to === 'ซ่อนประกาศ' ? 'ซ่อนประกาศแล้ว' : 'แสดงประกาศอีกครั้งแล้ว');
    else flash('เปลี่ยนสถานะไม่ได้ (พระอาจถูกจองหรือขายไปแล้ว)', 'err');
    redirect('dashboard.php');
}

$st = db()->prepare(
    'SELECT a.*, c.Category_Name,
            (SELECT COUNT(*) FROM favorites f WHERE f.Amulet_ID = a.Amulet_ID) AS favs,
            (SELECT Image_URL FROM amulet_images i WHERE i.Amulet_ID = a.Amulet_ID ORDER BY i.Is_Primary DESC, i.Image_ID LIMIT 1) AS cover
     FROM amulets a JOIN categories c ON c.Category_ID = a.Category_ID
     WHERE a.Seller_ID = ? ORDER BY a.Created_At DESC LIMIT 200'
);
$st->execute([$uid]);
$mine = $st->fetchAll();
$count = fn($s) => count(array_filter($mine, fn($a) => $a['Stock_Status'] === $s));

$pageTitle = 'แดชบอร์ดผู้ขาย';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
  <div><p class="eyebrow">SELLER SPACE</p><h1>แดชบอร์ดผู้ขาย</h1></div>
  <a class="btn" href="amulet_add.php">+ ลงขายพระเครื่อง</a>
</div>
<div class="stats">
  <div class="stat"><b><?= count($mine) ?></b>ประกาศทั้งหมด</div>
  <div class="stat"><b><?= $count('พร้อมเช่า') ?></b>พร้อมเช่า</div>
  <div class="stat"><b><?= $count('จองแล้ว') ?></b>จองแล้ว</div>
  <div class="stat"><b><?= $count('ปล่อยเช่าแล้ว') ?></b>ปล่อยเช่าแล้ว</div>
  <div class="stat"><b><?= number_format((float)$me['Rating_Score'], 2) ?></b>คะแนนผู้ขาย</div>
</div>
<div class="panel">
  <h2>ประกาศของฉัน</h2>
  <?php if (!$mine): ?><p class="muted">ยังไม่มีประกาศ — กด “ลงขายพระเครื่อง” เพื่อเริ่มต้น</p><?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>พระเครื่อง</th><th>ราคา</th><th>สถานะ</th><th>บันทึก</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($mine as $a): ?>
      <tr>
        <td><a href="amulet.php?id=<?= (int)$a['Amulet_ID'] ?>"><?= e($a['Name']) ?></a><div class="muted"><?= e($a['Category_Name']) ?><?= $a['Temple'] ? ' · ' . e($a['Temple']) : '' ?></div></td>
        <td><?= baht($a['Price']) ?></td>
        <td><span class="badge <?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'green' : 'gray' ?>"><?= e($a['Stock_Status']) ?></span></td>
        <td>♥ <?= (int)$a['favs'] ?></td>
        <td>
          <?php if (in_array($a['Stock_Status'], ['พร้อมเช่า', 'ซ่อนประกาศ'], true)): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="amulet_id" value="<?= (int)$a['Amulet_ID'] ?>">
              <input type="hidden" name="to" value="<?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'hide' : 'show' ?>">
              <button class="ghost small"><?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'ซ่อนประกาศ' : 'แสดงประกาศ' ?></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<p><a class="btn ghost" href="orders.php">ดูคำสั่งซื้อที่ได้รับ</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
