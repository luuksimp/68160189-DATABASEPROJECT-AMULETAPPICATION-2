<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'ตลาดพระเครื่อง';
$showHero = true;
require __DIR__ . '/includes/header.php';

if (random_int(1, 20) === 1) release_expired_orders();

$cats = db()->query('SELECT * FROM categories ORDER BY Category_ID')->fetchAll();
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$cat = (int)($_GET['cat'] ?? 0);
$min = trim((string)($_GET['min'] ?? '')) !== '' ? max(0.0, (float)$_GET['min']) : null;
$max = trim((string)($_GET['max'] ?? '')) !== '' ? max(0.0, (float)$_GET['max']) : null;
$certOnly = !empty($_GET['cert']);
$favOnly = !empty($_GET['fav']) && $me;
$f = in_array($_GET['f'] ?? 'all', ['all', 'popular', 'classic', 'new'], true) ? $_GET['f'] : 'all';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ["a.Stock_Status <> 'ซ่อนประกาศ'"];
$params = [];
if ($q !== '') {
    $where[] = '(a.Name LIKE ? OR a.Monk_Creator LIKE ? OR a.Temple LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($cat) { $where[] = 'a.Category_ID = ?'; $params[] = $cat; }
if ($min !== null) { $where[] = 'a.Price >= ?'; $params[] = $min; }
if ($max !== null) { $where[] = 'a.Price <= ?'; $params[] = $max; }
if ($certOnly) $where[] = "(a.Certificate_Info IS NOT NULL AND a.Certificate_Info <> '')";
if ($favOnly) { $where[] = 'a.Amulet_ID IN (SELECT Amulet_ID FROM favorites WHERE User_ID = ?)'; $params[] = (int)$me['User_ID']; }
if ($f === 'classic') $where[] = 'a.Year_Created IS NOT NULL AND a.Year_Created <= ' . (be_year() - 50);
if ($f === 'new') $where[] = 'a.Created_At >= (NOW() - INTERVAL 14 DAY)';
$whereSql = implode(' AND ', $where);
$order = $f === 'popular' ? 'favs DESC, a.Created_At DESC' : 'a.Created_At DESC';

$cnt = db()->prepare("SELECT COUNT(*) FROM amulets a WHERE $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / PER_PAGE));
$page = min($page, $pages);

$st = db()->prepare(
    "SELECT a.*, c.Category_Name, u.Username, u.Verification_Status,
            (SELECT Image_URL FROM amulet_images i WHERE i.Amulet_ID = a.Amulet_ID ORDER BY i.Is_Primary DESC, i.Image_ID LIMIT 1) AS cover,
            (SELECT COUNT(*) FROM favorites fv WHERE fv.Amulet_ID = a.Amulet_ID) AS favs
     FROM amulets a
     JOIN categories c ON c.Category_ID = a.Category_ID
     JOIN users u ON u.User_ID = a.Seller_ID
     WHERE $whereSql ORDER BY $order LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)(($page - 1) * PER_PAGE)
);
$st->execute($params);
$items = $st->fetchAll();
$favs = my_favorites();

$keep = ['q' => $q, 'cat' => $cat ?: '', 'min' => $_GET['min'] ?? '', 'max' => $_GET['max'] ?? '', 'cert' => $certOnly ? 1 : '', 'fav' => $favOnly ? 1 : ''];
$url = fn(array $extra) => 'index.php?' . http_build_query(array_filter(array_merge($keep, $extra), fn($v) => $v !== '' && $v !== null && $v !== 'all'));
?>
<section class="hero">
  <div class="wrap">
    <div>
      <p class="eyebrow">เสน่ห์แห่งศรัทธา · เรื่องราวเหนือกาลเวลา</p>
      <h1>ค้นพบพระเครื่องที่ถูกใจ ในพื้นที่แห่งความศรัทธา</h1>
      <p class="lead">ค้นหา เลือกดูรายละเอียด โหวตแท้/เก๊กับชุมชน และเช่าบูชาผ่านระบบตัวกลาง ในที่เดียว</p>
    </div>
    <div class="hero-art" role="img" aria-label="ภาพประกอบพระเครื่องแบบกราฟิกโทนทอง"><div class="amulet" aria-hidden="true"></div></div>
  </div>
</section>

<div class="wrap page">
  <p class="eyebrow">EXPLORE THE COLLECTION</p>
  <h2>เลือกชมพระเครื่อง</h2>
  <form class="panel filters" method="get" action="index.php">
    <div><label for="q">ค้นหาชื่อพระ / เกจิ / วัด</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ลองค้นหา เช่น สมเด็จ หรือ ลำพูน"></div>
    <div><label for="cat">หมวดหมู่</label>
      <select id="cat" name="cat"><option value="0">ทุกหมวดหมู่</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['Category_ID'] ?>" <?= $cat === (int)$c['Category_ID'] ? 'selected' : '' ?>><?= e($c['Category_Name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="min">ราคาต่ำสุด (บาท)</label><input id="min" type="number" name="min" min="0" value="<?= e($_GET['min'] ?? '') ?>" placeholder="0"></div>
    <div><label for="max">ราคาสูงสุด (บาท)</label><input id="max" type="number" name="max" min="0" value="<?= e($_GET['max'] ?? '') ?>" placeholder="ไม่จำกัด"></div>
    <div class="full">
      <label class="check"><input type="checkbox" name="cert" value="1" <?= $certOnly ? 'checked' : '' ?>> เฉพาะรายการที่มีใบรับรอง</label>
      <?php if ($me): ?><label class="check"><input type="checkbox" name="fav" value="1" <?= $favOnly ? 'checked' : '' ?>> เฉพาะที่บันทึกไว้</label><?php endif; ?>
      <?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?>
      <button>ค้นหา</button> <a class="btn ghost" href="index.php">ล้างตัวกรอง</a>
    </div>
  </form>

  <nav class="chips" aria-label="กรองรายการเด่น">
    <?php foreach (['all' => 'ทั้งหมด', 'popular' => 'ยอดนิยม', 'classic' => 'พระเก่า', 'new' => 'มาใหม่'] as $k => $label): ?>
      <a class="chip" href="<?= e($url(['f' => $k])) ?>" aria-current="<?= $f === $k ? 'true' : 'false' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$items): ?><div class="panel muted" role="status" style="text-align:center;padding:48px">ไม่พบรายการที่ตรงกับคำค้นหา ลองปรับตัวกรองหรือค้นหาด้วยคำอื่น</div><?php endif; ?>
  <div class="grid">
  <?php foreach ($items as $a):
      $aid = (int)$a['Amulet_ID'];
      $isFav = isset($favs[$aid]);
      $tag = (int)$a['favs'] >= 3 ? 'ยอดนิยม' : (($a['Year_Created'] && (int)$a['Year_Created'] <= be_year() - 50) ? 'พระเก่า' : (strtotime($a['Created_At']) >= time() - 14 * 86400 ? 'มาใหม่' : e($a['Category_Name'])));
  ?>
    <article class="item">
      <a class="art" href="amulet.php?id=<?= $aid ?>" tabindex="-1" aria-hidden="true">
        <?php if ($a['cover']): ?><img src="<?= e($a['cover']) ?>" alt="" loading="lazy"><?php else: ?><div class="noimg">ไม่มีรูป</div><?php endif; ?>
      </a>
      <div class="body">
        <span class="tag"><?= $tag ?></span>
        <h3><a href="amulet.php?id=<?= $aid ?>"><?= e($a['Name']) ?></a></h3>
        <div class="meta"><?= e($a['Category_Name']) ?><?= $a['Material'] ? ' · ' . e($a['Material']) : '' ?><?= $a['Temple'] ? ' · ' . e($a['Temple']) : '' ?></div>
        <div class="meta">✓ @<?= e($a['Username']) ?><?= $a['Verification_Status'] === 'ยืนยันแล้ว' ? ' · ยืนยันตัวตนแล้ว' : '' ?><?= $a['Certificate_Info'] ? ' · มีใบรับรอง' : '' ?></div>
        <div><span class="badge <?= $a['Stock_Status'] === 'พร้อมเช่า' ? 'green' : 'gray' ?>"><?= e($a['Stock_Status']) ?></span></div>
        <div class="foot-row">
          <span class="price"><?= baht($a['Price']) ?></span>
          <?php if ($me): ?>
            <form method="post" action="favorite.php"><?= csrf_field() ?>
              <input type="hidden" name="amulet_id" value="<?= $aid ?>"><input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI']) ?>">
              <button class="heart <?= $isFav ? 'on' : '' ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" aria-label="<?= $isFav ? 'เลิกบันทึก ' : 'บันทึก ' ?><?= e($a['Name']) ?>"><?= $isFav ? '♥' : '♡' ?></button>
            </form>
          <?php else: ?><a class="heart" href="login.php" aria-label="เข้าสู่ระบบเพื่อบันทึก <?= e($a['Name']) ?>">♡</a><?php endif; ?>
        </div>
        <a class="btn ghost small" style="text-align:center;margin-top:10px" href="amulet.php?id=<?= $aid ?>">ดูรายละเอียด</a>
      </div>
    </article>
  <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="เปลี่ยนหน้า">
    <?php if ($page > 1): ?><a class="btn ghost small" href="<?= e($url(['f' => $f, 'page' => $page - 1])) ?>">← ก่อนหน้า</a><?php endif; ?>
    <span class="muted">หน้า <?= $page ?> / <?= $pages ?> · ทั้งหมด <?= $total ?> รายการ</span>
    <?php if ($page < $pages): ?><a class="btn ghost small" href="<?= e($url(['f' => $f, 'page' => $page + 1])) ?>">ถัดไป →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
