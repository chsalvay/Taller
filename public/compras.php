<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';

use App\Src\Auth;

Auth::startSession();
Auth::requireRole('Admin');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - APP_TALLER</title>
    <style>body{font-family:Segoe UI,Arial,sans-serif;margin:2rem}a{display:inline-block;margin-top:1rem}</style>
</head>
<body>
<h1>Compras</h1>
<p>Modulo de Compras.</p>
<a href="/dashboard.php">Volver al panel</a>
</body>
</html>
