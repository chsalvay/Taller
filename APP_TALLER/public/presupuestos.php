<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireRole('Admin');

$error = '';
$success = '';
$showForm = false;
$formMode = 'new';

if (isset($_SESSION['flash_success'])) {
    $success = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

try {
    $pdo = Database::connect($projectRoot);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

$nextNumeroPresupuesto = '1';
try {
    $nextNumeroPresupuesto = (string) ((int) $pdo->query('SELECT COALESCE(MAX(id_presupuesto), 0) + 1 FROM presupuesto')->fetchColumn());
} catch (Throwable) {
    $nextNumeroPresupuesto = '1';
}

$catalogs = [
    'marcas' => [],
    'vehiculos_marcas' => [],
    'vehiculos_modelos' => [],
    'motorizaciones' => [],
    'categorias' => [],
];

$repuestos = [];
try {
    $catalogs['marcas'] = $pdo->query('SELECT id_marca AS id, nombre_marca AS nombre FROM marcas WHERE activo = 1 ORDER BY nombre_marca')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['vehiculos_marcas'] = $pdo->query('SELECT id_vehiculo_marca AS id, nombre_marca_v AS nombre FROM vehiculos_marcas WHERE activo = 1 ORDER BY nombre_marca_v')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['vehiculos_modelos'] = $pdo->query(
        'SELECT m.id_modelo AS id,
                m.nombre_modelo AS nombre,
                vm.id_vehiculo_marca AS marca_id
         FROM vehiculos_modelos m
         INNER JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = m.id_vehiculo_marca
         WHERE m.activo = 1
           AND vm.activo = 1
         ORDER BY vm.nombre_marca_v, m.nombre_modelo'
    )->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['motorizaciones'] = $pdo->query('SELECT id_motorizacion AS id, nombre_motor AS nombre FROM motorizaciones WHERE activo = 1 ORDER BY nombre_motor')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['categorias'] = $pdo->query('SELECT id_categoria AS id, nombre_categoria AS nombre FROM categorias WHERE activo = 1 ORDER BY nombre_categoria')->fetchAll(PDO::FETCH_ASSOC);

    $repuestos = $pdo->query(
        'SELECT r.id_repuesto,
                r.codigo,
                r.nombre,
                COALESCE(precio_costo, 0) AS precio_costo,
                COALESCE(precio_venta, 0) AS precio_venta,
                COALESCE(r.id_marca, 0) AS id_marca,
                COALESCE(r.id_categoria, 0) AS id_categoria,
                COALESCE(vm.id_vehiculo_marca, 0) AS id_vehiculo_marca,
                COALESCE(vmo.id_modelo, 0) AS id_modelo,
                COALESCE(cv.id_motorizacion, 0) AS id_motorizacion
         FROM repuestos r
         LEFT JOIN (
             SELECT id_repuesto,
                    MIN(id_modelo) AS id_modelo,
                    MIN(id_motorizacion) AS id_motorizacion
             FROM compatibilidad_vehiculos
             WHERE activo = 1
             GROUP BY id_repuesto
         ) cv ON cv.id_repuesto = r.id_repuesto
         LEFT JOIN vehiculos_modelos vmo ON vmo.id_modelo = cv.id_modelo
         LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = vmo.id_vehiculo_marca
         WHERE r.activo = 1
         ORDER BY r.nombre'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'No se pudo cargar el catalogo de materiales: ' . $e->getMessage();
}

$repuestoById = [];
foreach ($repuestos as $rep) {
    $repuestoById[(int) $rep['id_repuesto']] = $rep;
}

$form = [
    'id_presupuesto' => 0,
    'numero_presupuesto' => '',
    'fecha' => date('Y-m-d'),
    'cliente' => '',
];

$formDetalles = [
    [
        'id_repuesto' => '',
        'material' => '',
        'cantidad' => '1',
        'precio_costo' => '0',
        'precio_venta' => '0',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $showForm = true;
        $formMode = 'edit';

        $idPresupuesto = (int) ($_POST['id_presupuesto'] ?? 0);
        $numeroPresupuesto = $idPresupuesto > 0 ? (string) $idPresupuesto : '';
        $fecha = trim((string) ($_POST['fecha'] ?? ''));
        $cliente = trim((string) ($_POST['cliente'] ?? ''));

        $form = [
            'id_presupuesto' => $idPresupuesto,
            'numero_presupuesto' => $numeroPresupuesto,
            'fecha' => $fecha,
            'cliente' => $cliente,
        ];

        $idRepuestos = (array) ($_POST['detalle_id_repuesto'] ?? []);
        $materiales = (array) ($_POST['detalle_material'] ?? []);
        $cantidades = (array) ($_POST['detalle_cantidad'] ?? []);
        $preciosCosto = (array) ($_POST['detalle_precio_costo'] ?? []);
        $preciosVenta = (array) ($_POST['detalle_precio_venta'] ?? []);

        $maxItems = max(count($idRepuestos), count($materiales), count($cantidades), count($preciosCosto), count($preciosVenta));
        $detallesLimpios = [];
        $montoTotal = 0.0;

        for ($i = 0; $i < $maxItems; $i++) {
            $idRepuesto = (int) ($idRepuestos[$i] ?? 0);
            $material = trim((string) ($materiales[$i] ?? ''));
            $cantidad = (int) ($cantidades[$i] ?? 0);
            $precioCosto = (float) ($preciosCosto[$i] ?? 0);
            $precioVenta = (float) ($preciosVenta[$i] ?? 0);

            if ($idRepuesto > 0 && isset($repuestoById[$idRepuesto])) {
                $rep = $repuestoById[$idRepuesto];
                if ($material === '') {
                    $material = (string) $rep['nombre'];
                }
                if ($precioCosto <= 0) {
                    $precioCosto = (float) $rep['precio_costo'];
                }
                if ($precioVenta <= 0) {
                    $precioVenta = (float) $rep['precio_venta'];
                }
            }

            if ($material === '' && $idRepuesto === 0 && $precioCosto <= 0 && $precioVenta <= 0) {
                continue;
            }

            if ($material === '' || $cantidad <= 0) {
                $error = 'Cada item del detalle debe tener material y cantidad mayor a 0.';
                break;
            }

            $detallesLimpios[] = [
                'id_repuesto' => $idRepuesto > 0 ? $idRepuesto : null,
                'material' => $material,
                'cantidad' => $cantidad,
                'precio_costo' => $precioCosto,
                'precio_venta' => $precioVenta,
            ];

            $montoTotal += $cantidad * $precioVenta;
        }

        $formDetalles = [];
        foreach ($detallesLimpios as $d) {
            $formDetalles[] = [
                'id_repuesto' => (string) ($d['id_repuesto'] ?? ''),
                'material' => (string) $d['material'],
                'cantidad' => (string) $d['cantidad'],
                'precio_costo' => (string) $d['precio_costo'],
                'precio_venta' => (string) $d['precio_venta'],
            ];
        }
        if ($formDetalles === []) {
            $formDetalles = [[
                'id_repuesto' => '',
                'material' => '',
                'cantidad' => '1',
                'precio_costo' => '0',
                'precio_venta' => '0',
            ]];
        }

        if ($error === '') {
            if ($fecha === '' || $cliente === '') {
                $error = 'Completa fecha y cliente.';
            } elseif ($detallesLimpios === []) {
                $error = 'Agrega al menos un item en el detalle.';
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                if ($idPresupuesto > 0) {
                    $up = $pdo->prepare(
                        'UPDATE presupuesto
                         SET numero_presupuesto = :numero_presupuesto,
                             fecha = :fecha,
                             cliente = :cliente,
                             monto_total = :monto_total
                         WHERE id_presupuesto = :id_presupuesto'
                    );
                    $up->execute([
                        'numero_presupuesto' => (string) $idPresupuesto,
                        'fecha' => $fecha,
                        'cliente' => $cliente,
                        'monto_total' => $montoTotal,
                        'id_presupuesto' => $idPresupuesto,
                    ]);

                    // Restaurar stock de los ítems anteriores antes de borrarlos
                    $oldDets = $pdo->prepare(
                        'SELECT id_repuesto, cantidad FROM presupuesto_detalle
                         WHERE id_presupuesto = :id_presupuesto AND id_repuesto IS NOT NULL'
                    );
                    $oldDets->execute(['id_presupuesto' => $idPresupuesto]);
                    $restoreStmt = $pdo->prepare(
                        'UPDATE repuestos SET stock_actual = stock_actual + :cantidad WHERE id_repuesto = :id_repuesto'
                    );
                    foreach ($oldDets->fetchAll(PDO::FETCH_ASSOC) as $old) {
                        $restoreStmt->execute(['cantidad' => (int) $old['cantidad'], 'id_repuesto' => (int) $old['id_repuesto']]);
                    }

                    $pdo->prepare('DELETE FROM presupuesto_detalle WHERE id_presupuesto = :id_presupuesto')
                        ->execute(['id_presupuesto' => $idPresupuesto]);

                    $successMessage = 'Presupuesto actualizado correctamente.';
                } else {
                    $numeroTemporal = 'TMP-' . bin2hex(random_bytes(8));
                    $ins = $pdo->prepare(
                        'INSERT INTO presupuesto (numero_presupuesto, fecha, cliente, monto_total, activo)
                         VALUES (:numero_presupuesto, :fecha, :cliente, :monto_total, 1)'
                    );
                    $ins->execute([
                        'numero_presupuesto' => $numeroTemporal,
                        'fecha' => $fecha,
                        'cliente' => $cliente,
                        'monto_total' => $montoTotal,
                    ]);

                    $idPresupuesto = (int) $pdo->lastInsertId();
                    $numeroPresupuesto = (string) $idPresupuesto;
                    $pdo->prepare(
                        'UPDATE presupuesto
                         SET numero_presupuesto = :numero_presupuesto
                         WHERE id_presupuesto = :id_presupuesto'
                    )->execute([
                        'numero_presupuesto' => $numeroPresupuesto,
                        'id_presupuesto' => $idPresupuesto,
                    ]);
                    $successMessage = 'Presupuesto creado correctamente.';
                }

                $insDet = $pdo->prepare(
                    'INSERT INTO presupuesto_detalle (
                        id_presupuesto,
                        id_repuesto,
                        material,
                        cantidad,
                        precio_costo,
                        precio_venta
                     ) VALUES (
                        :id_presupuesto,
                        :id_repuesto,
                        :material,
                        :cantidad,
                        :precio_costo,
                        :precio_venta
                     )'
                );

                foreach ($detallesLimpios as $d) {
                    $insDet->execute([
                        'id_presupuesto' => $idPresupuesto,
                        'id_repuesto' => $d['id_repuesto'],
                        'material' => $d['material'],
                        'cantidad' => $d['cantidad'],
                        'precio_costo' => $d['precio_costo'],
                        'precio_venta' => $d['precio_venta'],
                    ]);
                }

                $requiredByRepuesto = [];
                foreach ($detallesLimpios as $d) {
                    if ($d['id_repuesto'] === null) {
                        continue;
                    }
                    $rid = (int) $d['id_repuesto'];
                    if (!isset($requiredByRepuesto[$rid])) {
                        $requiredByRepuesto[$rid] = [
                            'cantidad' => 0,
                            'material' => (string) $d['material'],
                        ];
                    }
                    $requiredByRepuesto[$rid]['cantidad'] += (int) $d['cantidad'];
                }

                if ($requiredByRepuesto !== []) {
                    $idsStock = array_keys($requiredByRepuesto);
                    $placeholders = implode(',', array_fill(0, count($idsStock), '?'));
                    $stockStmt = $pdo->prepare(
                        'SELECT id_repuesto, COALESCE(stock_actual, 0) AS stock_actual
                         FROM repuestos
                         WHERE id_repuesto IN (' . $placeholders . ')
                         FOR UPDATE'
                    );
                    $stockStmt->execute($idsStock);
                    $stockById = [];
                    foreach ($stockStmt->fetchAll(PDO::FETCH_ASSOC) as $rowStock) {
                        $stockById[(int) $rowStock['id_repuesto']] = (int) $rowStock['stock_actual'];
                    }

                    foreach ($requiredByRepuesto as $rid => $req) {
                        $stockActual = (int) ($stockById[$rid] ?? 0);
                        if ((int) $req['cantidad'] > $stockActual) {
                            throw new RuntimeException(
                                'No se pudo descontar la cantidad para "' . $req['material'] . '". Stock disponible: ' . $stockActual . '.'
                            );
                        }
                    }
                }

                // Descontar stock por cada ítem con repuesto vinculado
                $deductStmt = $pdo->prepare(
                    'UPDATE repuestos SET stock_actual = stock_actual - :cantidad WHERE id_repuesto = :id_repuesto'
                );
                foreach ($detallesLimpios as $d) {
                    if ($d['id_repuesto'] !== null) {
                        $deductStmt->execute(['cantidad' => $d['cantidad'], 'id_repuesto' => $d['id_repuesto']]);
                    }
                }

                $pdo->commit();
                $success = $successMessage;
                $showForm = true;
                $formMode = 'edit';
                $form['id_presupuesto'] = $idPresupuesto;
                $form['numero_presupuesto'] = (string) $idPresupuesto;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e instanceof RuntimeException) {
                    $error = $e->getMessage();
                } else {
                    $error = 'No fue posible guardar el presupuesto: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'reset_all') {
        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM presupuesto_detalle');
            $pdo->exec('DELETE FROM presupuesto');
            $pdo->commit();
            $success = 'Todos los presupuestos fueron eliminados correctamente.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'No fue posible eliminar los presupuestos: ' . $e->getMessage();
        }
    }

    if ($action === 'delete_confirm') {
        $idPresupuesto = (int) ($_POST['id_presupuesto'] ?? 0);
        if ($idPresupuesto > 0) {
            try {
                $stmt = $pdo->prepare('UPDATE presupuesto SET activo = 0 WHERE id_presupuesto = :id_presupuesto');
                $stmt->execute(['id_presupuesto' => $idPresupuesto]);
                $success = 'Presupuesto dado de baja correctamente.';
            } catch (Throwable $e) {
                $error = 'No fue posible dar de baja el presupuesto: ' . $e->getMessage();
            }
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$newRequested = isset($_GET['new']);

if ($newRequested) {
    $showForm = true;
    $formMode = 'new';
}

if ($editId > 0 && $error === '') {
    try {
        $stmt = $pdo->prepare('SELECT * FROM presupuesto WHERE id_presupuesto = :id_presupuesto LIMIT 1');
        $stmt->execute(['id_presupuesto' => $editId]);
        $selected = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selected) {
            $showForm = true;
            $formMode = 'edit';
            $form = [
                'id_presupuesto' => (int) $selected['id_presupuesto'],
                'numero_presupuesto' => (string) $selected['id_presupuesto'],
                'fecha' => (string) $selected['fecha'],
                'cliente' => (string) $selected['cliente'],
            ];

            $detStmt = $pdo->prepare(
                'SELECT id_repuesto, material, cantidad, precio_costo, precio_venta
                 FROM presupuesto_detalle
                 WHERE id_presupuesto = :id_presupuesto
                 ORDER BY id_detalle ASC'
            );
            $detStmt->execute(['id_presupuesto' => $editId]);
            $detalles = $detStmt->fetchAll(PDO::FETCH_ASSOC);

            $formDetalles = [];
            foreach ($detalles as $d) {
                $formDetalles[] = [
                    'id_repuesto' => (string) ($d['id_repuesto'] ?? ''),
                    'material' => (string) ($d['material'] ?? ''),
                    'cantidad' => (string) ($d['cantidad'] ?? 1),
                    'precio_costo' => (string) ($d['precio_costo'] ?? 0),
                    'precio_venta' => (string) ($d['precio_venta'] ?? 0),
                ];
            }
            if ($formDetalles === []) {
                $formDetalles[] = [
                    'id_repuesto' => '',
                    'material' => '',
                    'cantidad' => '1',
                    'precio_costo' => '0',
                    'precio_venta' => '0',
                ];
            }
        }
    } catch (Throwable $e) {
        $error = 'No fue posible cargar el presupuesto seleccionado: ' . $e->getMessage();
    }
}

$presupuestos = [];
try {
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
    $error = 'No fue posible cargar la lista de presupuestos: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 1250px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .btn-danger { background: #b91c1c; color: #fff; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
        .grid-cabecera { display: grid; grid-template-columns: 180px 1fr 180px; gap: 0.75rem; }
        label { display: block; font-size: 0.9rem; margin-bottom: 0.25rem; font-weight: 600; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #c6d2e4; border-radius: 8px; padding: 0.55rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.55rem; text-align: left; font-size: 0.93rem; vertical-align: top; }
        .num { text-align: right; }
        .actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .msg-error { background: #fee2e2; color: #991b1b; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-ok { background: #dcfce7; color: #166534; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .detalle-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; gap: 0.75rem; }
        .btn-small { padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.86rem; }
        .btn-icon { width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .table-actions { display: flex; gap: 0.4rem; align-items: center; }
        .input-numero-presupuesto { width: 100%; min-width: 0; }
        .material-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.65rem;
            margin: 0.2rem 0 0.9rem;
            padding: 0.75rem;
            background: #f8fafc;
            border: 1px solid #dbe4f2;
            border-radius: 10px;
        }
        .material-filter-actions { display: flex; align-items: end; }
        .reset-box { margin-top: 1.5rem; border: 2px dashed #dc2626; border-radius: 10px; padding: .9rem 1.1rem; background: #fef2f2; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .reset-box p { margin: 0; font-size: .88rem; color: #7f1d1d; font-weight: 600; }
        .btn-reset { background: #dc2626; color: #fff; border: none; padding: .55rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: .9rem; cursor: pointer; }
        .btn-reset:hover { background: #b91c1c; }
        @media (max-width: 640px) {
            .grid-cabecera { grid-template-columns: 1fr; }
            .input-numero-presupuesto { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php $numeroMostrado = (int) $form['id_presupuesto'] > 0 ? (string) $form['id_presupuesto'] : $nextNumeroPresupuesto; ?>
    <div class="top">
        <div>
            <h1>Presupuesto</h1>
            <p>Alta, modificación y baja lógica de presupuestos.</p>
        </div>
        <div class="actions">
            <a class="btn btn-muted" href="/dashboard.php">Volver al panel</a>
            <a class="btn btn-primary" href="/presupuestos.php?new=1">Nuevo</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="msg-ok"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <div class="panel">
        <h2><?= $formMode === 'edit' && (int) $form['id_presupuesto'] > 0 ? 'Editar presupuesto' : 'Nuevo presupuesto' ?></h2>
        <form method="post" action="/presupuestos.php" autocomplete="off">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id_presupuesto" value="<?= (int) $form['id_presupuesto'] ?>">

            <div class="grid-cabecera">
                <div>
                    <label for="numero_presupuesto">Numero presupuesto</label>
                    <input id="numero_presupuesto" class="input-numero-presupuesto" value="<?= htmlspecialchars($numeroMostrado) ?>" readonly>
                </div>
                <div>
                    <label for="cliente">Cliente</label>
                    <input id="cliente" name="cliente" maxlength="150" required value="<?= htmlspecialchars((string) $form['cliente']) ?>">
                </div>
                <div>
                    <label for="fecha">Fecha</label>
                    <input id="fecha" name="fecha" type="date" required value="<?= htmlspecialchars((string) $form['fecha']) ?>">
                </div>
            </div>

            <h3 style="margin-top:1rem; margin-bottom:0.5rem;">Detalle de materiales</h3>
            <div class="material-filters">
                <div>
                    <label for="filtro_marca_repuesto">Marca del repuesto</label>
                    <select id="filtro_marca_repuesto">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['marcas'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>"><?= htmlspecialchars((string) $row['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_vehiculo_marca">Vehículo marca</label>
                    <select id="filtro_vehiculo_marca">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['vehiculos_marcas'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>"><?= htmlspecialchars((string) $row['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_vehiculo_modelo">Vehículo modelo</label>
                    <select id="filtro_vehiculo_modelo">
                        <option value="0">Todos</option>
                        <?php foreach ($catalogs['vehiculos_modelos'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" data-marca="<?= (int) $row['marca_id'] ?>"><?= htmlspecialchars((string) $row['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_motorizacion">Motorización</label>
                    <select id="filtro_motorizacion">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['motorizaciones'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>"><?= htmlspecialchars((string) $row['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtro_categoria">Categoría</label>
                    <select id="filtro_categoria">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['categorias'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>"><?= htmlspecialchars((string) $row['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="material-filter-actions">
                    <button type="button" class="btn btn-muted btn-small" id="limpiar-filtros-material">Limpiar filtros</button>
                </div>
            </div>
            <table id="tabla-detalle">
                <thead>
                <tr>
                    <th style="width:24%;">Material (catálogo)</th>
                    <th style="width:26%;">Material (texto)</th>
                    <th class="num" style="width:10%;">Cantidad</th>
                    <th class="num" style="width:15%;">Precio costo</th>
                    <th class="num" style="width:15%;">Precio venta</th>
                    <th style="width:10%;">Acción</th>
                </tr>
                </thead>
                <tbody id="detalle-body">
                <?php foreach ($formDetalles as $d): ?>
                    <tr>
                        <td>
                            <select name="detalle_id_repuesto[]" onchange="completarDesdeRepuesto(this)">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($repuestos as $rep): ?>
                                    <option
                                        value="<?= (int) $rep['id_repuesto'] ?>"
                                        data-material="<?= htmlspecialchars((string) $rep['nombre']) ?>"
                                        data-precio-costo="<?= htmlspecialchars((string) $rep['precio_costo']) ?>"
                                        data-precio-venta="<?= htmlspecialchars((string) $rep['precio_venta']) ?>"
                                        data-id-marca="<?= (int) ($rep['id_marca'] ?? 0) ?>"
                                        data-id-vehiculo-marca="<?= (int) ($rep['id_vehiculo_marca'] ?? 0) ?>"
                                        data-id-modelo="<?= (int) ($rep['id_modelo'] ?? 0) ?>"
                                        data-id-motorizacion="<?= (int) ($rep['id_motorizacion'] ?? 0) ?>"
                                        data-id-categoria="<?= (int) ($rep['id_categoria'] ?? 0) ?>"
                                        <?= (string) $d['id_repuesto'] === (string) $rep['id_repuesto'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars((string) $rep['codigo'] . ' - ' . $rep['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input name="detalle_material[]" maxlength="150" value="<?= htmlspecialchars((string) $d['material']) ?>"></td>
                        <td class="num"><input class="num" name="detalle_cantidad[]" type="number" min="1" step="1" value="<?= htmlspecialchars((string) $d['cantidad']) ?>"></td>
                        <td class="num"><input class="num" name="detalle_precio_costo[]" type="number" min="0" step="0.01" value="<?= htmlspecialchars((string) $d['precio_costo']) ?>" readonly style="background:#f1f5f9;color:#64748b;"></td>
                        <td class="num"><input class="num" name="detalle_precio_venta[]" type="number" min="0" step="0.01" value="<?= htmlspecialchars((string) $d['precio_venta']) ?>"></td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-muted btn-icon" onclick="agregarFilaDetalle()" title="Agregar item">&#43;</button>
                                <button type="button" class="btn btn-danger btn-icon" onclick="quitarFilaDetalle(this)" title="Quitar">&#10005;</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="detalle-actions">
                <div class="actions">
                    <?php if ((int) $form['id_presupuesto'] > 0): ?>
                        <a class="btn btn-muted" href="/presupuestos_pdf.php?id=<?= (int) $form['id_presupuesto'] ?>" target="_blank" rel="noopener">Imprimir</a>
                    <?php else: ?>
                        <button class="btn btn-muted" type="button" disabled title="Guardá el presupuesto para habilitar la impresión">Imprimir</button>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Guardar presupuesto</button>
                    <a class="btn btn-muted" href="/presupuestos.php">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!$showForm): ?>
    <div class="panel">
        <h2>Lista de presupuestos</h2>
        <table>
            <thead>
            <tr>
                <th>Numero</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th class="num">Items</th>
                <th class="num">Monto total</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($presupuestos) === 0): ?>
                <tr>
                    <td colspan="7">No hay presupuestos cargados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($presupuestos as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_presupuesto'] ?></td>
                        <td><?= htmlspecialchars((string) $row['fecha']) ?></td>
                        <td><?= htmlspecialchars((string) $row['cliente']) ?></td>
                        <td class="num"><?= (int) $row['cant_items'] ?></td>
                        <td class="num"><?= number_format((float) ($row['monto_total'] ?? 0), 2, ',', '.') ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-muted btn-icon" href="/presupuestos.php?edit=<?= (int) $row['id_presupuesto'] ?>" title="Editar">&#9998;</a>
                                <form method="post" action="/presupuestos.php" onsubmit="return confirm('Se dara de baja el presupuesto seleccionado. Continuar?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_confirm">
                                    <input type="hidden" name="id_presupuesto" value="<?= (int) $row['id_presupuesto'] ?>">
                                    <button class="btn btn-danger btn-icon" type="submit" title="Dar de baja">&#128465;</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="reset-box">
        <p>&#9888; <u>Atenci&oacute;n</u>: borra <strong>todos</strong> los presupuestos y sus detalles. Esta acci&oacute;n no se puede deshacer.</p>
        <form method="post" action="/presupuestos.php" onsubmit="return confirm('Se eliminar&aacute;n TODOS los presupuestos. &iquest;Confirm&aacute;s?');">
            <input type="hidden" name="action" value="reset_all">
            <button type="submit" class="btn-reset">&#128465; Borrar todos los presupuestos</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<template id="detalle-row-template">
    <tr>
        <td>
            <select name="detalle_id_repuesto[]" onchange="completarDesdeRepuesto(this)">
                <option value="">Seleccionar...</option>
                <?php foreach ($repuestos as $rep): ?>
                    <option
                        value="<?= (int) $rep['id_repuesto'] ?>"
                        data-material="<?= htmlspecialchars((string) $rep['nombre']) ?>"
                        data-precio-costo="<?= htmlspecialchars((string) $rep['precio_costo']) ?>"
                        data-precio-venta="<?= htmlspecialchars((string) $rep['precio_venta']) ?>"
                        data-id-marca="<?= (int) ($rep['id_marca'] ?? 0) ?>"
                        data-id-vehiculo-marca="<?= (int) ($rep['id_vehiculo_marca'] ?? 0) ?>"
                        data-id-modelo="<?= (int) ($rep['id_modelo'] ?? 0) ?>"
                        data-id-motorizacion="<?= (int) ($rep['id_motorizacion'] ?? 0) ?>"
                        data-id-categoria="<?= (int) ($rep['id_categoria'] ?? 0) ?>"
                    >
                        <?= htmlspecialchars((string) $rep['codigo'] . ' - ' . $rep['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input name="detalle_material[]" maxlength="150"></td>
        <td class="num"><input class="num" name="detalle_cantidad[]" type="number" min="1" step="1" value="1"></td>
        <td class="num"><input class="num" name="detalle_precio_costo[]" type="number" min="0" step="0.01" value="0" readonly style="background:#f1f5f9;color:#64748b;"></td>
        <td class="num"><input class="num" name="detalle_precio_venta[]" type="number" min="0" step="0.01" value="0"></td>
        <td>
            <div class="table-actions">
                <button type="button" class="btn btn-muted btn-icon" onclick="agregarFilaDetalle()" title="Agregar item">&#43;</button>
                <button type="button" class="btn btn-danger btn-icon" onclick="quitarFilaDetalle(this)" title="Quitar">&#10005;</button>
            </div>
        </td>
    </tr>
</template>

<script>
var repuestosCatalogo = <?= json_encode($repuestos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function toPositiveInt(value) {
    var n = Number(value);
    return Number.isFinite(n) && n > 0 ? Math.trunc(n) : 0;
}

function pasaFiltrosMaterial(repuesto) {
    var marca = toPositiveInt(document.getElementById('filtro_marca_repuesto') ? document.getElementById('filtro_marca_repuesto').value : 0);
    var vehiculoMarca = toPositiveInt(document.getElementById('filtro_vehiculo_marca') ? document.getElementById('filtro_vehiculo_marca').value : 0);
    var modelo = toPositiveInt(document.getElementById('filtro_vehiculo_modelo') ? document.getElementById('filtro_vehiculo_modelo').value : 0);
    var motorizacion = toPositiveInt(document.getElementById('filtro_motorizacion') ? document.getElementById('filtro_motorizacion').value : 0);
    var categoria = toPositiveInt(document.getElementById('filtro_categoria') ? document.getElementById('filtro_categoria').value : 0);

    if (marca > 0 && toPositiveInt(repuesto.id_marca) !== marca) return false;
    if (vehiculoMarca > 0 && toPositiveInt(repuesto.id_vehiculo_marca) !== vehiculoMarca) return false;
    if (modelo > 0 && toPositiveInt(repuesto.id_modelo) !== modelo) return false;
    if (motorizacion > 0 && toPositiveInt(repuesto.id_motorizacion) !== motorizacion) return false;
    if (categoria > 0 && toPositiveInt(repuesto.id_categoria) !== categoria) return false;

    return true;
}

function poblarSelectMaterial(selectEl) {
    if (!selectEl) return;

    var seleccionActual = String(selectEl.value || '');
    selectEl.innerHTML = '';

    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Seleccionar...';
    selectEl.appendChild(placeholder);

    var opcionSeleccionadaVisible = false;

    repuestosCatalogo.forEach(function (rep) {
        var id = String(rep.id_repuesto || '');
        if (id === '') return;

        var visible = pasaFiltrosMaterial(rep);
        var mantenerSeleccion = seleccionActual !== '' && id === seleccionActual;
        if (!visible && !mantenerSeleccion) return;

        var opt = document.createElement('option');
        opt.value = id;
        opt.textContent = String((rep.codigo || '') + ' - ' + (rep.nombre || ''));
        opt.setAttribute('data-material', String(rep.nombre || ''));
        opt.setAttribute('data-precio-costo', String(rep.precio_costo || '0'));
        opt.setAttribute('data-precio-venta', String(rep.precio_venta || '0'));
        opt.setAttribute('data-id-marca', String(rep.id_marca || '0'));
        opt.setAttribute('data-id-vehiculo-marca', String(rep.id_vehiculo_marca || '0'));
        opt.setAttribute('data-id-modelo', String(rep.id_modelo || '0'));
        opt.setAttribute('data-id-motorizacion', String(rep.id_motorizacion || '0'));
        opt.setAttribute('data-id-categoria', String(rep.id_categoria || '0'));

        if (mantenerSeleccion) {
            opt.selected = true;
            opcionSeleccionadaVisible = true;
        }

        selectEl.appendChild(opt);
    });

    if (seleccionActual !== '' && !opcionSeleccionadaVisible) {
        selectEl.value = '';
    }
}

function refrescarSelectsMaterial() {
    var selects = document.querySelectorAll('select[name="detalle_id_repuesto[]"]');
    selects.forEach(function (sel) { poblarSelectMaterial(sel); });
}

function filtrarModelosPorMarca() {
    var selVehMarca = document.getElementById('filtro_vehiculo_marca');
    var selModelo = document.getElementById('filtro_vehiculo_modelo');
    if (!selVehMarca || !selModelo) return;

    if (!selModelo._allOptions) {
        selModelo._allOptions = Array.prototype.slice.call(selModelo.options).map(function (opt) {
            return {
                value: opt.value,
                text: opt.textContent,
                marca: opt.getAttribute('data-marca') || ''
            };
        });
    }

    var marcaId = String(selVehMarca.value || '0');
    var modeloActual = String(selModelo.value || '0');
    selModelo.innerHTML = '';

    selModelo._allOptions.forEach(function (baseOpt) {
        var isPlaceholder = baseOpt.value === '0';
        var coincideMarca = marcaId === '0' || baseOpt.marca === marcaId;
        if (!isPlaceholder && !coincideMarca) return;

        var opt = document.createElement('option');
        opt.value = baseOpt.value;
        opt.textContent = baseOpt.text;
        if (baseOpt.marca !== '') {
            opt.setAttribute('data-marca', baseOpt.marca);
        }
        selModelo.appendChild(opt);
    });

    var sigueValido = Array.prototype.some.call(selModelo.options, function (o) { return o.value === modeloActual; });
    selModelo.value = sigueValido ? modeloActual : '0';
}

function agregarFilaDetalle() {
    var tpl = document.getElementById('detalle-row-template');
    var body = document.getElementById('detalle-body');
    if (!tpl || !body) return;
    var clone = tpl.content.cloneNode(true);
    body.appendChild(clone);
    refrescarSelectsMaterial();
}

function quitarFilaDetalle(btn) {
    var body = document.getElementById('detalle-body');
    if (!body) return;
    if (body.children.length <= 1) return;
    var row = btn.closest('tr');
    if (row) row.remove();
}

function completarDesdeRepuesto(selectEl) {
    var row = selectEl.closest('tr');
    if (!row) return;

    var opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) return;

    var material = row.querySelector('input[name="detalle_material[]"]');
    var precioCosto = row.querySelector('input[name="detalle_precio_costo[]"]');
    var precioVenta = row.querySelector('input[name="detalle_precio_venta[]"]');

    if (material && (!material.value || material.value.trim() === '')) {
        material.value = opt.getAttribute('data-material') || '';
    }

    if (precioCosto && (!precioCosto.value || Number(precioCosto.value) <= 0)) {
        precioCosto.value = opt.getAttribute('data-precio-costo') || '0';
    }

    if (precioVenta && (!precioVenta.value || Number(precioVenta.value) <= 0)) {
        precioVenta.value = opt.getAttribute('data-precio-venta') || '0';
    }
}

(function () {
    var filtros = [
        document.getElementById('filtro_marca_repuesto'),
        document.getElementById('filtro_vehiculo_marca'),
        document.getElementById('filtro_vehiculo_modelo'),
        document.getElementById('filtro_motorizacion'),
        document.getElementById('filtro_categoria')
    ].filter(Boolean);

    filtros.forEach(function (el) {
        el.addEventListener('change', function () {
            if (el.id === 'filtro_vehiculo_marca') {
                filtrarModelosPorMarca();
            }
            refrescarSelectsMaterial();
        });
    });

    var btnLimpiar = document.getElementById('limpiar-filtros-material');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            var ids = [
                'filtro_marca_repuesto',
                'filtro_vehiculo_marca',
                'filtro_vehiculo_modelo',
                'filtro_motorizacion',
                'filtro_categoria'
            ];
            ids.forEach(function (id) {
                var sel = document.getElementById(id);
                if (sel) sel.value = '0';
            });
            filtrarModelosPorMarca();
            refrescarSelectsMaterial();
        });
    }

    filtrarModelosPorMarca();
    refrescarSelectsMaterial();
}());
</script>
</body>
</html>
