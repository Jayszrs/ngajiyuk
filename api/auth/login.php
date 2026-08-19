<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf();
$data = request_data();
[$success, $message] = login_user((string) ($data['identifier'] ?? ''), (string) ($data['password'] ?? ''));
json_response($success, $message, $success ? current_user(true) : null, $success ? 200 : 401);

