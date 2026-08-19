<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$user = require_api_user();
json_response(true, 'Sesi aktif.', $user);

