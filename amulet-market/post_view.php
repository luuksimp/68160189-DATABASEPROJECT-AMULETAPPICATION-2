<?php
require_once __DIR__ . '/includes/functions.php';
$postId = (int)($_GET['id'] ?? $_POST['post_id'] ?? 0);

$posts = fetch_posts('p.Post_ID = ?', [$postId], 'p.Created_At DESC', 1);
if (!$posts) render_error_page(404, 'ไม่พบโพสต์นี้');
$p = $posts[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = require_login();
    csrf_check();
    $text = trim((string)($_POST['comment'] ?? ''));
    if ($text === '') {
        flash('กรุณาพิมพ์ข้อความ', 'err');
    } elseif (mb_strlen($text) > 1000) {
        flash('คอมเมนต์ยาวเกิน 1000 ตัวอักษร', 'err');
    } else {
        db()->prepare('INSERT INTO comments (Post_ID, User_ID, Comment_Text) VALUES (?,?,?)')->execute([$postId, $me['User_ID'], $text]);
        flash('แสดงความคิดเห็นแล้ว');
    }
    redirect('post_view.php?id=' . $postId . '#comments');
}

$st = db()->prepare('SELECT c.*, u.Username, u.Full_Name FROM comments c JOIN users u ON u.User_ID = c.User_ID WHERE c.Post_ID = ? ORDER BY c.Created_At ASC');
$st->execute([$postId]);
$comments = $st->fetchAll();

$pageTitle = 'โพสต์ของ ' . $p['Username'];
require __DIR__ . '/includes/header.php';
?>
<div class="narrow-lg">
<?php include __DIR__ . '/includes/post_card.php'; ?>
<div class="panel" id="comments">
  <h2>ความคิดเห็น (<?= count($comments) ?>)</h2>
  <?php foreach ($comments as $c): ?>
    <div class="comment">
      <strong><?= e($c['Full_Name']) ?></strong> <span class="muted">@<?= e($c['Username']) ?> · <?= e(time_ago($c['Created_At'])) ?></span>
      <div><?= nl2br(e($c['Comment_Text'])) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$comments): ?><p class="muted">ยังไม่มีความคิดเห็น</p><?php endif; ?>

  <?php if ($me): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="post_id" value="<?= $postId ?>">
      <textarea name="comment" placeholder="แสดงความคิดเห็น เช่น ดูพิมพ์แล้วน่าจะแท้..." required></textarea>
      <p><button>ส่งความคิดเห็น</button></p>
    </form>
  <?php else: ?>
    <p><a href="login.php">เข้าสู่ระบบ</a> เพื่อแสดงความคิดเห็น</p>
  <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
