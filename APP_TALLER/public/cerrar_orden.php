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
    header('Location: /ordenes.php');
    exit;
}

try {
    $pdo = Database::connect($projectRoot);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

// ── Guardar presupuesto ─────────────────────────────────────────────────────
$successMsg = '';
$errorMsg   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'terminar') {
    $montoTotal = filter_input(INPUT_POST, 'monto_total', FILTER_VALIDATE_FLOAT);
    $precios    = $_POST['precio_final'] ?? [];   // array [id_detalle => precio]

    if ($montoTotal === false || $montoTotal < 0) {
        $errorMsg = 'El monto total no es válido.';
    } else {
        try {
            $pdo->beginTransaction();

            // Actualizar cabecera de la orden
            $pdo->prepare(
                'UPDATE ordenes_trabajo
                 SET fecha_finalizacion = CURDATE(),
                     monto_total        = :monto,
                     estado             = \'cerrada\'
                 WHERE id = :id'
            )->execute([':monto' => $montoTotal, ':id' => $idOrden]);

            // Actualizar precio_final por ítem (repuestos)
            $stmtPf = $pdo->prepare(
                'UPDATE ordenes_trabajo_detalle
                 SET precio_final = :precio
                 WHERE id = :id AND id_orden = :id_orden'
            );
            foreach ($precios as $idDetalle => $precioRaw) {
                $precio = filter_var($precioRaw, FILTER_VALIDATE_FLOAT);
                if ($precio === false) $precio = null;
                $stmtPf->execute([
                    ':precio'   => $precio,
                    ':id'       => (int) $idDetalle,
                    ':id_orden' => $idOrden,
                ]);
            }

            // Insertar tareas de la descripción de la orden como líneas del detalle
            $descTexts  = $_POST['desc_texts']  ?? [];
            $descPrecos = $_POST['desc_precio']  ?? [];
            $stmtIns = $pdo->prepare(
                'INSERT INTO ordenes_trabajo_detalle (id_orden, descripcion_libre, cantidad, precio_final)
                 VALUES (:id_orden, :desc, 1, :precio)'
            );
            foreach ($descTexts as $dIdx => $descText) {
                $descText = trim((string) $descText);
                if ($descText === '') continue;
                $dPrecio = filter_var($descPrecos[$dIdx] ?? 0, FILTER_VALIDATE_FLOAT);
                if ($dPrecio === false) $dPrecio = null;
                $stmtIns->execute([':id_orden' => $idOrden, ':desc' => $descText, ':precio' => $dPrecio]);
            }

            $pdo->commit();
            $successMsg = 'Orden cerrada correctamente.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMsg = 'Error al guardar: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Cargar cabecera de la orden
$stmtOrden = $pdo->prepare(
    'SELECT o.id, o.cliente, o.vehiculo, o.patente, o.descripcion, o.estado,
            o.fecha_ot, o.fecha_finalizacion, o.monto_total
     FROM ordenes_trabajo o
     WHERE o.id = :id
     LIMIT 1'
);
$stmtOrden->execute([':id' => $idOrden]);
$orden = $stmtOrden->fetch(\PDO::FETCH_ASSOC);

if (!$orden) {
    header('Location: /ordenes.php');
    exit;
}

// Cargar ítems del detalle
$stmtDet = $pdo->prepare(
    'SELECT d.id,
            d.cantidad,
            d.precio_final,
            d.descripcion_libre,
            r.id_repuesto,
            r.sku,
            r.nombre AS rep_nombre,
            COALESCE(r.precio_costo, 0) AS precio_costo,
            COALESCE(u.abreviatura, \'\') AS unidad
     FROM ordenes_trabajo_detalle d
     LEFT JOIN repuestos r ON r.id_repuesto = d.id_repuesto
     LEFT JOIN unidades u ON u.id_unidad = r.id_unidad
     WHERE d.id_orden = :id
     ORDER BY d.id ASC'
);
$stmtDet->execute([':id' => $idOrden]);
$items = $stmtDet->fetchAll(\PDO::FETCH_ASSOC);

$ordenCerrada = ($orden['estado'] === 'cerrada');

// Tareas escritas en la descripción de la orden (visibles solo si no está cerrada,
// una vez cerradas se insertan como filas en ordenes_trabajo_detalle)
$descItems = [];
if (!$ordenCerrada && !empty($orden['descripcion'])) {
    foreach (explode('|', $orden['descripcion']) as $part) {
        $part = trim($part);
        if ($part !== '') $descItems[] = $part;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto OT #<?= $idOrden ?> - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 960px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
        .top-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; font-size: 0.95rem; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-print { background: #0369a1; color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .alert { padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 1rem; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem 1.5rem; margin-bottom: 0.5rem; }
        .info-item label { display: block; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .info-item span { font-size: 0.97rem; font-weight: 600; }
        .badge { display: inline-block; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700; }
        .badge-abierta { background: #dbeafe; color: #1d4ed8; }
        .badge-en_progreso { background: #fef9c3; color: #854d0e; }
        .badge-cerrada { background: #dcfce7; color: #166534; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.6rem 0.55rem; text-align: left; font-size: 0.93rem; vertical-align: middle; }
        th { font-weight: 700; background: #f8fafc; font-size: 0.86rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; }
        .num { text-align: right; }
        .costo-ref { color: #64748b; font-size: 0.88rem; }
        .precio-input { width: 120px; padding: 0.4rem 0.5rem; border: 1px solid #c6d2e4; border-radius: 8px; font-size: 0.93rem; text-align: right; }
        .precio-input:focus { outline: 2px solid #0f172a; border-color: #0f172a; }
        .subtotal-cell { font-weight: 700; color: #0f172a; }
        tfoot td { border-bottom: none; border-top: 2px solid #0f172a; padding-top: 0.75rem; }
        .total-label { font-size: 1.05rem; font-weight: 700; }
        .total-value { font-size: 1.3rem; font-weight: 800; color: #0f172a; }
        .desc-libre { font-style: italic; color: #334155; }
        @media print {
            body { background: #fff; margin: 0.5rem; }
            .top-actions, .no-print { display: none !important; }
            .panel { border: 1px solid #999; box-shadow: none; }
            .precio-input { border: none; background: transparent; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <div>
            <h1>Presupuesto — OT #<?= $idOrden ?></h1>
            <?php if ($ordenCerrada): ?>
                <p style="color:#16a34a;font-weight:600;">Esta orden ya fue cerrada.</p>
            <?php else: ?>
                <p>Ingresá el precio de venta por ítem para calcular el total.</p>
            <?php endif; ?>
        </div>
        <div class="top-actions no-print">
            <a class="btn btn-muted" href="/ordenes.php">Volver a órdenes</a>
            <button class="btn btn-print" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Cabecera de la orden -->
    <div class="panel">
        <div class="info-grid">
            <div class="info-item">
                <label>Cliente</label>
                <span><?= htmlspecialchars((string) $orden['cliente']) ?></span>
            </div>
            <div class="info-item">
                <label>Vehículo</label>
                <span><?= htmlspecialchars((string) $orden['vehiculo']) ?></span>
            </div>
            <div class="info-item">
                <label>Patente</label>
                <span><?= htmlspecialchars((string) $orden['patente']) ?></span>
            </div>
            <div class="info-item">
                <label>Fecha OT</label>
                <span><?= htmlspecialchars((string) ($orden['fecha_ot'] ?? '—')) ?></span>
            </div>
            <div class="info-item">
                <label>Estado</label>
                <span class="badge badge-<?= htmlspecialchars((string) $orden['estado']) ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $orden['estado']))) ?>
                </span>
            </div>
            <?php if (!empty($orden['fecha_finalizacion'])): ?>
            <div class="info-item">
                <label>Fecha finalización</label>
                <span><?= htmlspecialchars((string) $orden['fecha_finalizacion']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($orden['monto_total'] !== null): ?>
            <div class="info-item">
                <label>Monto total</label>
                <span>$ <?= number_format((float)$orden['monto_total'], 2, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($orden['descripcion'])): ?>
            <div class="info-item" style="grid-column: 1/-1;">
                <label>Descripción</label>
                <span><?= htmlspecialchars((string) $orden['descripcion']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabla de ítems -->
    <div class="panel">
        <?php if (empty($items)): ?>
            <p style="color:#64748b;font-style:italic;">Esta orden no tiene ítems cargados.</p>
        <?php else: ?>
        <form method="POST" action="/cerrar_orden.php?id=<?= $idOrden ?>" id="form-terminar">
        <input type="hidden" name="action" value="terminar">
        <input type="hidden" name="monto_total" id="input-monto-total" value="0">
        <table id="tabla-presupuesto">
            <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>SKU</th>
                <th class="num">Cant.</th>
                <th class="num">Costo unit. ($)</th>
                <th class="num">Precio venta unit. ($)</th>
                <th class="num">Subtotal ($)</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <?php
                    $esRepuesto  = !empty($item['id_repuesto']);
                    $descripcion = $esRepuesto
                        ? htmlspecialchars((string) $item['rep_nombre'])
                        : '<span class="desc-libre">* ' . htmlspecialchars((string) $item['descripcion_libre']) . '</span>';
                    $sku         = $esRepuesto ? htmlspecialchars((string) $item['sku']) : '—';
                    $costo       = $esRepuesto ? (float) $item['precio_costo'] : 0.0;
                    $cantidad    = (int) $item['cantidad'];
                ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td><?= $descripcion ?></td>
                    <td><?= $sku ?></td>
                    <td class="num"><?= $cantidad ?></td>
                    <td class="num costo-ref">
                        <?= $esRepuesto ? '$ ' . number_format($costo, 2, ',', '.') : '—' ?>
                    </td>
                    <td class="num">
                        <input
                            type="number"
                            class="precio-input precio-venta"
                            name="precio_final[<?= (int)$item['id'] ?>]"
                            min="0"
                            step="0.01"
                            value="<?= $item['precio_final'] !== null ? number_format((float)$item['precio_final'], 2, '.', '') : number_format($costo, 2, '.', '') ?>"
                            data-cantidad="<?= $cantidad ?>"
                            placeholder="0,00"
                            <?= $ordenCerrada ? 'readonly' : '' ?>
                        >
                    </td>
                    <td class="num subtotal-cell" id="subtotal-<?= $i ?>">$ 0,00</td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($descItems as $dIdx => $descText): ?>
                <tr>
                    <td class="num"><?= count($items) + $dIdx + 1 ?></td>
                    <td><?= htmlspecialchars($descText) ?></td>
                    <td>—</td>
                    <td class="num">1</td>
                    <td class="num costo-ref">—</td>
                    <td class="num">
                        <input type="hidden" name="desc_texts[]" value="<?= htmlspecialchars($descText) ?>">
                        <input
                            type="number"
                            class="precio-input precio-venta"
                            name="desc_precio[<?= $dIdx ?>]"
                            min="0"
                            step="0.01"
                            value="0"
                            data-cantidad="1"
                            placeholder="0,00"
                        >
                    </td>
                    <td class="num subtotal-cell">$ 0,00</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="6" class="total-label" style="text-align:right;padding-right:1rem;">TOTAL</td>
                <td class="num total-value" id="total-final">$ 0,00</td>
            </tr>
            </tfoot>
        </table>
        <?php if (!$ordenCerrada): ?>
        <div class="no-print" style="display:flex;justify-content:flex-end;margin-top:1.25rem;">
            <button type="submit" form="form-terminar" class="btn btn-success"
                onclick="return confirm('¿Confirmás el cierre de la orden? Esta acción no se puede deshacer.')"
            >Terminar orden</button>
        </div>
        <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    function fmt(n) {
        return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcular() {
        var total = 0;
        document.querySelectorAll('tbody tr').forEach(function (tr) {
            var input = tr.querySelector('.precio-venta');
            var subtotalCell = tr.querySelector('.subtotal-cell');
            if (!input || !subtotalCell) return;

            var precio   = parseFloat(input.value) || 0;
            var cantidad = parseInt(input.dataset.cantidad, 10) || 1;
            var subtotal = precio * cantidad;
            total += subtotal;
            subtotalCell.textContent = fmt(subtotal);
        });
        document.getElementById('total-final').textContent = fmt(total);
        var hiddenTotal = document.getElementById('input-monto-total');
        if (hiddenTotal) hiddenTotal.value = total.toFixed(2);
    }

    document.querySelectorAll('.precio-venta').forEach(function (input) {
        input.addEventListener('input', recalcular);
    });

    recalcular();
}());
</script>
</body>
</html>
