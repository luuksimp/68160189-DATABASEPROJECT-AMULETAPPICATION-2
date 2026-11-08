<?php
// ===== bootstrap: ตั้งค่าพื้นฐาน จัดการ error และ helper ของทั้งระบบ =====
if (!is_file(__DIR__ . '/../config.php')) {
    http_response_code(500);
    exit('ยังไม่พบไฟล์ config.php — ให้คัดลอก config.example.php เป็น config.php แล้วแก้ค่าฐานข้อมูล');
}
require_once __DIR__ . '/../config.php';
// ค่าเริ่มต้น เผื่อ config.php เก่าที่ยังไม่มีค่าเหล่านี้
defined('APP_DEBUG') || define('APP_DEBUG', false);
defined('TIMEZONE') || define('TIMEZONE', 'Asia/Bangkok');
defined('DB_TZ_OFFSET') || define('DB_TZ_OFFSET', '+07:00');
defined('PER_PAGE') || define('PER_PAGE', 12);
defined('ORDER_HOLD_HOURS') || define('ORDER_HOLD_HOURS', 48);

date_default_timezone_set(TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ob_start(); // เก็บ output ไว้ก่อน เพื่อทิ้งหน้าที่เสียครึ่งๆ กลางๆ ได้เมื่อเกิด error

/** ข้อผิดพลาดที่แสดงให้ผู้ใช้เห็นได้ (ต่างจาก error ระบบที่ต้องซ่อนรายละเอียด) */
class UserError extends RuntimeException {}

function render_error_page(int $code, string $msg): never {
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) { http_response_code($code); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>เกิดข้อผิดพลาด</title><link rel="stylesheet" href="assets/style.css"></head><body>'
       . '<main class="wrap page" style="text-align:center;padding-top:70px"><h1>' . htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') . '</h1>'
       . '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
       . '<p><a class="btn" href="index.php">กลับหน้าตลาดพระ</a></p></main></body></html>';
    exit;
}

set_exception_handler(function (Throwable $ex): void {
    error_log('[amulet-market] ' . get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    render_error_page(500, APP_DEBUG ? $ex->getMessage() : 'ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในอีกสักครู่');
});

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . DB_TZ_OFFSET . "'",
                ]
            );
        } catch (PDOException $ex) {
            // ไม่แสดงรายละเอียด (อาจมีชื่อ user/host) ลงหน้าเว็บ
            error_log('[amulet-market] DB connect failed: ' . $ex->getMessage());
            render_error_page(503, 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
        }
    }
    return $pdo;
}

/** รันงานใน transaction; rollback ให้เสมอเมื่อพลาด และลองซ้ำเมื่อชน deadlock / lock timeout */
function transact(callable $fn, int $retries = 2) {
    $pdo = db();
    for ($try = 0; ; $try++) {
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $code = $ex instanceof PDOException ? (int)($ex->errorInfo[1] ?? 0) : 0;
            if (in_array($code, [1213, 1205], true) && $try < $retries) { usleep(150000 * ($try + 1)); continue; }
            throw $ex;
        }
    }
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function old(string $k): string { return e($_POST[$k] ?? ''); }
function redirect(string $url): never { header('Location: ' . $url); exit; }

/** รับเฉพาะ path ภายในเว็บ กัน open redirect */
function safe_back($b, string $default = 'index.php'): string {
    $b = (string)$b;
    if ($b === '' || !preg_match('#^[A-Za-z0-9_./?=&%\#-]+$#', $b) || str_starts_with($b, '//') || str_contains($b, '://')) return $default;
    return $b;
}

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT * FROM users WHERE User_ID = ?');
            $st->execute([$_SESSION['uid']]);
            $user = $st->fetch() ?: null;
            if (!$user) unset($_SESSION['uid']); // บัญชีถูกลบไปแล้ว
        }
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) { flash('กรุณาเข้าสู่ระบบก่อน', 'err'); redirect('login.php'); }
    return $u;
}

function require_seller(): array {
    $u = require_login();
    if (!in_array($u['Role'], ['Seller', 'Admin'], true)) {
        flash('ต้องเป็นบัญชีผู้ขายเท่านั้น (เปลี่ยนประเภทบัญชีได้ที่หน้าโปรไฟล์)', 'err'); redirect('profile.php');
    }
    return $u;
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$msg, $type]; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        render_error_page(400, 'แบบฟอร์มหมดอายุหรือไม่ถูกต้อง กรุณาย้อนกลับ รีเฟรชหน้า แล้วลองใหม่');
    }
}

/** อัปโหลดรูป คืนค่า path แบบ relative หรือ null; โยน RuntimeException ถ้าไฟล์ไม่ผ่าน */
function save_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('รูปมีขนาดเกิน 3MB');
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ');
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('รูปมีขนาดเกิน 3MB');
    if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ');
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) throw new RuntimeException('รองรับเฉพาะไฟล์ JPG, PNG, WEBP');
    if ($info[0] > 8000 || $info[1] > 8000) throw new RuntimeException('ขนาดรูปใหญ่เกินไป (สูงสุด 8000x8000 พิกเซล)');
    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0755, true)) throw new RuntimeException('ไม่สามารถบันทึกรูปได้ในขณะนี้');
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) throw new RuntimeException('บันทึกรูปไม่สำเร็จ');
    return UPLOAD_URL . $name;
}

/** ลบไฟล์รูปที่เพิ่งอัปโหลด (ใช้เมื่อบันทึกฐานข้อมูลไม่สำเร็จ กันไฟล์ค้างเป็นขยะ) */
function delete_files(array $paths): void {
    foreach ($paths as $p) {
        if (is_string($p) && str_starts_with($p, UPLOAD_URL)) @unlink(UPLOAD_DIR . basename($p));
    }
}

function baht($n): string { return '฿' . number_format((float)$n, 0); }
function be_year(): int { return (int)date('Y') + 543; }

function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 604800) return floor($diff / 86400) . ' วันที่แล้ว';
    return date('d/m/Y', strtotime($dt));
}

function amulet_cover(int $amuletId): ?string {
    $st = db()->prepare('SELECT Image_URL FROM amulet_images WHERE Amulet_ID = ? ORDER BY Is_Primary DESC, Image_ID LIMIT 1');
    $st->execute([$amuletId]);
    return $st->fetchColumn() ?: null;
}

function update_seller_rating(int $sellerId): void {
    $st = db()->prepare(
        'SELECT AVG(r.Rating) FROM reviews_disputes r
         JOIN orders o ON o.Order_ID = r.Order_ID
         JOIN amulets a ON a.Amulet_ID = o.Amulet_ID
         WHERE a.Seller_ID = ? AND r.Rating IS NOT NULL'
    );
    $st->execute([$sellerId]);
    $avg = (float)$st->fetchColumn();
    db()->prepare('UPDATE users SET Rating_Score = ? WHERE User_ID = ?')->execute([round($avg, 2), $sellerId]);
}

/** รายการ Amulet_ID ที่ผู้ใช้ปัจจุบันกดบันทึกไว้ (key = id) */
function my_favorites(): array {
    $uid = (int)($_SESSION['uid'] ?? 0);
    if (!$uid) return [];
    $st = db()->prepare('SELECT Amulet_ID FROM favorites WHERE User_ID = ?');
    $st->execute([$uid]);
    return array_fill_keys(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)), true);
}

/** ปล่อยพระที่ถูกจองไว้แต่ไม่ชำระเกินเวลา กลับมาให้เช่าได้ (กันพระค้างสถานะ "จองแล้ว" ตลอดกาล) */
function release_expired_orders(): void {
    try {
        transact(function (PDO $pdo) {
            $st = $pdo->query(
                "SELECT Order_ID, Amulet_ID FROM orders
                 WHERE Payment_Status = 'รอชำระ' AND Order_Date < (NOW() - INTERVAL " . (int)ORDER_HOLD_HOURS . " HOUR) FOR UPDATE"
            );
            foreach ($st->fetchAll() as $o) {
                $pdo->prepare("UPDATE orders SET Payment_Status = 'ยกเลิก' WHERE Order_ID = ? AND Payment_Status = 'รอชำระ'")->execute([$o['Order_ID']]);
                $pdo->prepare("UPDATE amulets SET Stock_Status = 'พร้อมเช่า' WHERE Amulet_ID = ? AND Stock_Status = 'จองแล้ว'")->execute([$o['Amulet_ID']]);
            }
        });
    } catch (Throwable $ex) {
        error_log('[amulet-market] release_expired_orders: ' . $ex->getMessage()); // งานเสริม ไม่ให้ทำให้หน้าเว็บล่ม
    }
}

// ----- กันเดารหัสผ่าน: ผิดครบ 5 ครั้งใน 15 นาที (ต่อชื่อผู้ใช้ + IP) ให้รอก่อน -----
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function login_key(string $id): string { return mb_substr(mb_strtolower($id), 0, 120); }
function login_blocked(string $id): bool {
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE Identifier = ? AND IP = ? AND Attempted_At > (NOW() - INTERVAL 15 MINUTE)');
        $st->execute([login_key($id), client_ip()]);
        return (int)$st->fetchColumn() >= 5;
    } catch (Throwable $ex) { error_log('[amulet-market] login_blocked: ' . $ex->getMessage()); return false; }
}
function login_failed(string $id): void {
    try {
        db()->prepare('INSERT INTO login_attempts (Identifier, IP) VALUES (?, ?)')->execute([login_key($id), client_ip()]);
        db()->exec('DELETE FROM login_attempts WHERE Attempted_At < (NOW() - INTERVAL 1 DAY)');
    } catch (Throwable $ex) { error_log('[amulet-market] login_failed: ' . $ex->getMessage()); }
}
function login_clear(string $id): void {
    try { db()->prepare('DELETE FROM login_attempts WHERE Identifier = ? AND IP = ?')->execute([login_key($id), client_ip()]); }
    catch (Throwable $ex) { error_log('[amulet-market] login_clear: ' . $ex->getMessage()); }
}

/** ดึงโพสต์พร้อมสถิติ ไลก์/โหวต/คอมเมนต์ และ reaction ของผู้ใช้ปัจจุบัน ($where/$order ต้องเป็นค่าที่โค้ดกำหนดเองเท่านั้น) */
function fetch_posts(string $where = '1=1', array $params = [], string $order = 'p.Created_At DESC', int $limit = 20): array {
    $uid = (int)($_SESSION['uid'] ?? 0);
    $sql = "SELECT p.*, u.Username, u.Full_Name, u.Verification_Status,
              a.Name AS AmuletName, a.Price AS AmuletPrice, a.Stock_Status,
              (SELECT Image_URL FROM amulet_images i WHERE i.Amulet_ID = p.Amulet_ID ORDER BY i.Is_Primary DESC, i.Image_ID LIMIT 1) AS AmuletCover,
              (SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.Reaction_Type='like') AS likes,
              (SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.Reaction_Type='real') AS reals,
              (SELECT COUNT(*) FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.Reaction_Type='fake') AS fakes,
              (SELECT COUNT(*) FROM comments c WHERE c.Post_ID = p.Post_ID) AS comment_count,
              (SELECT r.Reaction_Type FROM reactions r WHERE r.Post_ID = p.Post_ID AND r.User_ID = ?) AS my_reaction
            FROM posts p
            JOIN users u ON u.User_ID = p.User_ID
            LEFT JOIN amulets a ON a.Amulet_ID = p.Amulet_ID
            WHERE $where ORDER BY $order LIMIT " . (int)$limit;
    $st = db()->prepare($sql);
    $st->execute(array_merge([$uid], $params));
    return $st->fetchAll();
}
