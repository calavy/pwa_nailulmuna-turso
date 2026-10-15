<?php

declare(strict_types=1);

/**
 * Batasi endpoint diagnostik (audit skema, cek server, dll.) — bukan untuk publik.
 */
function pondok_dev_endpoint_guard(): void
{
    require_once __DIR__ . '/app_path.php';

    if (function_exists('app_is_local_dev') && app_is_local_dev()) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        return;
    }

    require_once __DIR__ . '/../config/session.php';

    $allowed = false;
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (!function_exists('is_super_admin')) {
            require_once __DIR__ . '/../includes/auth.php';
        }
        if (function_exists('is_super_admin') && is_super_admin()) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
}
