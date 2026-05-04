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
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

$successMsg = '';
$errorMsg = '';

// ── Revertir una orden cerrada a abierta ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revertir') {
    $idRevertir = (int) ($_POST['id_orden'] ?? 0);
    if ($idRevertir > 0) {
        try {
            $stmtCheck = $pdo->prepare(
                'SELECT id, estado FROM ordenes_trabajo WHERE id = :id LIMIT 1'
            );
            $stmtCheck->execute([':id' => $idRevertir]);
            $ordenCheck = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            if ($ordenCheck && $ordenCheck['estado'] === 'cerrada') {
                $pdo->beginTransaction();
                
                // Limpiar precios de todos los ítems (repuestos y tareas)
                $pdo->prepare(
                    'UPDATE ordenes_trabajo_detalle SET precio_final = NULL WHERE id_orden = :id'
                )->execute([':id' => $idRevertir]);
                
                // Revertir estado de la orden
                $pdo->prepare(
                    'UPDATE ordenes_trabajo SET estado = :estado, fecha_finalizacion = NULL, monto_total = NULL WHERE id = :id'
                )->execute([':estado' => 'abierta', ':id' => $idRevertir]);
                
                $pdo->commit();
                $successMsg = "Orden #$idRevertir revertida a estado abierto.";
            } else {
                $errorMsg = 'La orden no existe o no está cerrada.';
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMsg = 'Error al revertir: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Ver detalle de una orden ─────────────────────────────────────────────────
$verId = (int) ($_GET['ver'] ?? 0);
$orden = null;
$items = [];

if ($verId > 0) {
    $stmtO = $pdo->prepare(
        'SELECT id, cliente, vehiculo, patente, descripcion, estado,
                fecha_ot, fecha_finalizacion, monto_total
         FROM ordenes_trabajo
         WHERE id = :id AND estado = \'cerrada\'
         LIMIT 1'
    );
    $stmtO->execute([':id' => $verId]);
    $orden = $stmtO->fetch(\PDO::FETCH_ASSOC);

    if ($orden) {
        $stmtD = $pdo->prepare(
            'SELECT d.id,
                    d.cantidad,
                    d.precio_final,
                    d.descripcion_libre,
                    r.codigo,
                    r.nombre AS rep_nombre,
                    COALESCE(r.precio_costo, 0) AS precio_costo
             FROM ordenes_trabajo_detalle d
             LEFT JOIN repuestos r ON r.id_repuesto = d.id_repuesto
             WHERE d.id_orden = :id
             ORDER BY d.id ASC'
        );
        $stmtD->execute([':id' => $verId]);
        $items = $stmtD->fetchAll(\PDO::FETCH_ASSOC);
    }
}

// ── Listado de órdenes cerradas ───────────────────────────────────────────────
$filters = [
    'cliente'     => trim((string) ($_GET['f_cliente']     ?? '')),
    'patente'     => trim((string) ($_GET['f_patente']     ?? '')),
    'fecha_desde' => trim((string) ($_GET['f_fecha_desde'] ?? '')),
    'fecha_hasta' => trim((string) ($_GET['f_fecha_hasta'] ?? '')),
];
$hasActiveFilters =
    $filters['cliente'] !== '' ||
    $filters['patente'] !== '' ||
    $filters['fecha_desde'] !== '' ||
    $filters['fecha_hasta'] !== '';
$showFilters = isset($_GET['show_filters']) || $hasActiveFilters;

$where  = ["estado = 'cerrada'"];
$params = [];
if ($filters['cliente'] !== '') {
    $where[] = '(cliente LIKE :f_cliente OR patente LIKE :f_cliente2)';
    $params['f_cliente']  = '%' . $filters['cliente'] . '%';
    $params['f_cliente2'] = '%' . $filters['cliente'] . '%';
}
if ($filters['patente'] !== '') {
    $where[] = 'patente LIKE :f_patente';
    $params['f_patente'] = '%' . $filters['patente'] . '%';
}
if ($filters['fecha_desde'] !== '') {
    $where[] = 'fecha_ot >= :f_fecha_desde';
    $params['f_fecha_desde'] = $filters['fecha_desde'];
}
if ($filters['fecha_hasta'] !== '') {
    $where[] = 'fecha_ot <= :f_fecha_hasta';
    $params['f_fecha_hasta'] = $filters['fecha_hasta'];
}
$sql = 'SELECT id, cliente, vehiculo, patente, fecha_ot, fecha_finalizacion, monto_total
        FROM ordenes_trabajo
        WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY fecha_finalizacion DESC, id DESC';
$stmtL = $pdo->prepare($sql);
$stmtL->execute($params);
$lista = $stmtL->fetchAll(\PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes terminadas - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 1200px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-small { padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.86rem; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.55rem; text-align: left; font-size: 0.93rem; vertical-align: top; }
        .num { text-align: right; }
        .actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .msg-error { background: #fee2e2; color: #991b1b; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-ok { background: #dcfce7; color: #166534; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .badge-cerrada { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; background: #dcfce7; color: #166534; }
        .search-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; }
        .search-bar input { flex: 1; padding: 0.55rem; border: 1px solid #c6d2e4; border-radius: 8px; font-size: 0.93rem; box-sizing: border-box; }
        .search-bar input:focus { outline: 2px solid #0f172a; border-color: #0f172a; }
        .empty { color: #64748b; font-style: italic; padding: 1rem 0; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem 1.5rem; margin-bottom: 0.75rem; }
        .info-item label { display: block; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .info-item span { font-size: 0.97rem; font-weight: 600; }
        tfoot td { border-bottom: none; border-top: 2px solid #0f172a; padding-top: 0.75rem; font-size: 1rem; font-weight: 800; }
        .costo-ref { color: #64748b; font-size: 0.88rem; }
        .subtotal { font-weight: 700; }
        .desc-libre { font-style: italic; }
        .panel h2 { margin-top: 0; font-size: 1.1rem; margin-bottom: .75rem; }
        label { display: block; font-size: .9rem; margin-bottom: .25rem; font-weight: 600; color: #0f172a; }
        input[type=text], input[type=date], select { width: 100%; box-sizing: border-box; border: 1px solid #c6d2e4; border-radius: 8px; padding: .55rem; font-family: inherit; font-size: .93rem; }
        input[type=text]:focus, input[type=date]:focus, select:focus { outline: 2px solid #0f172a; border-color: #0f172a; }
        @media print {
            body { background: #fff; margin: 0.5rem; }
            .no-print { display: none !important; }
            .panel { border: 1px solid #999; }
        }
    </style>
</head>
<body>
<div class="container">

<?php if ($orden): ?>
    <!-- ── Vista de detalle de una orden ── -->
    <div class="top">
        <div>
            <h1>Detalle — OT #<?= (int)$orden['id'] ?></h1>
            <p><span class="badge-cerrada">Cerrada</span></p>
        </div>
        <div class="actions no-print">
            <a class="btn btn-muted" href="/ordenes_terminadas.php">Volver al listado</a>
        </div>
    </div>

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
                <label>Fecha finalización</label>
                <span><?= htmlspecialchars((string) ($orden['fecha_finalizacion'] ?? '—')) ?></span>
            </div>
            <div class="info-item">
                <label>Monto total</label>
                <span>$ <?= number_format((float)($orden['monto_total'] ?? 0), 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <div class="panel">
        <?php if (empty($items)): ?>
            <p class="empty">No hay ítems registrados para esta orden.</p>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th class="num">Cant.</th>
                <th class="num">Costo unit. ($)</th>
                <th class="num">Precio venta unit. ($)</th>
                <th class="num">Subtotal ($)</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $totalCalc = 0.0;
            foreach ($items as $i => $item):
                $esRep    = !empty($item['codigo']);
                $desc     = $esRep
                    ? htmlspecialchars((string) $item['rep_nombre'])
                    : '<span class="desc-libre">* ' . htmlspecialchars((string) $item['descripcion_libre']) . '</span>';
                $costo    = (float) $item['precio_costo'];
                $cantidad = (int)   $item['cantidad'];
                $pFinal   = $item['precio_final'] !== null ? (float) $item['precio_final'] : null;
                $subtotal = $pFinal !== null ? $pFinal * $cantidad : 0.0;
                $totalCalc += $subtotal;
            ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td><?= $desc ?></td>
                    <td class="num"><?= $cantidad ?></td>
                    <td class="num costo-ref"><?= $esRep ? '$ ' . number_format($costo, 2, ',', '.') : '—' ?></td>
                    <td class="num"><?= $pFinal !== null ? '$ ' . number_format($pFinal, 2, ',', '.') : '—' ?></td>
                    <td class="num subtotal">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;padding-right:1rem;">TOTAL</td>
                <td class="num">$ <?= number_format($totalCalc, 2, ',', '.') ?></td>
            </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>

    <div class="actions no-print" style="justify-content:flex-end; margin-top:0.25rem; margin-bottom:1rem;">
        <a class="btn btn-print" href="/ot_terminada_print.php?id=<?= (int)$orden['id'] ?>" target="_blank" rel="noopener">Imprimir</a>
    </div>

<?php else: ?>
    <!-- ── Listado de órdenes cerradas ── -->
    <div class="top">
        <div>
            <h1>Órdenes terminadas</h1>
        </div>
        <div class="actions no-print">
            <a class="btn btn-muted" href="/dashboard.php">Volver al panel</a>
            <a class="btn btn-muted" href="/ordenes_terminadas.php?show_filters=1">Buscar</a>
        </div>
    </div>

    <?php if ($showFilters): ?>
    <div class="panel">
        <h2>Filtros de búsqueda</h2>
        <form method="GET" action="/ordenes_terminadas.php" autocomplete="off">
            <input type="hidden" name="show_filters" value="1">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem">
                <div>
                    <label for="f_cliente">Cliente / Patente</label>
                    <input id="f_cliente" name="f_cliente" type="text"
                           value="<?= htmlspecialchars($filters['cliente']) ?>"
                           placeholder="Nombre o patente">
                </div>
                <div>
                    <label for="f_patente">Patente exacta</label>
                    <input id="f_patente" name="f_patente" type="text"
                           value="<?= htmlspecialchars($filters['patente']) ?>"
                           placeholder="Ej: AB123CD">
                </div>
                <div>
                    <label for="f_fecha_desde">Fecha desde</label>
                    <input id="f_fecha_desde" name="f_fecha_desde" type="date"
                           value="<?= htmlspecialchars($filters['fecha_desde']) ?>">
                </div>
                <div>
                    <label for="f_fecha_hasta">Fecha hasta</label>
                    <input id="f_fecha_hasta" name="f_fecha_hasta" type="date"
                           value="<?= htmlspecialchars($filters['fecha_hasta']) ?>">
                </div>
            </div>
            <div class="actions" style="margin-top:.9rem">
                <button class="btn btn-primary" type="submit">Buscar</button>
                <a class="btn btn-muted" href="/ordenes_terminadas.php">Limpiar</a>
                <a class="btn btn-muted" href="/ordenes_terminadas.php">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
        <div class="msg-ok"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="msg-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="panel">
        <?php if (empty($lista)): ?>
            <p class="empty">No hay órdenes terminadas<?= $buscar !== '' ? ' que coincidan con la búsqueda.' : ' aún.' ?></p>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th class="num">#</th>
                <th>Cliente</th>
                <th>Vehículo</th>
                <th>Patente</th>
                <th>Fecha OT</th>
                <th>Fecha finalización</th>
                <th class="num">Total ($)</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $ot): ?>
                <tr>
                    <td class="num"><?= (int) $ot['id'] ?></td>
                    <td><?= htmlspecialchars((string) $ot['cliente']) ?></td>
                    <td><?= htmlspecialchars((string) $ot['vehiculo']) ?></td>
                    <td><?= htmlspecialchars((string) $ot['patente']) ?></td>
                    <td><?= htmlspecialchars((string) ($ot['fecha_ot'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($ot['fecha_finalizacion'] ?? '—')) ?></td>
                    <td class="num">$ <?= number_format((float)($ot['monto_total'] ?? 0), 2, ',', '.') ?></td>
                    <td>
                        <div class="actions">
                            <a href="/ordenes_terminadas.php?ver=<?= (int)$ot['id'] ?>" class="btn btn-primary btn-small">Ver detalle</a>
                            <form method="POST" action="/ordenes_terminadas.php" style="display:inline;">
                                <input type="hidden" name="action" value="revertir">
                                <input type="hidden" name="id_orden" value="<?= (int)$ot['id'] ?>">
                                <button type="submit" class="btn btn-muted btn-small" onclick="return confirm('¿Revertir la orden #<?= (int)$ot['id'] ?> a estado abierto?')">Revertir</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>
</body>
</html>
