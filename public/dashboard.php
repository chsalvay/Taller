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
        .menu-bar { margin-top: 1rem; display: flex; align-items: center; gap: 1rem; }
        .menu-item { position: relative; }
        .menu-trigger {
            border: 0;
            background: transparent;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            padding: 0.35rem 0.45rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .menu-trigger:hover,
        .menu-trigger:focus-visible { background: #e2e8f0; outline: none; }
        .caret { font-size: 0.85rem; color: #334155; }
        .dropdown {
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + 0.5rem);
            width: 260px;
            background: #0f172a;
            border-radius: 12px;
            border: 1px solid #1e293b;
            box-shadow: 0 14px 28px rgba(2, 6, 23, 0.35);
            padding: 0.55rem;
            z-index: 15;
        }
        .menu-item:hover .dropdown,
        .menu-item:focus-within .dropdown { display: block; }
        .dropdown::before {
            content: '';
            position: absolute;
            top: -7px;
            left: 22px;
            width: 12px;
            height: 12px;
            background: #0f172a;
            border-left: 1px solid #1e293b;
            border-top: 1px solid #1e293b;
            transform: rotate(45deg);
        }
        .dropdown a {
            display: block;
            text-decoration: none;
            color: #e2e8f0;
            padding: 0.7rem 0.75rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .dropdown a:hover,
        .dropdown a:focus-visible {
            background: #1e293b;
            color: #fff;
            outline: none;
        }
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
        <nav class="menu-bar" aria-label="Menu principal">
            <div class="menu-item">
                <button class="menu-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                    Catalogo
                    <span class="caret">&#9662;</span>
                </button>
                <div class="dropdown" role="menu" aria-label="Submenu catalogo">
                    <a role="menuitem" href="/catalogos.php?seccion=marca#marcas-panel">Marca Repuesto</a>
                    <a role="menuitem" href="/catalogos.php?seccion=vehiculo_marca#vehiculos-marcas-panel">Vehículo Marca</a>
                    <a role="menuitem" href="/catalogos.php?seccion=vehiculo_modelo#vehiculos-modelos-panel">Vehículo Modelo</a>
                    <a role="menuitem" href="/catalogos.php?seccion=motorizacion#motorizaciones-panel">Motorización</a>
                    <a role="menuitem" href="/catalogos.php?seccion=categoria#categorias-panel">Categoría</a>
                    <a role="menuitem" href="/catalogos.php?seccion=proveedor#proveedores-panel">Proveedor</a>
                </div>
            </div>
        </nav>

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
