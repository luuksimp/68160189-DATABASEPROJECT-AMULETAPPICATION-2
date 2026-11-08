<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$oid = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

$st = db()->prepare(
    'SELECT o.*, a.Name AS AmuletName, a.Seller_ID FROM orders o JOIN amulets a ON a.Amulet_ID = o.Amulet_ID
     WHERE o.Order_ID = ? AND o.Buyer_ID = ?'
);
$st->execute([$oid, $me['User_ID']]);
$o = $st->fetch();
if (!$o || in_array($o['Payment_Status'], ['รอชำระ', 'ยกเลิก'], true)) { flash('ไม่พบคำสั่งซื้อที่รีวิวได้', 'err'); redirect('orders.php'); }

$dup = db()->prepare('SELECT 1 FROM reviews_disputes WHERE Order_ID = ?');
$dup->execute([$oid]);
if ($dup->fetch()) { flash('คำสั่งซื้อนี้ส่งรีวิวแล้ว', 'err'); redirect('orders.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = mb_substr(trim((string)($_POST['comment'] ?? '')), 0, 1000);
    $dispute = (string)($_POST['dispute'] ?? 'ไม่มี');
    if (!in_array($dispute, ['ไม่มี', 'แจ้งพระเก๊', 'ไม่ได้รับของ'], true)) $dispute = 'ไม่มี';
    if ($rating < 1 || $rating > 5) $errors[] = 'กรุณาให้คะแนน 1-5 ดาว';

    if (!$errors) {
        try {
            transact(function (PDO $pdo) use ($oid, $rating, $comment, $dispute, $o) {
                $pdo->prepare('INSERT INTO reviews_disputes (Order_ID, Rating, Comment, Dispute_Status) VALUES (?,?,?,?)')
                    ->execute([$oid, $rating, $comment, $dispute]);
                update_seller_rating((int)$o['Seller_ID']);
            });
            flash($dispute === 'ไม่มี' ? 'ขอบคุณสำหรับรีวิว' : 'ส่งเรื่องร้องเรียนแล้ว ทีมงานจะตรวจสอบ');
            redirect('orders.php');
        } catch (PDOException $ex) {
            if ((string)$ex->getCode() === '23000') { flash('คำสั่งซื้อนี้ส่งรีวิวแล้ว', 'err'); redirect('orders.php'); } // กดส่งซ้ำพร้อมกัน
            throw $ex;
        }
    }
}

$pageTitle = 'รีวิว / แจ้งปัญหา';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
  <h1>รีวิว: <?= e($o['AmuletName']) ?></h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $oid ?>">
    <label for="rating">คะแนน</label>
    <select id="rating" name="rating">
      <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> (<?= $i ?>)</option><?php endfor; ?>
    </select>
    <label for="comment">ข้อความรีวิว</label><textarea id="comment" name="comment" maxlength="1000"></textarea>
    <label for="dispute">แจ้งปัญหา (ถ้ามี)</label>
    <select id="dispute" name="dispute"><option value="ไม่มี">ไม่มีปัญหา</option><option value="แจ้งพระเก๊">แจ้งพระเก๊</option><option value="ไม่ได้รับของ">ไม่ได้รับของ</option></select>
    <p><button>ส่ง</button></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
