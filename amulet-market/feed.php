<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'ฟีด';
require __DIR__ . '/includes/header.php';

$sort = ($_GET['sort'] ?? 'new') === 'hot' ? 'hot' : 'new';
// Trending = ไลก์ + โหวต + คอมเมนต์ x2 ภายใน 7 วัน
$order = $sort === 'hot'
    ? '((SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID) + (SELECT COUNT(*) FROM comments c WHERE c.Post_ID = p.Post_ID) * 2) DESC, p.Created_At DESC'
    : 'p.Created_At DESC';
$where = $sort === 'hot' ? 'p.Created_At >= (NOW() - INTERVAL 7 DAY)' : '1=1';
$posts = fetch_posts($where, [], $order, 30);
?>
<h1>ฟีดพระเครื่อง</h1>
<p class="actions">
  <a class="btn small <?= $sort === 'new' ? '' : 'ghost' ?>" href="feed.php">ล่าสุด</a>
  <a class="btn small <?= $sort === 'hot' ? '' : 'ghost' ?>" href="feed.php?sort=hot">🔥 ยอดฮิต</a>
  <?php if ($me): ?><a class="btn small" href="post_create.php">+ โพสต์ใหม่</a><?php endif; ?>
</p>

<?php if (!$posts): ?><div class="panel muted">ยังไม่มีโพสต์ — มาเป็นคนแรกที่โพสต์เลย</div><?php endif; ?>
<div class="narrow-lg">
<?php foreach ($posts as $p): include __DIR__ . '/includes/post_card.php'; endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
