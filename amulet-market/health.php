<?php
// ใช้เช็คสถานะระบบ (เช่น ให้ UptimeRobot ยิงดู) — ไม่เปิดเผยรายละเอียดภายใน
require_once __DIR__ . '/includes/functions.php';
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    db()->query('SELECT 1');
    echo '{"status":"ok"}';
} catch (Throwable $ex) {
    http_response_code(503);
    echo '{"status":"down"}';
}
