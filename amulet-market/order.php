<?php
// สร้างคำสั่งซื้อ — transaction + FOR UPDATE กันซื้อซ้ำพระองค์เดียวกัน (กดซ้ำ/สองคนกดพร้อมกันก็ไม่ซ้ำ)
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();

$aid = (int)($_POST['amulet_id'] ?? 0);
$name = mb_substr(trim((string)($_POST['ship_name'] ?? '')), 0, 120);
$phone = trim((string)($_POST['ship_phone'] ?? ''));
$addr = mb_substr(trim((string)($_POST['ship_address'] ?? '')), 0, 500);
$_SESSION['checkout_old'] = ['ship_name' => $name, 'ship_phone' => $phone, 'ship_address' => $addr];

try {
    if ($name === '' || $addr === '') throw new UserError('กรุณากรอกชื่อผู้รับและที่อยู่จัดส่ง');
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) throw new UserError('เบอร์โทรศัพท์ไม่ถูกต้อง');

    $oid = transact(function (PDO $pdo) use ($aid, $me, $name, $phone, $addr) {
        $st = $pdo->prepare('SELECT * FROM amulets WHERE Amulet_ID = ? FOR UPDATE');
        $st->execute([$aid]);
        $a = $st->fetch();
        if (!$a || $a['Stock_Status'] !== 'พร้อมเช่า') throw new UserError('พระองค์นี้ไม่พร้อมให้เช่าแล้ว');
        if ((int)$a['Seller_ID'] === (int)$me['User_ID']) throw new UserError('ไม่สามารถเช่าพระของตัวเองได้');

        $pdo->prepare('INSERT INTO orders (Buyer_ID, Amulet_ID, Total_Amount, Ship_Name, Ship_Phone, Ship_Address) VALUES (?,?,?,?,?,?)')
            ->execute([$me['User_ID'], $aid, $a['Price'], $name, $phone, $addr]);
        $oid = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE amulets SET Stock_Status = 'จองแล้ว' WHERE Amulet_ID = ?")->execute([$aid]);
        return $oid;
    });
    unset($_SESSION['checkout_old']);
    flash('สร้างคำสั่งซื้อเรียบร้อย กรุณาชำระเงินผ่านระบบตัวกลางภายใน ' . (int)ORDER_HOLD_HOURS . ' ชั่วโมง');
    redirect('orders.php#order-' . $oid);
} catch (UserError $ex) {
    flash($ex->getMessage(), 'err');
    redirect('checkout.php?amulet_id=' . $aid);
}
