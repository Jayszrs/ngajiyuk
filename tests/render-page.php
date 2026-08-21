<?php

declare(strict_types=1);

/*
 * Helper audit untuk merender satu halaman dengan sesi pengguna nyata.
 *
 * Pemakaian:
 * php tests/render-page.php admin admin admin/dashboard.php output.html
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$role = trim((string) ($argv[1] ?? ''));
$username = trim((string) ($argv[2] ?? ''));
$relativePage = ltrim(str_replace('\\', '/', trim((string) ($argv[3] ?? ''))), '/');
$outputFile = trim((string) ($argv[4] ?? ''));
$queryString = trim((string) ($argv[5] ?? ''));
$renderMode = trim((string) ($argv[6] ?? ''));

if ($queryString === '-') {
    $queryString = '';
}

if ($role === '' || $username === '' || $relativePage === '') {
    fwrite(STDERR, "Argumen role, username, dan halaman wajib diisi.\n");
    exit(2);
}

$root = dirname(__DIR__);
$page = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePage);

if (!is_file($page) || realpath($page) === false || !str_starts_with((string) realpath($page), (string) realpath($root))) {
    fwrite(STDERR, "Halaman tidak valid: {$relativePage}\n");
    exit(2);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/ngajiyuk/' . $relativePage;
$_SERVER['REQUEST_URI'] = '/ngajiyuk/' . $relativePage . ($queryString !== '' ? '?' . $queryString : '');
$_SERVER['HTTP_HOST'] = 'localhost';

if ($queryString !== '') {
    parse_str($queryString, $_GET);
}

require $root . '/config/bootstrap.php';

$statement = db()->prepare('SELECT id, role FROM users WHERE username = ? AND role = ? LIMIT 1');
$statement->execute([$username, $role]);
$account = $statement->fetch();

if (!$account) {
    fwrite(STDERR, "Akun pengujian {$username} ({$role}) tidak ditemukan.\n");
    exit(2);
}

$_SESSION['user_id'] = (string) $account['id'];
$_SESSION['last_activity'] = time();

ob_start();
require $page;
$html = (string) ob_get_clean();

if ($html === '' || str_contains($html, 'Terjadi gangguan pada sistem')) {
    fwrite(STDERR, "Render gagal: {$relativePage}\n");
    exit(1);
}

if ($renderMode === 'suppress-guide') {
    $storageRole = json_encode($role, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $guideScript = '<script>sessionStorage.setItem("ngajiyuk-guide-seen-"+' . $storageRole . ',"1");</script>';
    $html = str_replace('</head>', $guideScript . '</head>', $html);
}

if ($outputFile !== '') {
    $directory = dirname($outputFile);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fwrite(STDERR, "Folder output tidak dapat dibuat.\n");
        exit(1);
    }
    file_put_contents($outputFile, $html);
}

fwrite(STDOUT, 'OK ' . $relativePage . ' (' . strlen($html) . " bytes)\n");
