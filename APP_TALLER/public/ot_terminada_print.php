<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireRole('Admin');

$idOrden = (int) ($_GET['id'] ?? 0);
if ($idOrden <= 0) {
    http_response_code(400);
    echo 'ID de orden inválido.';
    exit;
}

try {
    $pdo = Database::connect($projectRoot);

    $stmtOrden = $pdo->prepare(
        'SELECT id, cliente, vehiculo, patente, fecha_ot, fecha_finalizacion, monto_total
         FROM ordenes_trabajo
         WHERE id = :id AND estado = \'cerrada\'
         LIMIT 1'
    );
    $stmtOrden->execute([':id' => $idOrden]);
    $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        http_response_code(404);
        echo 'No se encontró la orden.';
        exit;
    }

    $stmtDet = $pdo->prepare(
        'SELECT d.cantidad, d.precio_final, d.descripcion_libre,
                r.codigo, r.nombre AS rep_nombre,
                COALESCE(r.precio_costo, 0) AS precio_costo
         FROM ordenes_trabajo_detalle d
         LEFT JOIN repuestos r ON r.id_repuesto = d.id_repuesto
         WHERE d.id_orden = :id
         ORDER BY d.id ASC'
    );
    $stmtDet->execute([':id' => $idOrden]);
    $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit;
}

$totalCalc = 0.0;
foreach ($items as $item) {
    $totalCalc += (float)($item['precio_final'] ?? 0) * (int)$item['cantidad'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>OT #<?= (int)$orden['id'] ?> — Cerrada</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Segoe UI, Arial, sans-serif; background: #fff; color: #0f172a; font-size: 13px; }
.page { max-width: 800px; margin: 0 auto; padding: 2rem; }
.no-print { margin-top: 1.5rem; display: flex; gap: .75rem; justify-content: flex-end; }
.btn { display: inline-block; text-decoration: none; border: 0; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-weight: 600; font-size: 13px; }
.btn-primary { background: #0f172a; color: #fff; }
.btn-muted { background: #e2e8f0; color: #0f172a; }

/* Encabezado */
.doc-header { border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: flex-end; }
.doc-header .title { font-size: 22px; font-weight: 800; letter-spacing: -.5px; }
.doc-header .meta { text-align: right; font-size: 12px; color: #475569; }
.doc-header .meta strong { display: block; font-size: 15px; color: #0f172a; }

/* Datos */
.info-block { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem 2rem; margin-bottom: 1.5rem; padding: .75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
.info-block .field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
.info-block .field span { font-size: 13px; font-weight: 600; }

/* Tabla */
table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
thead tr { background: #fff; color: #0f172a; border-bottom: 2px solid #0f172a; }
thead th { padding: .5rem .6rem; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
thead th.r { text-align: right; }
tbody tr { border-bottom: 1px solid #e2e8f0; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: .5rem .6rem; vertical-align: top; }
tbody td.r { text-align: right; }
tbody td.muted { color: #94a3b8; font-size: .88em; text-align: right; }
tfoot tr { border-top: 2px solid #0f172a; }
tfoot td { padding: .6rem .6rem; font-weight: 800; font-size: 14px; }
tfoot td.r { text-align: right; }
.italic { font-style: italic; color: #475569; }

@media print {
    .no-print { display: none !important; }
    body { font-size: 12px; }
    .page { padding: 1cm; max-width: 100%; }
}
</style>
</head>
<body>
<div class="page">

    <div class="doc-header">
        <div class="title">Orden de Trabajo</div>
        <div class="meta">
            <strong>OT #<?= (int)$orden['id'] ?></strong>
            Fecha OT: <?= htmlspecialchars((string)($orden['fecha_ot'] ?? '—')) ?><br>
            Finalización: <?= htmlspecialchars((string)($orden['fecha_finalizacion'] ?? '—')) ?><br>
            Generado el: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="info-block">
        <div class="field">
            <label>Cliente</label>
            <span><?= htmlspecialchars((string)($orden['cliente'] ?? '—')) ?></span>
        </div>
        <div class="field">
            <label>Vehículo</label>
            <span><?= htmlspecialchars((string)($orden['vehiculo'] ?? '—')) ?></span>
        </div>
        <div class="field">
            <label>Patente</label>
            <span><?= htmlspecialchars((string)($orden['patente'] ?? '—')) ?></span>
        </div>
        <div class="field">
            <label>Monto total</label>
            <span>$ <?= number_format((float)($orden['monto_total'] ?? $totalCalc), 2, ',', '.') ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th class="r">Cant.</th>
                <th class="r">Costo unit. ($)</th>
                <th class="r">Precio venta unit. ($)</th>
                <th class="r">Subtotal ($)</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" style="color:#94a3b8;text-align:center;padding:1rem">Sin ítems registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $i => $item):
                $esRep    = !empty($item['codigo']);
                $desc     = $esRep
                    ? htmlspecialchars((string)$item['rep_nombre'])
                    : '<span class="italic">* ' . htmlspecialchars((string)$item['descripcion_libre']) . '</span>';
                $cantidad = (int)$item['cantidad'];
                $costo    = (float)$item['precio_costo'];
                $pFinal   = $item['precio_final'] !== null ? (float)$item['precio_final'] : null;
                $subtotal = $pFinal !== null ? $pFinal * $cantidad : 0.0;
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= $desc ?></td>
                <td class="r"><?= $cantidad ?></td>
                <td class="muted"><?= $esRep ? '$ ' . number_format($costo, 2, ',', '.') : '—' ?></td>
                <td class="r"><?= $pFinal !== null ? '$ ' . number_format($pFinal, 2, ',', '.') : '—' ?></td>
                <td class="r">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;padding-right:1rem;font-size:13px;">TOTAL</td>
                <td class="r">$ <?= number_format($totalCalc, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
        <a class="btn btn-muted" href="/ordenes_terminadas.php?ver=<?= (int)$orden['id'] ?>">Volver</a>
    </div>
</div>
</body>
</html>
