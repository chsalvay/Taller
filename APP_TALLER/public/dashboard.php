<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireLogin();

$user    = Auth::user();
$isAdmin = isset($user['rol']) && $user['rol'] === 'Admin';
$mostrarStock = isset($_GET['controlar_stock']);

// ── RESET TEMPORAL: borrar órdenes y reponer stock ─────────────────────────
$resetMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_ordenes' && $isAdmin) {
    try {
        $pdo = Database::connect($projectRoot);
        $pdo->beginTransaction();
        // Reponer stock de cada detalle
        $detalles = $pdo->query('SELECT id_repuesto, cantidad FROM ordenes_trabajo_detalle WHERE id_repuesto IS NOT NULL')->fetchAll(\PDO::FETCH_ASSOC);
        $stmtRep = $pdo->prepare('UPDATE repuestos SET stock_actual = stock_actual + :cant WHERE id_repuesto = :id');
        foreach ($detalles as $d) {
            $stmtRep->execute([':cant' => (int)$d['cantidad'], ':id' => (int)$d['id_repuesto']]);
        }
        $pdo->exec('DELETE FROM ordenes_trabajo_detalle');
        $pdo->exec('DELETE FROM ordenes_trabajo');
        $pdo->commit();
        $resetMsg = 'OK: ' . count($detalles) . ' líneas de detalle procesadas. Órdenes eliminadas y stock repuesto.';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $resetMsg = 'ERROR: ' . htmlspecialchars($e->getMessage());
    }
}

// Repuestos en stock mínimo o por debajo
$lowStockItems = [];
try {
    $pdo = Database::connect($projectRoot);
    $lowStockItems = $pdo->query('
        SELECT nombre, sku, stock_actual, stock_minimo
        FROM repuestos
        WHERE activo = 1 AND stock_actual <= stock_minimo
        ORDER BY stock_actual ASC
    ')->fetchAll(\PDO::FETCH_ASSOC);
} catch (Throwable) {}
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
        /* Alerta stock */
        .stock-alert { margin-top: 1.25rem; background: #fff7ed; border: 1px solid #fb923c; border-radius: 10px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .stock-alert-msg { font-weight: 700; color: #c2410c; font-size: .97rem; display: flex; align-items: center; gap: .5rem; }
        .stock-badge { background: #c2410c; color: #fff; border-radius: 999px; padding: .15rem .55rem; font-size: .82rem; font-weight: 700; }
        .btn-stock { background: #c2410c; color: #fff; padding: .55rem 1.1rem; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: .9rem; white-space: nowrap; }
        .btn-stock:hover { background: #9a3412; }
        /* Stock ok */
        .stock-ok { margin-top: 1.25rem; background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: .9rem 1.25rem; color: #166534; font-weight: 600; font-size: .95rem; }
        .btn-controlar { background: #0f172a; color: #fff; padding: .55rem 1.1rem; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: .9rem; text-align: center; }
        .btn-controlar:hover { opacity: .85; }
        /* Reset temporal */
        .reset-box { margin-top: 1.5rem; border: 2px dashed #dc2626; border-radius: 10px; padding: .9rem 1.1rem; background: #fef2f2; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .reset-box p { margin: 0; font-size: .88rem; color: #7f1d1d; font-weight: 600; }
        .btn-reset { background: #dc2626; color: #fff; border: none; padding: .55rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: .9rem; cursor: pointer; }
        .btn-reset:hover { background: #b91c1c; }
        .reset-msg { margin-top: .6rem; font-size: .88rem; padding: .5rem .8rem; border-radius: 6px; background: #f1f5f9; color: #0f172a; }
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

    <?php if ($mostrarStock): ?>
        <?php if (!empty($lowStockItems)): ?>
        <div class="stock-alert">
            <span class="stock-alert-msg">
                &#9888;&#65039; Hay <span class="stock-badge"><?= count($lowStockItems) ?></span> repuesto<?= count($lowStockItems) !== 1 ? 's' : '' ?> con stock por debajo del mínimo registrado.
            </span>
            <a class="btn-stock" href="/compras.php?f_stock_bajo=1&show_filters=1">Ver listado</a>
        </div>
        <?php else: ?>
        <div class="stock-ok">&#10003; Todos los repuestos tienen stock suficiente.</div>
        <?php endif; ?>
    <?php elseif (!empty($lowStockItems)): ?>
    <div class="stock-alert">
        <span class="stock-alert-msg">
            &#9888;&#65039; Hay <span class="stock-badge"><?= count($lowStockItems) ?></span> repuesto<?= count($lowStockItems) !== 1 ? 's' : '' ?> con stock por debajo del mínimo registrado.
        </span>
        <a class="btn-stock" href="/compras.php?f_stock_bajo=1&show_filters=1">Ver listado</a>
    </div>
    <?php endif; ?>

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

    <?php if ($isAdmin): ?>
    <!-- ⚠️ BOTÓN TEMPORAL DE RESET - QUITAR EN PRODUCCIÓN -->
    <div class="reset-box">
        <p>⚠️ <u>Solo pruebas</u>: borra todas las órdenes de trabajo y repone el stock como si no hubieran existido.</p>
        <form method="post" action="/dashboard.php" onsubmit="return confirm('¿Confirmas que querés borrar TODAS las órdenes y reponer el stock?')">
            <input type="hidden" name="action" value="reset_ordenes">
            <button type="submit" class="btn-reset">🗑 Resetear órdenes y stock</button>
        </form>
    </div>
    <?php if ($resetMsg !== ''): ?>
        <div class="reset-msg"><?= htmlspecialchars($resetMsg) ?></div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
