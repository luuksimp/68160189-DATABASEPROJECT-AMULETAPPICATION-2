<?php
// ใส่ข้อมูลตัวอย่าง 4 รายการตามเทมเพลต (รันจาก command line เท่านั้น):  php tools/seed_demo.php
// สร้างผู้ขายตัวอย่าง demo_seller พร้อมรหัสผ่านสุ่ม (แสดงครั้งเดียวตอนรัน) — รันซ้ำได้ ไม่สร้างซ้ำ
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../includes/functions.php';

$items = [
  ['พระสมเด็จพิมพ์ใหญ่', 'พระผง', 'สมเด็จพระพุฒาจารย์ (โต)', 'วัดระฆังโฆสิตาราม', 2505, 'เนื้อผงพุทธคุณ', 'สภาพดี', 2500, 'CERT-001 · สถาบันพระเครื่องตัวอย่าง', 'https://images.pexels.com/photos/28442607/pexels-photo-28442607.jpeg'],
  ['พระรอดลำพูน', 'พระกรุ', null, 'วัดมหาวัน', 2497, 'เนื้อดิน', 'ผ่านการใช้', 3200, null, 'https://images.pexels.com/photos/10185520/pexels-photo-10185520.jpeg'],
  ['เหรียญหลวงพ่อ', 'พระเหรียญ', 'หลวงพ่อกวย', 'วัดโฆสิตาราม', 2515, 'เนื้อทองแดง', 'สภาพดี', 1850, 'CERT-003 · สถาบันพระเครื่องตัวอย่าง', 'https://images.pexels.com/photos/7267600/pexels-photo-7267600.jpeg'],
  ['พระปิดตาเนื้อผง', 'พระปิดตา', 'หลวงปู่ทวด', 'วัดช้างให้', 2520, 'เนื้อผง', 'สวยแชมป์', 2100, null, 'https://images.pexels.com/photos/12851427/pexels-photo-12851427.jpeg'],
];

$pdo = db();
$st = $pdo->prepare("SELECT User_ID FROM users WHERE Username = 'demo_seller'");
$st->execute();
$sid = $st->fetchColumn();
if (!$sid) {
    $pw = bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO users (Username, Password_Hash, Email, Role, Full_Name, Verification_Status) VALUES ('demo_seller', ?, 'demo_seller@example.com', 'Seller', 'ร้านศรัทธาสะสม (ตัวอย่าง)', 'ยืนยันแล้ว')")
        ->execute([password_hash($pw, PASSWORD_DEFAULT)]);
    $sid = (int)$pdo->lastInsertId();
    echo "สร้างผู้ขายตัวอย่าง: demo_seller / $pw  (จดไว้ ระบบจะไม่แสดงอีก)\n";
}
foreach ($items as [$name, $cat, $monk, $temple, $year, $material, $cond, $price, $cert, $img]) {
    $ex = $pdo->prepare('SELECT 1 FROM amulets WHERE Seller_ID = ? AND Name = ?');
    $ex->execute([$sid, $name]);
    if ($ex->fetch()) { echo "ข้าม (มีแล้ว): $name\n"; continue; }
    $c = $pdo->prepare('SELECT Category_ID FROM categories WHERE Category_Name = ?');
    $c->execute([$cat]);
    $cid = $c->fetchColumn();
    if (!$cid) { echo "ไม่พบหมวด $cat — ข้าม $name (รัน migration.sql แล้วหรือยัง?)\n"; continue; }
    transact(function (PDO $pdo) use ($sid, $name, $cid, $monk, $temple, $year, $material, $cond, $price, $cert, $img) {
        $pdo->prepare('INSERT INTO amulets (Seller_ID, Name, Category_ID, Monk_Creator, Temple, Year_Created, Material, Condition_Desc, Price, Certificate_Info, Guarantee_Policy, Description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$sid, $name, $cid, $monk, $temple, $year, $material, $cond, $price, $cert, 'เงื่อนไขรับประกันตัวอย่าง', 'รายการตัวอย่างเพื่อสาธิตเท่านั้น']);
        $aid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO amulet_images (Amulet_ID, Image_URL, Is_Primary) VALUES (?,?,1)')->execute([$aid, $img]);
    });
    echo "เพิ่ม: $name\n";
}
