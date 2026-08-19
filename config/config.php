<?php
declare(strict_types=1);

define('APP_NAME', 'NgajiYuk');
define('APP_SUBTITLE', 'SD Islam Labschool Bani Saleh');
define('BASE_URL', '/ngajiyuk');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);
define('ACTIVE_ACADEMIC_YEAR', '2026/2027');

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ngajiyuk_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

