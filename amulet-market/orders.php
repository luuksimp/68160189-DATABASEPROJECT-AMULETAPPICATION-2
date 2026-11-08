<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$uid = (int)$me['User_ID'];

// ----- เปลี่ยนสถานะ: ทุกขั้นตอนอยู่ใน transaction และเช็คสถานะปัจจุบันซ้ำหลังล็อกแถว กันกดซ้ำ/ข้อมูลเก่า -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $oid = (int)($_POST['order_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $track = trim((string)($_POST['tracking'] ?? ''));
    try {
        $msg = transact(function (PDO $pdo) use ($oid, $action, $uid, $track) {
            $st = $pdo->prepare('SELECT o.*, a.Seller_ID FROM orders o JOIN amulets a ON a.Amulet_ID = o.Amulet_ID WHERE o.Order_ID = ? FOR UPDATE');
            $st->execute([$oid]);
            $o = $st->fetch();
            if (!$o) throw new UserError('ไม่พบคำสั่งซื้อ');
            $isBuyer = (int)$o['Buyer_ID'] === $uid;
            $isSeller = (int)$o['Seller_ID'] === $uid;
            $pay = $o['Payment_Status'];
            $ship = $o['Shipping_Status'];

            if ($action === 'pay' && $isBuyer && $pay === 'รอชำระ') {
                // จำลองการโอนผ่านระบบตัวกลาง (ใช้งานจริงให้เชื่อม payment gateway / escrow)
                $pdo->prepare("UPDATE orders SET Payment_Status = 'โอนเงินผ่านระบบตัวกลาง' WHERE Order_ID = ?")->execute([$oid]);
                return 'บันทึกการชำระเงินผ่านระบบตัวกลางแล้ว';
            }
            if ($action === 'ship' && $isSeller && !in_array($pay, ['รอชำระ', 'ยกเลิก'], true) && $ship === 'รอดำเนินการ') {
                if (!preg_match('/^[A-Za-z0-9\-]{5,60}$/', $track)) throw new UserError('เลขพัสดุต้องเป็นตัวอักษร/ตัวเลข 5-60 ตัว');
                $pdo->prepare("UPDATE orders SET Shipping_Status = 'จัดส่งแล้ว', Tracking_Number = ? WHERE Order_ID = ?")->execute([$track, $oid]);
                return 'บันทึกการจัดส่งแล้ว';
            }
            if ($action === 'received' && $isBuyer && $ship === 'จัดส่งแล้ว') {
                $pdo->prepare("UPDATE orders SET Shipping_Status = 'ได้รับของแล้ว' WHERE Order_ID = ?")->execute([$oid]);
                $pdo->prepare("UPDATE amulets SET Stock_Status = 'ปล่อยเช่าแล้ว' WHERE Amulet_ID = ?")->execute([$o['Amulet_ID']]);
                return 'ยืนยันรับของแล้ว';
            }
            if ($action === 'cancel' && $isBuyer && $pay === 'รอชำระ' && $ship === 'รอดำเนินการ') {
                // เก็บประวัติไว้ (ไม่ลบแถว) แล้วปล่อยพระกลับมาให้เช่าได้
                $pdo->prepare("UPDATE orders SET Payment_Status = 'ยกเลิก' WHERE Order_ID = ?")->execute([$oid]);
                $pdo->prepare("UPDATE amulets SET Stock_Status = 'พร้อมเช่า' WHERE Amulet_ID = ? AND Stock_Status = 'จองแล้ว'")->execute([$o['Amulet_ID']]);
                return 'ยกเลิกคำสั่งซื้อแล้ว';
            }
            throw new UserError('สถานะคำสั่งซื้อเปลี่ยนไปแล้ว กรุณารีเฟรชหน้า');
        });
        flash($msg);
    } catch (UserError $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('orders.php');
}

function order_stage(array $o): int {
    if ($o['Shipping_Status'] === 'ได้รับของแล้ว') return 3;
    if ($o['Shipping_Status'] === 'จัดส่งแล้ว') return 2;
    if (!in_array($o['Payment_Status'], ['รอชำระ', 'ยกเลิก'], true)) return 1;
    return 0;
}
function order_steps(array $o): void {
    if ($o['Payment_Status'] === 'ยกเลิก') { echo '<p><span class="badge red">คำสั่งซื้อถูกยกเลิก</span></p>'; return; }
    $labels = ['รอชำระเงิน', 'ชำระแล้ว (ผ่านระบบตัวกลาง)', 'จัดส่งแล้ว', 'ได้รับของแล้ว'];
    $stage = order_stage($o);
    echo '<ol class="steps">';
    foreach ($labels as $i => $t) echo '<li class="' . ($i <= $stage ? 'done' : '') . '">' . ($i <= $stage ? '● ' : '○ ') . e($t) . '</li>';
    echo '</ol>';
}

$sel = 'SELECT o.*, a.Name AS AmuletName, a.Seller_ID, b.Username AS BuyerName, s.Username AS SellerName,
               (SELECT Review_ID FROM reviews_disputes r WHERE r.Order_ID = o.Order_ID LIMIT 1) AS ReviewID
        FROM orders o
        JOIN amulets a ON a.Amulet_ID = o.Amulet_ID
        JOIN users b ON b.User_ID = o.Buyer_ID
        JOIN users s ON s.User_ID = a.Seller_ID ';
$st = db()->prepare($sel . 'WHERE o.Buyer_ID = ? ORDER BY o.Order_Date DESC LIMIT 100');
$st->execute([$uid]);
$bought = $st->fetchAll();
$sold = [];
if (in_array($me['Role'], ['Seller', 'Admin'], true)) {
    $st = db()->prepare($sel . 'WHERE a.Seller_ID = ? ORDER BY o.Order_Date DESC LIMIT 100');
    $st->execute([$uid]);
    $sold = $st->fetchAll();
}

$pageTitle = 'คำสั่งซื้อ';
require __DIR__ . '/includes/header.php';
$hidden = fn($oid, $act) => csrf_field() . '<input type="hidden" name="order_id" value="' . (int)$oid . '"><input type="hidden" name="action" value="' . $act . '">';
?>
<h1>ติดตามคำสั่งซื้อ</h1>
<p class="notice">การชำระเงินและการปล่อยเงินเป็นการจำลอง ยังไม่ใช่ escrow จริง</p>

<h2>คำสั่งซื้อของฉัน (ผู้ซื้อ)</h2>
<?php if (!$bought): ?><div class="panel muted">ยังไม่มีคำสั่งซื้อ เลือกรายการจาก <a href="index.php">ตลาดพระ</a> เพื่อเริ่มต้น</div><?php endif; ?>
<?php foreach ($bought as $o): ?>
  <article class="panel" id="order-<?= (int)$o['Order_ID'] ?>">
    <h3>#<?= (int)$o['Order_ID'] ?> · <a href="amulet.php?id=<?= (int)$o['Amulet_ID'] ?>"><?= e($o['AmuletName']) ?></a> · <span class="price"><?= baht($o['Total_Amount']) ?></span></h3>
    <div class="muted">ผู้ขาย @<?= e($o['SellerName']) ?> · <?= e($o['Order_Date']) ?></div>
    <?php order_steps($o); ?>
    <?php if ($o['Tracking_Number']): ?><p class="muted">เลขพัสดุ: <?= e($o['Tracking_Number']) ?></p><?php endif; ?>
    <div class="actions">
      <?php if ($o['Payment_Status'] === 'รอชำระ'): ?>
        <form method="post"><?= $hidden($o['Order_ID'], 'pay') ?><button>ชำระเงินผ่านระบบตัวกลาง (จำลอง)</button></form>
        <form method="post" onsubmit="return confirm('ยกเลิกคำสั่งซื้อ?')"><?= $hidden($o['Order_ID'], 'cancel') ?><button class="ghost">ยกเลิก</button></form>
      <?php endif; ?>
      <?php if ($o['Shipping_Status'] === 'จัดส่งแล้ว'): ?>
        <form method="post"><?= $hidden($o['Order_ID'], 'received') ?><button>ยืนยันได้รับของแล้ว</button></form>
      <?php endif; ?>
      <?php if (!in_array($o['Payment_Status'], ['รอชำระ', 'ยกเลิก'], true) && !$o['ReviewID']): ?>
        <a class="btn ghost" href="review.php?order_id=<?= (int)$o['Order_ID'] ?>">รีวิว / แจ้งปัญหา</a>
      <?php elseif ($o['ReviewID']): ?><span class="badge green">ส่งรีวิว/แจ้งเรื่องแล้ว</span><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>

<?php if (in_array($me['Role'], ['Seller', 'Admin'], true)): ?>
<h2 style="margin-top:32px">คำสั่งซื้อที่ได้รับ (ผู้ขาย)</h2>
<?php if (!$sold): ?><div class="panel muted">ยังไม่มีคำสั่งซื้อ</div><?php endif; ?>
<?php foreach ($sold as $o): ?>
  <article class="panel">
    <h3>#<?= (int)$o['Order_ID'] ?> · <?= e($o['AmuletName']) ?> · <span class="price"><?= baht($o['Total_Amount']) ?></span></h3>
    <div class="muted">ผู้ซื้อ @<?= e($o['BuyerName']) ?> · <?= e($o['Order_Date']) ?></div>
    <?php order_steps($o); ?>
    <?php if ($o['Payment_Status'] !== 'ยกเลิก' && $o['Ship_Name']): ?>
      <p class="muted">จัดส่งถึง: <?= e($o['Ship_Name']) ?> · <?= e($o['Ship_Phone']) ?><br><?= nl2br(e($o['Ship_Address'])) ?></p>
    <?php endif; ?>
    <?php if ($o['Tracking_Number']): ?><p class="muted">เลขพัสดุ: <?= e($o['Tracking_Number']) ?></p><?php endif; ?>
    <?php if (!in_array($o['Payment_Status'], ['รอชำระ', 'ยกเลิก'], true) && $o['Shipping_Status'] === 'รอดำเนินการ'): ?>
      <form method="post" class="actions"><?= $hidden($o['Order_ID'], 'ship') ?>
        <input type="text" name="tracking" placeholder="เลขพัสดุ" style="max-width:240px" maxlength="60" required>
        <button>บันทึกการจัดส่ง</button>
      </form>
    <?php elseif ($o['Payment_Status'] === 'รอชำระ'): ?><span class="muted">รอผู้ซื้อชำระเงิน</span><?php endif; ?>
  </article>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
