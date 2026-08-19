<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
logout_user();
redirect('login.php');

