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

$catalogs = [
    'marcas' => [],
    'vehiculos_marcas' => [],
    'vehiculos_modelos' => [],
    'motorizaciones' => [],
    'categorias' => [],
    'unidades' => [],
    'proveedores' => [],
];

$form = [
    'id_repuesto' => 0,
    'sku' => '',
    'cod_oem' => '',
    'nombre' => '',
    'marca_id' => '',
    'vehiculo_marca_id' => '',
    'vehiculo_modelo_id' => '',
    'motorizacion_id' => '',
    'categoria_id' => '',
    'unidad_id' => '',
    'proveedor_id' => '',
    'precio_costo' => '0',
    'stock_actual' => '0',
    'stock_minimo' => '5',
    'activo' => '1',
];

$filters = [
    'nombre' => trim((string) ($_GET['f_nombre'] ?? '')),
    'marca_id' => (int) ($_GET['f_marca_id'] ?? 0),
    'categoria_id' => (int) ($_GET['f_categoria_id'] ?? 0),
    'proveedor_id' => (int) ($_GET['f_proveedor_id'] ?? 0),
    'activo' => (string) ($_GET['f_activo'] ?? ''),
];

$hasActiveFilters =
    $filters['nombre'] !== '' ||
    $filters['marca_id'] > 0 ||
    $filters['categoria_id'] > 0 ||
    $filters['proveedor_id'] > 0 ||
    ($filters['activo'] === '1' || $filters['activo'] === '0');

$showFilters = isset($_GET['show_filters']) || $hasActiveFilters;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $redirectToInitialState = false;
        $showForm = true;
        $formMode = 'edit';
        $idRepuesto = (int) ($_POST['id_repuesto'] ?? 0);
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $codOem = trim((string) ($_POST['cod_oem'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $marcaId = (int) ($_POST['marca_id'] ?? 0);
        $vehiculoMarcaId = (int) ($_POST['vehiculo_marca_id'] ?? 0);
        $vehiculoModeloId = (int) ($_POST['vehiculo_modelo_id'] ?? 0);
        $motorizacionId = (int) ($_POST['motorizacion_id'] ?? 0);
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
        $unidadId = (int) ($_POST['unidad_id'] ?? 0);
        $proveedorId = (int) ($_POST['proveedor_id'] ?? 0);
        $precioCosto = (float) ($_POST['precio_costo'] ?? 0);
        $stockActual = (int) ($_POST['stock_actual'] ?? 0);
        $stockMinimo = (int) ($_POST['stock_minimo'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        $form = [
            'id_repuesto' => $idRepuesto,
            'sku' => $sku,
            'cod_oem' => $codOem,
            'nombre' => $nombre,
            'marca_id' => (string) $marcaId,
            'vehiculo_marca_id' => (string) $vehiculoMarcaId,
            'vehiculo_modelo_id' => (string) $vehiculoModeloId,
            'motorizacion_id' => (string) $motorizacionId,
            'categoria_id' => (string) $categoriaId,
            'unidad_id' => (string) $unidadId,
            'proveedor_id' => (string) $proveedorId,
            'precio_costo' => (string) $precioCosto,
            'stock_actual' => (string) $stockActual,
            'stock_minimo' => (string) $stockMinimo,
            'activo' => (string) $activo,
        ];

        if (
            $sku === '' ||
            $nombre === '' ||
            $marcaId <= 0 ||
            $vehiculoMarcaId <= 0 ||
            $vehiculoModeloId <= 0 ||
            $motorizacionId <= 0 ||
            $categoriaId <= 0 ||
            $unidadId <= 0 ||
            $proveedorId <= 0
        ) {
            $error = 'Completa todos los campos obligatorios.';
        } else {
            try {
                $dupSku = $pdo->prepare(
                    'SELECT 1
                     FROM repuestos
                     WHERE LOWER(sku) = LOWER(:sku)
                       AND id_repuesto <> :id_repuesto
                     LIMIT 1'
                );
                $dupSku->execute([
                    'sku' => $sku,
                    'id_repuesto' => $idRepuesto,
                ]);

                if ($dupSku->fetchColumn() !== false) {
                    throw new RuntimeException('Ya existe un repuesto con ese SKU.');
                }

                if ($codOem !== '') {
                    $dupOem = $pdo->prepare(
                        'SELECT 1
                         FROM repuestos
                         WHERE LOWER(cod_oem) = LOWER(:cod_oem)
                           AND id_repuesto <> :id_repuesto
                         LIMIT 1'
                    );
                    $dupOem->execute([
                        'cod_oem' => $codOem,
                        'id_repuesto' => $idRepuesto,
                    ]);

                    if ($dupOem->fetchColumn() !== false) {
                        throw new RuntimeException('Ya existe un repuesto con ese Código OEM.');
                    }
                }

                $pdo->beginTransaction();

                if ($idRepuesto > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE repuestos
                         SET sku = :sku,
                             cod_oem = :cod_oem,
                             nombre = :nombre,
                             id_marca = :id_marca,
                             id_categoria = :id_categoria,
                             id_unidad = :id_unidad,
                             id_proveedor = :id_proveedor,
                             precio_costo = :precio_costo,
                             stock_actual = :stock_actual,
                             stock_minimo = :stock_minimo,
                             activo = :activo
                         WHERE id_repuesto = :id_repuesto'
                    );

                    $stmt->execute([
                        'sku' => $sku,
                        'cod_oem' => $codOem === '' ? null : $codOem,
                        'nombre' => $nombre,
                        'id_marca' => $marcaId,
                        'id_categoria' => $categoriaId,
                        'id_unidad' => $unidadId,
                        'id_proveedor' => $proveedorId,
                        'precio_costo' => $precioCosto,
                        'stock_actual' => $stockActual,
                        'stock_minimo' => $stockMinimo,
                        'activo' => $activo,
                        'id_repuesto' => $idRepuesto,
                    ]);

                    $checkCompat = $pdo->prepare(
                        'SELECT id_compatibilidad
                         FROM compatibilidad_vehiculos
                         WHERE id_repuesto = :id_repuesto
                         ORDER BY id_compatibilidad DESC
                         LIMIT 1'
                    );
                    $checkCompat->execute(['id_repuesto' => $idRepuesto]);
                    $compatId = (int) ($checkCompat->fetchColumn() ?: 0);

                    if ($compatId > 0) {
                        $upCompat = $pdo->prepare(
                            'UPDATE compatibilidad_vehiculos
                             SET id_modelo = :id_modelo,
                                 id_motorizacion = :id_motorizacion,
                                 activo = :activo
                             WHERE id_compatibilidad = :id_compatibilidad'
                        );
                        $upCompat->execute([
                            'id_modelo' => $vehiculoModeloId,
                            'id_motorizacion' => $motorizacionId,
                            'activo' => $activo,
                            'id_compatibilidad' => $compatId,
                        ]);
                    } else {
                        $insCompat = $pdo->prepare(
                            'INSERT INTO compatibilidad_vehiculos (id_repuesto, id_modelo, id_motorizacion, activo)
                             VALUES (:id_repuesto, :id_modelo, :id_motorizacion, :activo)'
                        );
                        $insCompat->execute([
                            'id_repuesto' => $idRepuesto,
                            'id_modelo' => $vehiculoModeloId,
                            'id_motorizacion' => $motorizacionId,
                            'activo' => $activo,
                        ]);
                    }

                    $success = 'Repuesto actualizado correctamente.';
                    $showForm = false;
                    $formMode = 'new';
                    $form = [
                        'id_repuesto' => 0,
                        'sku' => '',
                        'cod_oem' => '',
                        'nombre' => '',
                        'marca_id' => '',
                        'vehiculo_marca_id' => '',
                        'vehiculo_modelo_id' => '',
                        'motorizacion_id' => '',
                        'categoria_id' => '',
                        'unidad_id' => '',
                        'proveedor_id' => '',
                        'precio_costo' => '0',
                        'stock_actual' => '0',
                        'stock_minimo' => '5',
                        'activo' => '1',
                    ];
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO repuestos (
                            sku,
                            cod_oem,
                            nombre,
                            id_marca,
                            id_categoria,
                            id_unidad,
                            id_proveedor,
                            precio_costo,
                            stock_actual,
                            stock_minimo,
                            activo
                         ) VALUES (
                            :sku,
                            :cod_oem,
                            :nombre,
                            :id_marca,
                            :id_categoria,
                            :id_unidad,
                            :id_proveedor,
                            :precio_costo,
                            :stock_actual,
                            :stock_minimo,
                            :activo
                         )'
                    );

                    $stmt->execute([
                        'sku' => $sku,
                        'cod_oem' => $codOem === '' ? null : $codOem,
                        'nombre' => $nombre,
                        'id_marca' => $marcaId,
                        'id_categoria' => $categoriaId,
                        'id_unidad' => $unidadId,
                        'id_proveedor' => $proveedorId,
                        'precio_costo' => $precioCosto,
                        'stock_actual' => $stockActual,
                        'stock_minimo' => $stockMinimo,
                        'activo' => $activo,
                    ]);

                    $idRepuesto = (int) $pdo->lastInsertId();

                    $insCompat = $pdo->prepare(
                        'INSERT INTO compatibilidad_vehiculos (id_repuesto, id_modelo, id_motorizacion, activo)
                         VALUES (:id_repuesto, :id_modelo, :id_motorizacion, :activo)'
                    );
                    $insCompat->execute([
                        'id_repuesto' => $idRepuesto,
                        'id_modelo' => $vehiculoModeloId,
                        'id_motorizacion' => $motorizacionId,
                        'activo' => $activo,
                    ]);

                    $success = 'Repuesto creado correctamente.';
                    $redirectToInitialState = true;
                }

                $pdo->commit();

                if ($redirectToInitialState) {
                    $_SESSION['flash_success'] = $success;
                    header('Location: /compras.php');
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'No fue posible guardar el repuesto: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_confirm') {
        $idRepuesto = (int) ($_POST['id_repuesto'] ?? 0);
        if ($idRepuesto > 0) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('UPDATE repuestos SET activo = 0 WHERE id_repuesto = :id_repuesto');
                $stmt->execute(['id_repuesto' => $idRepuesto]);

                $stmtCompat = $pdo->prepare('UPDATE compatibilidad_vehiculos SET activo = 0 WHERE id_repuesto = :id_repuesto');
                $stmtCompat->execute(['id_repuesto' => $idRepuesto]);

                $pdo->commit();
                $success = 'Repuesto dado de baja correctamente.';
                $showForm = false;
                $formMode = 'new';
                $form = [
                    'id_repuesto' => 0,
                    'sku' => '',
                    'cod_oem' => '',
                    'nombre' => '',
                    'marca_id' => '',
                    'vehiculo_marca_id' => '',
                    'vehiculo_modelo_id' => '',
                    'motorizacion_id' => '',
                    'categoria_id' => '',
                    'unidad_id' => '',
                    'proveedor_id' => '',
                    'precio_costo' => '0',
                    'stock_actual' => '0',
                    'stock_minimo' => '5',
                    'activo' => '1',
                ];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'No fue posible dar de baja el repuesto: ' . $e->getMessage();
                $showForm = true;
                $formMode = 'delete';
            }
        }
    }
}

try {
    $catalogs['marcas'] = $pdo->query('SELECT id_marca AS id, nombre_marca AS nombre FROM marcas WHERE activo = 1 ORDER BY nombre_marca')->fetchAll();
    $catalogs['vehiculos_marcas'] = $pdo->query('SELECT id_vehiculo_marca AS id, nombre_marca_v AS nombre FROM vehiculos_marcas WHERE activo = 1 ORDER BY nombre_marca_v')->fetchAll();
    $catalogs['vehiculos_modelos'] = $pdo->query(
        'SELECT m.id_modelo AS id,
                m.nombre_modelo AS nombre,
                vm.nombre_marca_v AS marca_nombre,
                vm.id_vehiculo_marca AS marca_id
         FROM vehiculos_modelos m
         INNER JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = m.id_vehiculo_marca
          WHERE m.activo = 1
            AND vm.activo = 1
         ORDER BY vm.nombre_marca_v, m.nombre_modelo'
    )->fetchAll();
    $catalogs['motorizaciones'] = $pdo->query('SELECT id_motorizacion AS id, nombre_motor AS nombre FROM motorizaciones WHERE activo = 1 ORDER BY nombre_motor')->fetchAll();
    $catalogs['categorias'] = $pdo->query('SELECT id_categoria AS id, nombre_categoria AS nombre FROM categorias WHERE activo = 1 ORDER BY nombre_categoria')->fetchAll();
    $catalogs['unidades'] = $pdo->query('SELECT id_unidad AS id, CONCAT(nombre_unidad, " (", abreviatura, ")") AS nombre FROM unidades WHERE activo = 1 ORDER BY nombre_unidad')->fetchAll();
    $catalogs['proveedores'] = $pdo->query('SELECT id_proveedor AS id, razon_social AS nombre FROM proveedores WHERE activo = 1 ORDER BY razon_social')->fetchAll();

    $where = [];
    $params = [];

    if ($filters['nombre'] !== '') {
        $where[] = 'r.nombre LIKE :f_nombre';
        $params['f_nombre'] = '%' . $filters['nombre'] . '%';
    }

    if ($filters['marca_id'] > 0) {
        $where[] = 'r.id_marca = :f_marca_id';
        $params['f_marca_id'] = $filters['marca_id'];
    }

    if ($filters['categoria_id'] > 0) {
        $where[] = 'r.id_categoria = :f_categoria_id';
        $params['f_categoria_id'] = $filters['categoria_id'];
    }

    if ($filters['proveedor_id'] > 0) {
        $where[] = 'r.id_proveedor = :f_proveedor_id';
        $params['f_proveedor_id'] = $filters['proveedor_id'];
    }

    if ($filters['activo'] === '1' || $filters['activo'] === '0') {
        $where[] = 'r.activo = :f_activo';
        $params['f_activo'] = (int) $filters['activo'];
    }

    $sqlRepuestos =
        'SELECT r.id_repuesto,
                r.sku,
                r.cod_oem,
                r.nombre,
                r.precio_costo,
                r.stock_actual,
                r.stock_minimo,
                r.activo,
                ma.nombre_marca AS marca,
                vm.nombre_marca_v AS vehiculo_marca,
                vmo.nombre_modelo AS vehiculo_modelo,
                mot.nombre_motor AS motorizacion,
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
         LEFT JOIN motorizaciones mot ON mot.id_motorizacion = cv.id_motorizacion';

    if (count($where) > 0) {
        $sqlRepuestos .= ' WHERE ' . implode(' AND ', $where);
    }

    $sqlRepuestos .= ' ORDER BY r.id_repuesto DESC';

    $stmtRepuestos = $pdo->prepare($sqlRepuestos);
    $stmtRepuestos->execute($params);
    $repuestos = $stmtRepuestos->fetchAll();
} catch (Throwable $e) {
    $error = 'No fue posible cargar la pantalla de repuestos: ' . $e->getMessage();
    $repuestos = [];
}

$newRequested = isset($_GET['new']);
$editId = (int) ($_GET['edit'] ?? 0);
$deleteId = (int) ($_GET['delete'] ?? 0);

if ($newRequested) {
    $showForm = true;
    $formMode = 'new';
}

$selectedId = $editId > 0 ? $editId : $deleteId;
if ($selectedId > 0 && $error === '') {
    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, cv.id_modelo, cv.id_motorizacion,
                    vm.id_vehiculo_marca
             FROM repuestos r
             LEFT JOIN compatibilidad_vehiculos cv ON cv.id_repuesto = r.id_repuesto AND cv.activo = 1
             LEFT JOIN vehiculos_modelos m ON m.id_modelo = cv.id_modelo
             LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = m.id_vehiculo_marca
             WHERE r.id_repuesto = :id_repuesto
             LIMIT 1'
        );
        $stmt->execute(['id_repuesto' => $selectedId]);
        $selected = $stmt->fetch();

        if ($selected) {
            $showForm = true;
            $formMode = $deleteId > 0 ? 'delete' : 'edit';
            $form = [
                'id_repuesto' => (int) $selected['id_repuesto'],
                'sku' => (string) $selected['sku'],
                'cod_oem' => (string) ($selected['cod_oem'] ?? ''),
                'nombre' => (string) $selected['nombre'],
                'marca_id' => (string) ($selected['id_marca'] ?? ''),
                'vehiculo_marca_id' => (string) ($selected['id_vehiculo_marca'] ?? ''),
                'vehiculo_modelo_id' => (string) ($selected['id_modelo'] ?? ''),
                'motorizacion_id' => (string) ($selected['id_motorizacion'] ?? ''),
                'categoria_id' => (string) ($selected['id_categoria'] ?? ''),
                'unidad_id' => (string) ($selected['id_unidad'] ?? ''),
                'proveedor_id' => (string) ($selected['id_proveedor'] ?? ''),
                'precio_costo' => (string) ($selected['precio_costo'] ?? 0),
                'stock_actual' => (string) ($selected['stock_actual'] ?? 0),
                'stock_minimo' => (string) ($selected['stock_minimo'] ?? 5),
                'activo' => (string) $selected['activo'],
            ];
        }
    } catch (Throwable $e) {
        $error = 'No fue posible cargar el repuesto seleccionado: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repuestos - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 1200px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .btn-danger { background: #b91c1c; color: #fff; }
        .btn-icon { width: 42px; height: 42px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; border-radius: 10px; }
        .btn-icon:hover { filter: brightness(0.96); }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
        label { display: block; font-size: 0.9rem; margin-bottom: 0.25rem; font-weight: 600; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #c6d2e4; border-radius: 8px; padding: 0.55rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.55rem; text-align: left; font-size: 0.93rem; vertical-align: top; }
        .tag { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .ok { background: #dcfce7; color: #166534; }
        .off { background: #fee2e2; color: #991b1b; }
        .msg-error { background: #fee2e2; color: #991b1b; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-ok { background: #dcfce7; color: #166534; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .table-actions { display: flex; gap: 0.4rem; flex-wrap: nowrap; align-items: center; }
        .hint-box { background: #eef2ff; border: 1px solid #c7d2fe; color: #1e3a8a; padding: 0.8rem; border-radius: 10px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php
    $isDeleteMode = $formMode === 'delete';
    $formDisabledAttr = $isDeleteMode ? 'disabled' : '';
    $formActionValue = $isDeleteMode ? 'delete_confirm' : 'save';
    ?>
<div class="container">
    <div class="top">
        <div>
            <h1>Repuestos</h1>
            <p>Alta, modificacion y baja logica de repuestos.</p>
        </div>
        <div class="actions">
            <a class="btn btn-muted" href="/dashboard.php">Volver al panel</a>
            <a class="btn btn-muted" href="/compras.php?show_filters=1">Buscar</a>
            <a class="btn btn-primary" href="/compras.php?new=1">Nuevo</a>
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
        <h2>
            <?php if ($isDeleteMode): ?>Confirmar baja de repuesto<?php elseif ((int) $form['id_repuesto'] > 0): ?>Editar repuesto<?php else: ?>Nuevo repuesto<?php endif; ?>
        </h2>
        <form method="post" action="/compras.php">
            <input type="hidden" name="action" value="<?= $formActionValue ?>">
            <input type="hidden" name="id_repuesto" value="<?= (int) $form['id_repuesto'] ?>">

            <div class="grid">
                <div>
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" maxlength="50" required value="<?= htmlspecialchars((string) $form['sku']) ?>" <?= $formDisabledAttr ?>>
                </div>
                <div>
                    <label for="cod_oem">Código OEM</label>
                    <input id="cod_oem" name="cod_oem" maxlength="100" value="<?= htmlspecialchars((string) $form['cod_oem']) ?>" <?= $formDisabledAttr ?>>
                </div>
                <div>
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" maxlength="150" required value="<?= htmlspecialchars((string) $form['nombre']) ?>" <?= $formDisabledAttr ?>>
                </div>
                <div>
                    <label for="precio_costo">Precio costo</label>
                    <input id="precio_costo" name="precio_costo" type="number" step="0.01" min="0" required value="<?= htmlspecialchars((string) $form['precio_costo']) ?>" <?= $formDisabledAttr ?>>
                </div>
                <div>
                    <label for="stock_actual">Stock actual</label>
                    <input id="stock_actual" name="stock_actual" type="number" step="1" min="0" required value="<?= htmlspecialchars((string) $form['stock_actual']) ?>" <?= $formDisabledAttr ?>>
                </div>
                <div>
                    <label for="stock_minimo">Stock minimo</label>
                    <input id="stock_minimo" name="stock_minimo" type="number" step="1" min="0" required value="<?= htmlspecialchars((string) $form['stock_minimo']) ?>" <?= $formDisabledAttr ?>>
                </div>

                <div>
                    <label for="marca_id">Marca del repuesto</label>
                    <select id="marca_id" name="marca_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['marcas'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['marca_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="vehiculo_marca_id">Vehículo marca</label>
                    <select id="vehiculo_marca_id" name="vehiculo_marca_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['vehiculos_marcas'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['vehiculo_marca_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="vehiculo_modelo_id">Vehículo modelo</label>
                    <select id="vehiculo_modelo_id" name="vehiculo_modelo_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['vehiculos_modelos'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['vehiculo_modelo_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['marca_nombre'] . ' - ' . (string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="motorizacion_id">Motorización</label>
                    <select id="motorizacion_id" name="motorizacion_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['motorizaciones'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['motorizacion_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="categoria_id">Categoría</label>
                    <select id="categoria_id" name="categoria_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['categorias'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['categoria_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="unidad_id">Unidad</label>
                    <select id="unidad_id" name="unidad_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['unidades'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['unidad_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="proveedor_id">Proveedor</label>
                    <select id="proveedor_id" name="proveedor_id" required <?= $formDisabledAttr ?>>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($catalogs['proveedores'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (string) $form['proveedor_id'] === (string) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="activo" value="1" <?= (string) $form['activo'] === '1' ? 'checked' : '' ?> <?= $formDisabledAttr ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <?php if ($isDeleteMode): ?>
                    <button class="btn btn-danger" type="submit" onclick="return confirm('Se dara de baja el repuesto seleccionado. Continuar?');">Confirmar baja</button>
                <?php else: ?>
                    <button class="btn btn-primary" type="submit"><?= (int) $form['id_repuesto'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <?php endif; ?>
                <a class="btn btn-muted" href="/compras.php">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($showFilters): ?>
    <div class="panel">
        <h2>Filtros de busqueda</h2>
        <form method="get" action="/compras.php">
            <input type="hidden" name="show_filters" value="1">
            <div class="grid">
                <div>
                    <label for="f_nombre">Nombre</label>
                    <input id="f_nombre" name="f_nombre" value="<?= htmlspecialchars($filters['nombre']) ?>" placeholder="Nombre del repuesto">
                </div>
                <div>
                    <label for="f_marca_id">Marca</label>
                    <select id="f_marca_id" name="f_marca_id">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['marcas'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= $filters['marca_id'] === (int) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_categoria_id">Categoría</label>
                    <select id="f_categoria_id" name="f_categoria_id">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['categorias'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= $filters['categoria_id'] === (int) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_proveedor_id">Proveedor</label>
                    <select id="f_proveedor_id" name="f_proveedor_id">
                        <option value="0">Todos</option>
                        <?php foreach ($catalogs['proveedores'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= $filters['proveedor_id'] === (int) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_activo">Estado</label>
                    <select id="f_activo" name="f_activo">
                        <option value="" <?= $filters['activo'] === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $filters['activo'] === '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $filters['activo'] === '0' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>
            </div>
            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit">Buscar</button>
                <a class="btn btn-muted" href="/compras.php">Limpiar</a>
                <a class="btn btn-muted" href="/compras.php">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h2>Lista de repuestos</h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>SKU</th>
                <th>OEM</th>
                <th>Nombre</th>
                <th>Marca</th>
                <th>Vehículo</th>
                <th>Motor</th>
                <th>Categoría</th>
                <th>Unidad</th>
                <th>Proveedor</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($repuestos) === 0): ?>
                <tr>
                    <td colspan="13">No hay repuestos cargados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($repuestos as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_repuesto'] ?></td>
                        <td><?= htmlspecialchars((string) $row['sku']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['cod_oem'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['marca'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) (($row['vehiculo_marca'] ?? '-') . ' - ' . ($row['vehiculo_modelo'] ?? '-'))) ?></td>
                        <td><?= htmlspecialchars((string) ($row['motorizacion'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['categoria'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['unidad'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['proveedor'] ?? '')) ?></td>
                        <td><?= number_format((float) ($row['precio_costo'] ?? 0), 2, ',', '.') ?></td>
                        <td><?= (int) ($row['stock_actual'] ?? 0) ?></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-muted btn-icon" href="/compras.php?edit=<?= (int) $row['id_repuesto'] ?>" title="Editar" aria-label="Editar">
                                    &#9998;
                                    <span class="sr-only">Editar</span>
                                </a>
                                <?php if ((int) $row['activo'] === 1): ?>
                                    <a class="btn btn-danger btn-icon" href="/compras.php?delete=<?= (int) $row['id_repuesto'] ?>" title="Dar de baja" aria-label="Dar de baja">
                                        &#128465;
                                        <span class="sr-only">Dar de baja</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
