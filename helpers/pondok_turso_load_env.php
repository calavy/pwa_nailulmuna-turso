<?php

declare(strict_types=1);

/** Muat config/turso.remote.env (gitignored) ke putenv — tanpa log nilai. */
function pondok_turso_load_env_file(?string $path = null): bool
{
    if ($path === null) {
        $path = dirname(__DIR__) . '/config/turso.remote.env';
    }
    if (!is_file($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }

    return true;
}
