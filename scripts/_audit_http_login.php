<?php
/** HTTP login smoke via curl — audit only */
$base = rtrim(trim((string) (getenv('PWA_AUDIT_BASE_URL') ?: 'http://localhost/pwa_nailulmuna%20-%20turso')), '/');
$jar = sys_get_temp_dir() . '/pwa_audit_cookies.txt';
@unlink($jar);

function http_get(string $url, string $jar): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return [$code, $final, $body];
}

function http_post(string $url, array $fields, string $jar, bool $followRedirects = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followRedirects,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => !$followRedirects,
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if (!$followRedirects && preg_match('/^Location:\s*(.+)$/mi', $body, $m)) {
        $final = trim($m[1]);
    }
    curl_close($ch);
    return [$code, $final, $body];
}

function page_status(string $path, string $body, string $final): string {
    if (preg_match('/Fatal error|Parse error|Uncaught (?:Exception|Error)/i', $body)) return 'FATAL';
    if (str_contains($final, 'login.php') && !str_contains($path, 'login')) return 'REDIRECT_LOGIN';
    if (strlen($body) < 300) return 'WARN_EMPTY';
    return 'OK';
}

function title_of(string $body): string {
    return preg_match('/<title>([^<]+)<\/title>/i', $body, $m) ? trim($m[1]) : '';
}

$logins = [];
$loginsJson = getenv('PWA_AUDIT_LOGINS');
if (is_string($loginsJson) && trim($loginsJson) !== '') {
    $decoded = json_decode($loginsJson, true);
    if (is_array($decoded)) {
        foreach ($decoded as $entry) {
            if (!is_array($entry) || empty($entry['label']) || empty($entry['fields']['username'])) {
                continue;
            }
            $logins[] = [
                'label' => (string) $entry['label'],
                'url' => (string) ($entry['url'] ?? "$base/login.php"),
                'fields' => (array) $entry['fields'],
            ];
        }
    }
}
if ($logins === []) {
    $adminPass = (string) (getenv('PWA_AUDIT_ADMIN_PASS') ?: 'admin123');
    $logins[] = [
        'label' => 'admin',
        'url' => "$base/login.php",
        'fields' => ['username' => 'admin', 'password' => $adminPass],
    ];
    if (getenv('PWA_AUDIT_LEGACY_LOGINS') === '1') {
        $logins[] = [
            'label' => 'pembimbing',
            'url' => "$base/login.php",
            'fields' => ['username' => 'PMB001', 'password' => 'abc123'],
        ];
        $logins[] = [
            'label' => 'pengurus',
            'url' => "$base/login.php",
            'fields' => ['username' => 'slamet', 'password' => 'abc123'],
        ];
    }
}

$pages = [
    '/pembimbing/dashboard.php','/yayasan/keaktifan.php','/yayasan/operasional.php',
    '/presensi/scan.php','/presensi/scan.php?portal=1','/rekap/keaktifan_hari.php','/dashboard.php',
];

echo "HTTP_LOGIN_SMOKE\n";
[$pc, , $pb] = http_get("$base/dev_http_db_ping.php", $jar);
if ($pc === 200 && preg_match('/pondok_db_driver=(\w+)/', $pb, $dm)) {
    echo 'ENV|pondok_db_driver=' . $dm[1] . "\n";
} elseif ($pc === 404) {
    echo "ENV|dev_http_db_ping|missing_or_not_local\n";
} else {
    echo "ENV|dev_http_db_ping|code=$pc\n";
}
foreach ($logins as $login) {
    @unlink($jar);
    http_get($login['url'], $jar);
    [$c,$f,$b] = http_post($login['url'], $login['fields'], $jar, false);
    $loginOk = ($c >= 301 && $c <= 303 && preg_match('#/(dashboard|pengasuh/)#i', $f))
        || !str_contains($f, 'login.php')
        || str_contains($f, 'dashboard')
        || str_contains($f, 'pengasuh/')
        || str_contains($b, 'Dashboard');
    echo "LOGIN|{$login['label']}|code=$c|final=$f|ok=" . ($loginOk ? '1' : '0');
    if (!$loginOk) {
        if (str_contains($b, 'Terlalu banyak percobaan login')) {
            echo '|hint=rate_limit';
        } elseif (str_contains($b, 'Identitas atau password salah')) {
            echo '|hint=bad_credentials_or_wrong_db_driver';
        } elseif (preg_match('/Fatal error|Parse error|near "EXISTS"/i', $b)) {
            echo '|hint=php_or_sql_error';
        }
    }
    echo "\n";
    foreach ($pages as $p) {
        [$c2,$f2,$b2] = http_get($base.$p, $jar);
        $st = page_status($p, $b2, $f2);
        echo "PAGE|{$login['label']}|$p|$st|code=$c2|final=$f2|title=" . title_of($b2) . "\n";
    }
}

// Portal scan public
[$c,$f,$b] = http_get("$base/presensi/scan.php?portal=1", $jar);
echo "PUBLIC|portal_scan|" . page_status('portal', $b, $f) . "|title=" . title_of($b) . "\n";
if (!preg_match('/presensi-scan-feedback|presensi-scan-timer|presensi-scan-banner/', $b)) {
    echo "PUBLIC|portal_scan|WARN|missing scan assets\n";
}
if (!preg_match('/data-type=/', $b) && !preg_match('/presensi-scan-result/', $b)) {
    echo "PUBLIC|portal_scan|INFO|no result overlay element on GET (expected)\n";
}
