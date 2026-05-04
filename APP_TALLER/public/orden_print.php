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
        'SELECT id, cliente, vehiculo, patente, descripcion, estado, fecha_ot
         FROM ordenes_trabajo
         WHERE id = :id
         LIMIT 1'
    );
    $stmtOrden->execute([':id' => $idOrden]);
    $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        http_response_code(404);
        echo 'No se encontró la orden solicitada.';
        exit;
    }

    $stmtDet = $pdo->prepare(
        'SELECT d.id_repuesto, d.descripcion_libre, d.cantidad,
                r.codigo, r.nombre AS repuesto_nombre
         FROM ordenes_trabajo_detalle d
         LEFT JOIN repuestos r ON r.id_repuesto = d.id_repuesto
         WHERE d.id_orden = :id
         ORDER BY d.id ASC'
    );
    $stmtDet->execute([':id' => $idOrden]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit;
}

$estadoMap = ['abierta' => 'Abierta', 'en_progreso' => 'En progreso', 'cerrada' => 'Cerrada'];

$descripcionItems = array_values(array_filter(
    array_map('trim', explode('|', (string)($orden['descripcion'] ?? ''))),
    fn($v) => $v !== ''
));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Trabajo #<?= (int)$orden['id'] ?></title>
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

/* Tareas */
.section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .5rem; margin-top: 1.25rem; }
.task-list { list-style: none; padding: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.task-list li { padding: .45rem .75rem; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
.task-list li:last-child { border-bottom: none; }
.task-list li:nth-child(even) { background: #f8fafc; }

/* Pie */
.footer { margin-top: 2.5rem; border-top: 1px solid #e2e8f0; padding-top: .75rem; display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; }

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
            <label>Estado</label>
            <span><?= htmlspecialchars($estadoMap[$orden['estado']] ?? $orden['estado']) ?></span>
        </div>
    </div>

    <?php if (!empty($descripcionItems)): ?>
    <div class="section-title">Descripción / Trabajos a realizar</div>
    <ul class="task-list">
        <?php foreach ($descripcionItems as $tarea): ?>
        <li><?= htmlspecialchars($tarea) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($detalles)): ?>
    <div class="section-title">Repuestos utilizados</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Código</th>
                <th>Repuesto</th>
                <th class="r">Cant.</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 0;
        foreach ($detalles as $d):
            if (empty($d['id_repuesto'])) continue;
            $i++;
        ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars((string)($d['codigo'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string)($d['repuesto_nombre'] ?? '—')) ?></td>
                <td class="r"><?= (int)$d['cantidad'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>


    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
        <a class="btn btn-muted" href="/ordenes.php?edit=<?= (int)$orden['id'] ?>">Volver</a>
    </div>
</div>
</body>
</html>
