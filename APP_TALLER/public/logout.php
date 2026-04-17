<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';

use App\Src\Auth;

Auth::startSession();
Auth::logout();

header('Location: /index.php');
exit;
