<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';

use App\Src\Auth;

Auth::startSession();
Auth::requireLogin();

$user = Auth::user();
$isAdmin = isset($user['rol']) && $user['rol'] === 'Admin';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 2rem; background: #f5f7fb; }
        .card { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 14px; border: 1px solid #dfe5ef; padding: 1.5rem; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .btn { display: inline-block; padding: 0.75rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .btn-logout { background: #e11d48; color: #fff; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
        .module { background: #0f172a; color: #fff; text-align: center; padding: 1.4rem 1rem; border-radius: 12px; text-decoration: none; }
        .module:hover { opacity: 0.9; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; background: #dbeafe; color: #1e3a8a; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="top">
        <div>
            <h1>Panel principal</h1>
            <p>Usuario: <strong><?= htmlspecialchars((string) ($user['username'] ?? '')) ?></strong> <span class="badge">Rol: <?= htmlspecialchars((string) ($user['rol'] ?? '')) ?></span></p>
        </div>
        <a class="btn btn-logout" href="/logout.php">Cerrar sesion</a>
    </div>

    <?php if ($isAdmin): ?>
        <div class="grid">
            <a class="module" href="/compras.php">Repuestos</a>
            <a class="module" href="/clientes.php">Clientes</a>
            <a class="module" href="/ordenes.php">Ordenes de Trabajo</a>
        </div>
    <?php else: ?>
        <p>Tu rol no tiene modulos asignados.</p>
    <?php endif; ?>
</div>
</body>
</html>
