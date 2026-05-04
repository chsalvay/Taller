<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireRole('Admin');

$idPresupuesto = (int) ($_GET['id'] ?? 0);
if ($idPresupuesto <= 0) {
    http_response_code(400);
    echo 'ID de presupuesto inválido.';
    exit;
}

try {
    $pdo = Database::connect($projectRoot);

    $stmt = $pdo->prepare(
        'SELECT id_presupuesto, numero_presupuesto, fecha, cliente, monto_total
         FROM presupuesto
         WHERE id_presupuesto = :id AND activo = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $idPresupuesto]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        http_response_code(404);
        echo 'No se encontró el presupuesto.';
        exit;
    }

    $stmtDet = $pdo->prepare(
        'SELECT material, cantidad, precio_venta
         FROM presupuesto_detalle
         WHERE id_presupuesto = :id
         ORDER BY id_detalle ASC'
    );
    $stmtDet->execute([':id' => $idPresupuesto]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
    exit;
}

$montoTotal = 0.0;
foreach ($detalles as $d) {
    $montoTotal += (float)$d['precio_venta'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Presupuesto #<?= htmlspecialchars((string)$presupuesto['numero_presupuesto']) ?></title>
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

/* Datos del cliente */
.info-block { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem 2rem; margin-bottom: 1.5rem; padding: .75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
.info-block .field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
.info-block .field span { font-size: 13px; font-weight: 600; }

/* Tabla detalle */
table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
thead tr { background: #fff; color: #0f172a; border-bottom: 2px solid #0f172a; }
thead th { padding: .5rem .6rem; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
thead th.r { text-align: right; }
tbody tr { border-bottom: 1px solid #e2e8f0; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: .5rem .6rem; vertical-align: top; }
tbody td.r { text-align: right; }
tfoot tr { border-top: 2px solid #0f172a; }
tfoot td { padding: .6rem .6rem; font-weight: 800; font-size: 14px; }
tfoot td.r { text-align: right; }

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
        <div class="title">Presupuesto</div>
        <div class="meta">
            <strong>#<?= htmlspecialchars((string)$presupuesto['numero_presupuesto']) ?></strong>
            Fecha: <?= htmlspecialchars((string)($presupuesto['fecha'] ?? '—')) ?><br>
            Generado el: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="info-block">
        <div class="field">
            <label>Cliente</label>
            <span><?= htmlspecialchars((string)($presupuesto['cliente'] ?? '—')) ?></span>
        </div>
        <div class="field">
            <label>N° de presupuesto</label>
            <span><?= htmlspecialchars((string)$presupuesto['numero_presupuesto']) ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Material</th>
                <th class="r">Cant.</th>
                <th class="r">Precio venta unit.</th>
                <th class="r">Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($detalles)): ?>
            <tr><td colspan="5" style="color:#94a3b8;text-align:center;padding:1rem">Sin ítems registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($detalles as $i => $d):
                $cant      = max(1, (int)$d['cantidad']);
                $subtotal  = (float)$d['precio_venta'];
                $unitPrice = $cant > 0 ? $subtotal / $cant : $subtotal;
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars((string)$d['material']) ?></td>
                <td class="r"><?= $cant ?></td>
                <td class="r">$ <?= number_format($unitPrice, 2, ',', '.') ?></td>
                <td class="r">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;padding-right:1rem;font-size:13px;">TOTAL</td>
                <td class="r">$ <?= number_format($montoTotal, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>


    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
        <a class="btn btn-muted" href="/presupuestos.php?edit=<?= $idPresupuesto ?>">Volver</a>
    </div>
</div>
</body>
</html>
