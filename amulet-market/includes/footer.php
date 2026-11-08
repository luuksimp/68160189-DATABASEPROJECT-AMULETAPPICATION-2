<?php if (empty($showHero)): ?></div><?php endif; ?>
</main>
<footer class="foot">
  <div class="wrap">© <?= date('Y') ?> <?= e(SITE_NAME) ?> — ระบบตัวอย่างสำหรับการเรียนรู้ ควรตรวจสอบพระแท้และใช้ระบบตัวกลางทุกครั้งก่อนโอนเงิน</div>
</footer>
<script>
// กันกดปุ่มซ้ำ (เช่น สั่งซื้อ/ลงขายซ้ำเมื่อเน็ตช้า) และคืนปุ่มเมื่อกดปุ่ม "ย้อนกลับ"
document.addEventListener('submit', function (ev) {
  if (ev.defaultPrevented) return;
  var b = ev.target.querySelector('button:not([type=button])');
  if (b) setTimeout(function () { b.disabled = true; }, 0);
});
window.addEventListener('pageshow', function () {
  document.querySelectorAll('form button:disabled').forEach(function (b) { b.disabled = false; });
});
</script>
</body>
</html>
