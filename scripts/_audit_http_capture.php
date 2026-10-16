<?php

declare(strict_types=1);

$base = rtrim(trim((string) (getenv('PWA_AUDIT_BASE_URL') ?: 'http://localhost/pwa_nailulmuna%20-%20turso')), '/');
$out = dirname(__DIR__) . '/storage/http_capture.txt';

function fetch(string $url, ?array $post = null): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($ch);
    curl_close($ch);

    return $body;
}

$lines = [];
foreach ([
    'ping' => "$base/dev_http_db_ping.php",
    'login_get' => "$base/login.php",
    'scan_portal' => "$base/presensi/scan.php?portal=1",
] as $label => $url) {
    $b = fetch($url);
    $lines[] = "=== $label === len=" . strlen($b);
    if (preg_match('/(?:Fatal error|Uncaught|SQLSTATE)[\s\S]{0,1200}/i', $b, $m)) {
        $lines[] = 'ERR: ' . trim(preg_replace('/\s+/', ' ', $m[0]) ?? '');
    } else {
        $lines[] = substr(preg_replace('/\s+/', ' ', strip_tags($b)) ?? '', 0, 300);
    }
}

$b = fetch("$base/login.php", ['username' => 'admin', 'password' => 'admin123']);
$lines[] = '=== login_post === len=' . strlen($b);
if (preg_match('/(?:Fatal error|Uncaught|SQLSTATE|near )[^\n<]{0,400}/i', $b, $m)) {
    $lines[] = 'ERR: ' . trim($m[0]);
}

file_put_contents($out, implode("\n", $lines) . "\n");
echo "written:$out\n";
