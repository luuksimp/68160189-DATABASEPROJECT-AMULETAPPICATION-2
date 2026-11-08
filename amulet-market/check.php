<?php
ini_set('display_errors', '1'); error_reporting(E_ALL);
echo 'PHP ' . PHP_VERSION . '<br>';
foreach (['pdo_mysql', 'mbstring'] as $x) echo $x . ': ' . (extension_loaded($x) ? 'ok' : 'ไม่มี') . '<br>';
echo 'config.php: ' . (is_file(__DIR__ . '/config.php') ? 'มี' : 'ไม่มี') . '<br><br>';
foreach (array_merge(glob(__DIR__ . '/*.php'), glob(__DIR__ . '/includes/*.php')) as $f) {
    try { token_get_all(file_get_contents($f), TOKEN_PARSE); echo basename($f) . ' ok<br>'; }
    catch (ParseError $e) { echo '<b>' . basename($f) . ': ' . htmlspecialchars($e->getMessage()) . ' (บรรทัด ' . $e->getLine() . ')</b><br>'; }
}