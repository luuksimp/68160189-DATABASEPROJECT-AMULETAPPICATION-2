<?php
// ใช้แสดงการ์ดโพสต์หนึ่งใบ ต้องกำหนดตัวแปร $p (แถวโพสต์จาก fetch_posts) ก่อน include
$me = current_user();
$my = $p['my_reaction'];
$cover = $p['Image_URL'] ?: ($p['AmuletCover'] ?? null);
?>
<article class="panel" id="post-<?= (int)$p['Post_ID'] ?>">
  <div class="post-head">
    <div>
      <strong><?= e($p['Full_Name']) ?></strong>
      <span class="muted">@<?= e($p['Username']) ?></span>
      <?php if ($p['Verification_Status'] === 'ยืนยันแล้ว'): ?><span class="badge green">ยืนยันตัวตนแล้ว</span><?php endif; ?>
      <div class="muted"><?= e(time_ago($p['Created_At'])) ?></div>
    </div>
    <span class="badge"><?= e($p['Post_Type']) ?></span>
  </div>

  <?php if (trim((string)$p['Content']) !== ''): ?>
    <p><?= nl2br(e($p['Content'])) ?></p>
  <?php endif; ?>

  <?php if ($cover): ?><img class="post-img" src="<?= e($cover) ?>" alt="รูปพระเครื่อง"><?php endif; ?>

  <?php if ($p['Amulet_ID'] && $p['AmuletName']): ?>
    <div class="attached">
      <a href="amulet.php?id=<?= (int)$p['Amulet_ID'] ?>"><strong><?= e($p['AmuletName']) ?></strong></a>
      — <span class="price"><?= baht($p['AmuletPrice']) ?></span>
      <span class="badge <?= $p['Stock_Status'] === 'พร้อมเช่า' ? 'green' : 'gray' ?>"><?= e($p['Stock_Status']) ?></span>
    </div>
  <?php endif; ?>

  <div class="poststats">
    👍 <?= (int)$p['likes'] ?> ไลก์ | ✅ <?= (int)$p['reals'] ?> โหวตแท้ | ❌ <?= (int)$p['fakes'] ?> โหวตเก๊ |
    <a href="post_view.php?id=<?= (int)$p['Post_ID'] ?>">💬 <?= (int)$p['comment_count'] ?> ความคิดเห็น</a>
  </div>

  <div class="actions">
    <?php foreach (['like' => '👍 ไลก์', 'real' => '✅ แท้', 'fake' => '❌ เก๊'] as $type => $label): ?>
      <form method="post" action="react.php">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= (int)$p['Post_ID'] ?>">
        <input type="hidden" name="type" value="<?= $type ?>">
        <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI']) ?>#post-<?= (int)$p['Post_ID'] ?>">
        <button class="react <?= $my === $type ? 'on' : '' ?>"><?= $label ?></button>
      </form>
    <?php endforeach; ?>
    <a class="btn ghost small" href="post_view.php?id=<?= (int)$p['Post_ID'] ?>">แสดงความคิดเห็น</a>
  </div>
</article>
