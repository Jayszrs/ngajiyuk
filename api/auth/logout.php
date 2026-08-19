<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf(); logout_user(); json_response(true, 'Logout berhasil.');

