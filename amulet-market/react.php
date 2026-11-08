<?php
// กดไลก์ / โหวตแท้ / โหวตเก๊ — 1 คนต่อ 1 โพสต์ กดซ้ำชนิดเดิมเพื่อยกเลิก กดชนิดอื่นเพื่อเปลี่ยน
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('feed.php');
csrf_check();

$postId = (int)($_POST['post_id'] ?? 0);
$type = (string)($_POST['type'] ?? '');
$back = safe_back($_POST['back'] ?? '', 'feed.php');
if (!in_array($type, ['like', 'real', 'fake'], true)) redirect($back);

$st = db()->prepare('SELECT 1 FROM posts WHERE Post_ID = ?');
$st->execute([$postId]);
if (!$st->fetch()) redirect('feed.php');

$st = db()->prepare('SELECT Reaction_Type FROM reactions WHERE Post_ID = ? AND User_ID = ?');
$st->execute([$postId, $me['User_ID']]);
$existing = $st->fetchColumn();

if ($existing === $type) {
    db()->prepare('DELETE FROM reactions WHERE Post_ID = ? AND User_ID = ?')->execute([$postId, $me['User_ID']]);
} else {
    // UPSERT: กดรัวหรือกดจากสองแท็บพร้อมกันก็ไม่ชน UNIQUE KEY จนหน้า error
    db()->prepare(
        'INSERT INTO reactions (Post_ID, User_ID, Reaction_Type) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE Reaction_Type = VALUES(Reaction_Type), Created_At = NOW()'
    )->execute([$postId, $me['User_ID'], $type]);
}
redirect($back);
