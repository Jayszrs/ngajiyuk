<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once __DIR__ . '/auth.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $error): void {
    error_log('NgajiYuk: ' . $error->getMessage());

    http_response_code(500);

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!doctype html>';
    echo '<html lang="id">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Gangguan Sistem</title>';
    echo '</head>';
    echo '<body style="font-family:Segoe UI,Arial,sans-serif;padding:40px">';
    echo '<h1>Terjadi gangguan pada sistem</h1>';
    echo '<p>Silakan coba kembali atau hubungi Administrator.</p>';
    echo '<a href="' . e(url('index.php')) . '">Kembali ke halaman utama</a>';
    echo '</body>';
    echo '</html>';

    exit;
});