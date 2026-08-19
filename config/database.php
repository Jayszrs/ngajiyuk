<?php
declare(strict_types=1);

define('DB_HOST', getenv('NGAJIYUK_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('NGAJIYUK_DB_PORT') ?: '3306');
define('DB_NAME', getenv('NGAJIYUK_DB_NAME') ?: 'ngajiyuk');
define('DB_USER', getenv('NGAJIYUK_DB_USER') ?: 'root');
define('DB_PASS', getenv('NGAJIYUK_DB_PASS') ?: '');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);

    return $pdo;
}

