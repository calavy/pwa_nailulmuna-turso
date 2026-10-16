<?php

declare(strict_types=1);

$base = rtrim(trim((string) (getenv('PWA_AUDIT_BASE_URL') ?: 'http://localhost/pwa_nailulmuna%20-%20turso')), '/');
$jar = sys_get_temp_dir() . '/pwa_turso_login_jar.txt';
@unlink($jar);

$ch = curl_init("$base/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_COOKIEFILE => $jar,
]);
curl_exec($ch);
curl_close($ch);

$ch = curl_init("$base/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['username' => 'admin', 'password' => 'admin123']),
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_COOKIEFILE => $jar,
    CURLOPT_HEADER => true,
]);
$raw = (string) curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST code=$code\n";
if (preg_match('/^Location:\s*(.+)$/mi', $raw, $m)) {
    echo 'Location: ' . trim($m[1]) . "\n";
}
if (str_contains($raw, 'Identitas atau password salah')) {
    echo "flash=bad_credentials\n";
}
if (str_contains($raw, 'Terlalu banyak percobaan')) {
    echo "flash=rate_limit\n";
}

$ch = curl_init("$base/pengasuh/dashboard.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR => $jar,
    CURLOPT_COOKIEFILE => $jar,
]);
$dash = (string) curl_exec($ch);
$final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
echo 'dashboard_final=' . $final . "\n";
echo 'dashboard_ok=' . (str_contains($final, 'login.php') ? '0' : '1') . "\n";
