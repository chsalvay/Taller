<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireRole('Admin');

$error   = '';
$success = '';

try {
    $pdo = Database::connect($projectRoot);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

// ── INCREMENTAR STOCK ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'incrementar') {
    $idRepuesto = (int) ($_POST['id_repuesto'] ?? 0);
    $cantidad   = (int) ($_POST['cantidad'] ?? 0);

    if ($idRepuesto <= 0 || $cantidad <= 0) {
        $error = 'Datos inválidos: repuesto o cantidad no válidos.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE repuestos
                 SET stock_actual = stock_actual + :cantidad
                 WHERE id_repuesto = :id_repuesto AND activo = 1'
            );
            $stmt->execute(['cantidad' => $cantidad, 'id_repuesto' => $idRepuesto]);

            if ($stmt->rowCount() === 0) {
                $error = 'No se encontró el repuesto o está inactivo.';
            } else {
                $success = 'Stock incrementado en ' . $cantidad . ' unidad' . ($cantidad !== 1 ? 'es' : '') . '.';
            }
        } catch (Throwable $e) {
            $error = 'Error al actualizar el stock: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── CARGAR REPUESTOS CON STOCK BAJO ───────────────────────────────────────
$items = [];
try {
    $items = $pdo->query(
        'SELECT r.id_repuesto,
                r.sku,
                r.nombre,
                r.stock_actual,
                r.stock_minimo,
                COALESCE(ma.nombre_marca, \'\') AS marca,
                COALESCE(cat.nombre_categoria, \'\') AS categoria
         FROM repuestos r
         LEFT JOIN marcas ma ON ma.id_marca = r.id_marca
         LEFT JOIN categorias cat ON cat.id_categoria = r.id_categoria
         WHERE r.activo = 1 AND r.stock_actual <= r.stock_minimo
         ORDER BY (r.stock_minimo - r.stock_actual) DESC, r.nombre ASC'
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'No se pudo cargar el listado: ' . htmlspecialchars($e->getMessage());
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repuestos con stock bajo - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 1100px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .btn-warning { background: #c2410c; color: #fff; }
        .btn-small { padding: 0.45rem 0.7rem; border-radius: 8px; font-size: 0.86rem; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
        .msg-error { background: #fee2e2; color: #991b1b; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-ok { background: #dcfce7; color: #166534; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .empty { color: #64748b; font-style: italic; padding: 1.5rem 0; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.6rem 0.55rem; text-align: left; font-size: 0.93rem; vertical-align: middle; }
        th { font-weight: 700; background: #f8fafc; }
        .num { text-align: right; }
        .tag-danger { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; background: #fee2e2; color: #991b1b; }
        .tag-warn { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; background: #fff7ed; color: #9a3412; }
        .inc-form { display: flex; align-items: center; gap: 0.4rem; }
        .inc-form input[type="number"] { width: 65px; padding: 0.35rem 0.45rem; border: 1px solid #c6d2e4; border-radius: 8px; font-size: 0.9rem; text-align: right; }
        .inc-form button { background: #0f172a; color: #fff; border: none; border-radius: 8px; padding: 0.38rem 0.75rem; font-weight: 700; font-size: 0.88rem; cursor: pointer; white-space: nowrap; }
        .inc-form button:hover { background: #1e293b; }
        .diff-neg { color: #b91c1c; font-weight: 700; }
        .diff-zero { color: #9a3412; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <div>
            <h1>Repuestos con stock bajo</h1>
            <p>Repuestos cuyo stock actual es igual o menor al mínimo registrado.</p>
        </div>
        <div class="actions">
            <a class="btn btn-muted" href="/dashboard.php">Volver al panel</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="msg-ok"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="panel">
        <?php if (empty($items)): ?>
            <div class="empty">&#10003; No hay repuestos con stock bajo en este momento.</div>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th class="num">ID</th>
                <th>SKU</th>
                <th>Nombre</th>
                <th>Marca</th>
                <th>Categoría</th>
                <th class="num">Stock actual</th>
                <th class="num">Stock mínimo</th>
                <th class="num">Diferencia</th>
                <th>Incrementar stock</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <?php $diff = (int) $row['stock_actual'] - (int) $row['stock_minimo']; ?>
                <tr>
                    <td class="num"><?= (int) $row['id_repuesto'] ?></td>
                    <td><?= htmlspecialchars((string) $row['sku']) ?></td>
                    <td><?= htmlspecialchars((string) $row['nombre']) ?></td>
                    <td><?= htmlspecialchars((string) $row['marca']) ?></td>
                    <td><?= htmlspecialchars((string) $row['categoria']) ?></td>
                    <td class="num">
                        <span class="<?= $diff < 0 ? 'tag-danger' : 'tag-warn' ?>">
                            <?= (int) $row['stock_actual'] ?>
                        </span>
                    </td>
                    <td class="num"><?= (int) $row['stock_minimo'] ?></td>
                    <td class="num">
                        <span class="<?= $diff < 0 ? 'diff-neg' : 'diff-zero' ?>">
                            <?= $diff ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" action="/stock_bajo.php" class="inc-form">
                            <input type="hidden" name="action" value="incrementar">
                            <input type="hidden" name="id_repuesto" value="<?= (int) $row['id_repuesto'] ?>">
                            <input type="number" name="cantidad" min="1" max="9999" value="1" required>
                            <button type="submit">+ Agregar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
