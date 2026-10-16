<?php

declare(strict_types=1);

/** Verifikasi login admin terhadap staging Turso (CLI). */
putenv('PONDOK_DB_DRIVER=turso');
require_once dirname(__DIR__) . '/config/database.php';

$st = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
$st->execute(['admin']);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "FAIL: user admin tidak ditemukan\n";
    exit(1);
}
$ok = password_verify('admin123', (string) ($row['password'] ?? ''));
echo $ok ? "OK: password admin123 valid\n" : "FAIL: password admin123 tidak cocok\n";
exit($ok ? 0 : 1);
