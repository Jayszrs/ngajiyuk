<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function (Throwable $error): never {
    error_log('NgajiYuk API: ' . $error->getMessage());
    $message = $error instanceof RuntimeException ? $error->getMessage() : 'Terjadi kesalahan pada server.';
    json_response(false, $message, null, $error instanceof RuntimeException ? 422 : 500);
});

function require_api_user(string ...$roles): array
{
    $user = current_user();
    if (!$user) json_response(false, 'Sesi login tidak ditemukan.', null, 401);
    if ($roles && !in_array($user['role'], $roles, true)) json_response(false, 'Akses tidak diizinkan.', null, 403);
    return $user;
}

