<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| APPLICATION
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'Catatan Mengaji Digital');
define('APP_SUBTITLE', 'SD Islam Labschool Bani Saleh');


/*
|--------------------------------------------------------------------------
| ROOT PATH
|--------------------------------------------------------------------------
*/

define('ROOT_PATH', dirname(__DIR__));


/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
|
| BASE_URL akan otomatis mengikuti nama folder project di htdocs.
|
| Contoh:
|
| C:\xampp\htdocs\ngajiyuk
| -> /ngajiyuk
|
| C:\xampp\htdocs\ngajiyuk-php
| -> /ngajiyuk-php
|
| Jika project menjadi DocumentRoot VirtualHost:
| -> ''
|
| Bisa juga dipaksa menggunakan environment variable:
|
| NGAJIYUK_BASE_URL=/ngajiyuk
|
*/

$configuredBaseUrl = getenv('NGAJIYUK_BASE_URL');

if (
    $configuredBaseUrl !== false
    && trim($configuredBaseUrl) !== ''
) {
    $baseUrl = '/' . trim(
        str_replace('\\', '/', trim($configuredBaseUrl)),
        '/'
    );

    if ($baseUrl === '/') {
        $baseUrl = '';
    }
} else {
    $baseUrl = '/ngajiyuk';

    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

    if ($documentRoot !== '') {
        $realDocumentRoot = realpath($documentRoot);
        $realProjectRoot = realpath(ROOT_PATH);

        if (
            $realDocumentRoot !== false
            && $realProjectRoot !== false
        ) {
            $normalizedDocumentRoot = rtrim(
                str_replace('\\', '/', $realDocumentRoot),
                '/'
            );

            $normalizedProjectRoot = rtrim(
                str_replace('\\', '/', $realProjectRoot),
                '/'
            );

            /*
             * Windows tidak case-sensitive, jadi perbandingan
             * path dibuat case-insensitive agar aman di XAMPP.
             */
            $documentRootLower = strtolower($normalizedDocumentRoot);
            $projectRootLower = strtolower($normalizedProjectRoot);

            if ($projectRootLower === $documentRootLower) {
                $baseUrl = '';
            } elseif (
                strpos(
                    $projectRootLower,
                    $documentRootLower . '/'
                ) === 0
            ) {
                $relativePath = substr(
                    $normalizedProjectRoot,
                    strlen($normalizedDocumentRoot)
                );

                $baseUrl = '/' . trim($relativePath, '/');
            }
        }
    }
}

define('BASE_URL', rtrim($baseUrl, '/'));


/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

define(
    'UPLOAD_PATH',
    ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads'
);

define(
    'MAX_UPLOAD_SIZE',
    2 * 1024 * 1024
);


/* Fallback saja. Runtime membaca academic_settings melalui helper. */
define('ACTIVE_ACADEMIC_YEAR', '2026/2027');


/*
|--------------------------------------------------------------------------
| TIMEZONE
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Jakarta');


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ngajiyuk_session');

    $sessionCookiePath = BASE_URL !== ''
        ? BASE_URL . '/'
        : '/';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $sessionCookiePath,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
