<?php
// บันทึก / เลิกบันทึกพระที่สนใจ (กดซ้ำเพื่อยกเลิก) — ไม่พังเมื่อกดซ้อนกัน
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();

$aid = (int)($_POST['amulet_id'] ?? 0);
$back = safe_back($_POST['back'] ?? '', 'index.php');

$st = db()->prepare('SELECT 1 FROM amulets WHERE Amulet_ID = ?');
$st->execute([$aid]);
if ($st->fetch()) {
    $del = db()->prepare('DELETE FROM favorites WHERE User_ID = ? AND Amulet_ID = ?');
    $del->execute([$me['User_ID'], $aid]);
    if ($del->rowCount() === 0) {
        db()->prepare('INSERT IGNORE INTO favorites (User_ID, Amulet_ID) VALUES (?, ?)')->execute([$me['User_ID'], $aid]);
    }
}
redirect($back);
