<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireRole('Admin');

try {
    $pdo = Database::connect($projectRoot);

    $repuestos = $pdo->query(
        'SELECT r.id_repuesto, r.codigo, r.nombre,
                r.precio_costo, r.precio_venta, r.stock_actual,
                ma.nombre_marca AS marca,
                vm.nombre_marca_v AS vehiculo_marca,
                vmo.nombre_modelo AS vehiculo_modelo,
                c.nombre_categoria AS categoria,
                CONCAT(u.nombre_unidad, " (", u.abreviatura, ")") AS unidad,
                p.razon_social AS proveedor
         FROM repuestos r
         LEFT JOIN marcas ma ON ma.id_marca = r.id_marca
         LEFT JOIN categorias c ON c.id_categoria = r.id_categoria
         LEFT JOIN unidades u ON u.id_unidad = r.id_unidad
         LEFT JOIN proveedores p ON p.id_proveedor = r.id_proveedor
         LEFT JOIN compatibilidad_vehiculos cv ON cv.id_repuesto = r.id_repuesto AND cv.activo = 1
         LEFT JOIN vehiculos_modelos vmo ON vmo.id_modelo = cv.id_modelo
         LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = vmo.id_vehiculo_marca
         WHERE r.activo = 1
         ORDER BY r.id_repuesto DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Listado de Repuestos</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Segoe UI, Arial, sans-serif; background: #fff; color: #0f172a; font-size: 13px; }
.page { max-width: 1000px; margin: 0 auto; padding: 2rem; }
.no-print { margin-top: 1.5rem; display: flex; gap: .75rem; justify-content: flex-end; }
.btn { display: inline-block; text-decoration: none; border: 0; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-weight: 600; font-size: 13px; }
.btn-primary { background: #0f172a; color: #fff; }
.btn-muted { background: #e2e8f0; color: #0f172a; }

/* Encabezado */
.doc-header { border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: flex-end; }
.doc-header .title { font-size: 22px; font-weight: 800; letter-spacing: -.5px; }
.doc-header .meta { text-align: right; font-size: 12px; color: #475569; }

/* Tabla */
table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
thead tr { background: #fff; color: #0f172a; border-bottom: 2px solid #0f172a; }
thead th { padding: .45rem .5rem; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
thead th.r { text-align: right; }
tbody tr { border-bottom: 1px solid #e2e8f0; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: .45rem .5rem; vertical-align: top; font-size: 12px; }
tbody td.r { text-align: right; }

@media print {
    .no-print { display: none !important; }
    body { font-size: 11px; }
    .page { padding: 1cm; max-width: 100%; }
}
</style>
</head>
<body>
<div class="page">

    <div class="doc-header">
        <div class="title">Listado de Repuestos</div>
        <div class="meta">Generado el: <?= date('d/m/Y H:i') ?> — Total: <?= count($repuestos) ?> repuestos</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Marca</th>
                <th>Vehículo</th>
                <th>Categoría</th>
                <th>Unidad</th>
                <th>Proveedor</th>
                <th class="r">Precio costo</th>
                <th class="r">Precio venta</th>
                <th class="r">Stock</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($repuestos)): ?>
            <tr><td colspan="11" style="color:#94a3b8;text-align:center;padding:1rem">Sin repuestos registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($repuestos as $r): ?>
            <tr>
                <td><?= (int)$r['id_repuesto'] ?></td>
                <td><?= htmlspecialchars((string)$r['codigo']) ?></td>
                <td><?= htmlspecialchars((string)$r['nombre']) ?></td>
                <td><?= htmlspecialchars((string)($r['marca'] ?? '—')) ?></td>
                <td><?= htmlspecialchars(trim((string)($r['vehiculo_marca'] ?? '') . ' ' . (string)($r['vehiculo_modelo'] ?? ''))) ?: '—' ?></td>
                <td><?= htmlspecialchars((string)($r['categoria'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string)($r['unidad'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string)($r['proveedor'] ?? '—')) ?></td>
                <td class="r">$ <?= number_format((float)$r['precio_costo'], 2, ',', '.') ?></td>
                <td class="r">$ <?= number_format((float)$r['precio_venta'], 2, ',', '.') ?></td>
                <td class="r"><?= (int)$r['stock_actual'] ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
        <a class="btn btn-muted" href="/compras.php">Volver</a>
    </div>
</div>
</body>
</html>
