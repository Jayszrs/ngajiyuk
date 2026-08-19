<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once __DIR__ . '/auth.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $error): never {
    error_log('NgajiYuk: ' . $error->getMessage());
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Gangguan Sistem</title>';
    echo '<body style="font-family:Segoe UI,Arial,sans-serif;padding:40px">';
    echo '<h1>Terjadi gangguan pada sistem</h1><p>Silakan coba kembali atau hubungi Administrator.</p>';
    echo '<a href="' . e(url('index.php')) . '">Kembali ke halaman utama</a></body></html>';
    exit;
});
