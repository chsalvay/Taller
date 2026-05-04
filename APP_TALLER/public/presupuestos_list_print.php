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

    $presupuestos = $pdo->query(
        'SELECT p.id_presupuesto,
                p.numero_presupuesto,
                p.fecha,
                p.cliente,
                p.monto_total,
                COUNT(pd.id_detalle) AS cant_items
         FROM presupuesto p
         LEFT JOIN presupuesto_detalle pd ON pd.id_presupuesto = p.id_presupuesto
         WHERE p.activo = 1
         GROUP BY p.id_presupuesto, p.numero_presupuesto, p.fecha, p.cliente, p.monto_total
         ORDER BY p.id_presupuesto DESC'
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
<title>Listado de Presupuestos</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Segoe UI, Arial, sans-serif; background: #fff; color: #0f172a; font-size: 13px; }
.page { max-width: 920px; margin: 0 auto; padding: 2rem; }
.no-print { margin-top: 1.5rem; display: flex; gap: .75rem; justify-content: flex-end; }
.btn { display: inline-block; text-decoration: none; border: 0; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-weight: 600; font-size: 13px; }
.btn-primary { background: #0f172a; color: #fff; }
.btn-muted { background: #e2e8f0; color: #0f172a; }

.doc-header { border-bottom: 2px solid #0f172a; padding-bottom: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: flex-end; }
.doc-header .title { font-size: 22px; font-weight: 800; letter-spacing: -.5px; }
.doc-header .meta { text-align: right; font-size: 12px; color: #475569; }

table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
thead tr { background: #fff; color: #0f172a; border-bottom: 2px solid #0f172a; }
thead th { padding: .5rem .6rem; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
thead th.r { text-align: right; }
tbody tr { border-bottom: 1px solid #e2e8f0; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: .5rem .6rem; vertical-align: top; }
tbody td.r { text-align: right; }

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
        <div class="title">Lista de Presupuestos</div>
        <div class="meta">Generado el: <?= date('d/m/Y H:i') ?> — Total: <?= count($presupuestos) ?> presupuestos</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th class="r">Ítems</th>
                <th class="r">Monto total</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($presupuestos)): ?>
            <tr><td colspan="5" style="color:#94a3b8;text-align:center;padding:1rem">Sin presupuestos registrados.</td></tr>
        <?php else: ?>
            <?php foreach ($presupuestos as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($row['numero_presupuesto'] ?? $row['id_presupuesto'])) ?></td>
                <td><?= htmlspecialchars((string) ($row['fecha'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['cliente'] ?? '')) ?></td>
                <td class="r"><?= (int) ($row['cant_items'] ?? 0) ?></td>
                <td class="r">$ <?= number_format((float) ($row['monto_total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
        <a class="btn btn-muted" href="/presupuestos.php">Volver</a>
    </div>
</div>
</body>
</html>
