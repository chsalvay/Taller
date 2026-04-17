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

$form = [
    'id_cliente' => 0,
    'nombre' => '',
    'direccion' => '',
    'telefono' => '',
    'id_vehiculo_marca' => '',
    'id_modelo' => '',
    'patente' => '',
    'activo' => '1',
];

$filters = [
    'nombre' => trim((string) ($_GET['f_nombre'] ?? '')),
    'telefono' => trim((string) ($_GET['f_telefono'] ?? '')),
    'vehiculo_marca_id' => (int) ($_GET['f_vehiculo_marca_id'] ?? 0),
    'modelo_id' => (int) ($_GET['f_modelo_id'] ?? 0),
    'activo' => (string) ($_GET['f_activo'] ?? ''),
];

$hasActiveFilters =
    $filters['nombre'] !== '' ||
    $filters['telefono'] !== '' ||
    $filters['vehiculo_marca_id'] > 0 ||
    $filters['modelo_id'] > 0 ||
    ($filters['activo'] === '1' || $filters['activo'] === '0');

$showFilters = isset($_GET['show_filters']) || $hasActiveFilters;

try {
    $pdo = Database::connect($projectRoot);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clientes (
            id_cliente INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            direccion VARCHAR(200) NULL,
            telefono VARCHAR(30) NULL,
            id_vehiculo_marca INT NULL,
            id_modelo INT NULL,
            activo TINYINT(1) DEFAULT 1,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )'
    );
    $pdo->exec('ALTER TABLE clientes ADD COLUMN IF NOT EXISTS id_vehiculo_marca INT NULL AFTER telefono');
    $pdo->exec('ALTER TABLE clientes ADD COLUMN IF NOT EXISTS id_modelo INT NULL AFTER id_vehiculo_marca');
    $pdo->exec('ALTER TABLE clientes ADD COLUMN IF NOT EXISTS patente VARCHAR(20) NULL AFTER id_modelo');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $showForm = true;
        $idCliente = (int) ($_POST['id_cliente'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $idVehiculoMarca = (int) ($_POST['id_vehiculo_marca'] ?? 0);
        $idModelo = (int) ($_POST['id_modelo'] ?? 0);
        $patente = strtoupper(trim((string) ($_POST['patente'] ?? '')));
        $activo = isset($_POST['activo']) ? 1 : 0;

        $form = [
            'id_cliente' => $idCliente,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'id_vehiculo_marca' => $idVehiculoMarca > 0 ? (string) $idVehiculoMarca : '',
            'id_modelo' => $idModelo > 0 ? (string) $idModelo : '',
            'patente' => $patente,
            'activo' => (string) $activo,
        ];

        if ($nombre === '') {
            $error = 'Debes ingresar el nombre del cliente.';
        } elseif ($idVehiculoMarca <= 0 || $idModelo <= 0) {
            $error = 'Debes seleccionar vehículo marca y vehículo modelo.';
        } else {
            try {
                $stmtModelo = $pdo->prepare(
                    'SELECT 1
                                         FROM vehiculos_modelos m
                                         INNER JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = m.id_vehiculo_marca
                                         WHERE m.id_modelo = :id_modelo
                                             AND m.id_vehiculo_marca = :id_vehiculo_marca
                                             AND m.activo = 1
                                             AND vm.activo = 1
                     LIMIT 1'
                );
                $stmtModelo->execute([
                    'id_modelo' => $idModelo,
                    'id_vehiculo_marca' => $idVehiculoMarca,
                ]);

                if ($stmtModelo->fetchColumn() === false) {
                    throw new RuntimeException('El modelo seleccionado no pertenece a la marca elegida.');
                }

                $stmtDuplicado = $pdo->prepare(
                    'SELECT 1
                     FROM clientes
                     WHERE LOWER(nombre) = LOWER(:nombre)
                       AND COALESCE(LOWER(direccion), "") = COALESCE(LOWER(:direccion), "")
                       AND COALESCE(telefono, "") = COALESCE(:telefono, "")
                       AND id_vehiculo_marca = :id_vehiculo_marca
                       AND id_modelo = :id_modelo
                       AND id_cliente <> :id_cliente
                     LIMIT 1'
                );
                $stmtDuplicado->execute([
                    'nombre' => $nombre,
                    'direccion' => $direccion === '' ? null : $direccion,
                    'telefono' => $telefono === '' ? null : $telefono,
                    'id_vehiculo_marca' => $idVehiculoMarca,
                    'id_modelo' => $idModelo,
                    'id_cliente' => $idCliente,
                ]);

                if ($stmtDuplicado->fetchColumn() !== false) {
                    throw new RuntimeException('Ya existe un cliente con los mismos datos y vehículo asociado.');
                }

                if ($idCliente > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE clientes
                         SET nombre = :nombre,
                             direccion = :direccion,
                             telefono = :telefono,
                             id_vehiculo_marca = :id_vehiculo_marca,
                             id_modelo = :id_modelo,
                             patente = :patente,
                             activo = :activo
                         WHERE id_cliente = :id_cliente'
                    );
                    $stmt->execute([
                        'nombre' => $nombre,
                        'direccion' => $direccion === '' ? null : $direccion,
                        'telefono' => $telefono === '' ? null : $telefono,
                        'id_vehiculo_marca' => $idVehiculoMarca,
                        'id_modelo' => $idModelo,
                        'patente' => $patente === '' ? null : $patente,
                        'activo' => $activo,
                        'id_cliente' => $idCliente,
                    ]);
                    $success = 'Cliente actualizado correctamente.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO clientes (nombre, direccion, telefono, id_vehiculo_marca, id_modelo, patente, activo)
                         VALUES (:nombre, :direccion, :telefono, :id_vehiculo_marca, :id_modelo, :patente, :activo)'
                    );
                    $stmt->execute([
                        'nombre' => $nombre,
                        'direccion' => $direccion === '' ? null : $direccion,
                        'telefono' => $telefono === '' ? null : $telefono,
                        'id_vehiculo_marca' => $idVehiculoMarca,
                        'id_modelo' => $idModelo,
                        'patente' => $patente === '' ? null : $patente,
                        'activo' => $activo,
                    ]);
                    $success = 'Cliente creado correctamente.';
                }

                $form = [
                    'id_cliente' => 0,
                    'nombre' => '',
                    'direccion' => '',
                    'telefono' => '',
                    'id_vehiculo_marca' => '',
                    'id_modelo' => '',
                    'patente' => '',
                    'activo' => '1',
                ];
                $showForm = false;
                $formMode = 'new';
            } catch (Throwable $e) {
                $error = 'No fue posible guardar el cliente: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle') {
        $idCliente = (int) ($_POST['id_cliente'] ?? 0);
        $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

        if ($idCliente > 0) {
            try {
                $stmt = $pdo->prepare('UPDATE clientes SET activo = :activo WHERE id_cliente = :id_cliente');
                $stmt->execute([
                    'activo' => $nuevoActivo,
                    'id_cliente' => $idCliente,
                ]);

                $success = $nuevoActivo === 1
                    ? 'Cliente activado correctamente.'
                    : 'Cliente dado de baja correctamente.';
            } catch (Throwable $e) {
                $error = 'No fue posible actualizar el estado del cliente: ' . $e->getMessage();
            }
        }
    }
}

$newRequested = isset($_GET['new']);
if ($newRequested) {
    $showForm = true;
    $formMode = 'new';
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT id_cliente, nombre, direccion, telefono, id_vehiculo_marca, id_modelo, patente, activo
             FROM clientes
             WHERE id_cliente = :id_cliente
             LIMIT 1'
        );
        $stmt->execute(['id_cliente' => $editId]);
        $row = $stmt->fetch();

        if ($row) {
            $form = [
                'id_cliente' => (int) $row['id_cliente'],
                'nombre' => (string) $row['nombre'],
                'direccion' => (string) ($row['direccion'] ?? ''),
                'telefono' => (string) ($row['telefono'] ?? ''),
                'id_vehiculo_marca' => (string) ((int) ($row['id_vehiculo_marca'] ?? 0)),
                'id_modelo' => (string) ((int) ($row['id_modelo'] ?? 0)),
                'patente' => (string) ($row['patente'] ?? ''),
                'activo' => (string) ((int) $row['activo']),
            ];
            $showForm = true;
            $formMode = 'edit';
        }
    } catch (Throwable $e) {
        $error = 'No fue posible cargar el cliente seleccionado: ' . $e->getMessage();
    }
}

try {
    $vehiculosMarcas = $pdo->query(
        'SELECT id_vehiculo_marca, nombre_marca_v
         FROM vehiculos_marcas
            WHERE activo = 1
         ORDER BY nombre_marca_v ASC'
    )->fetchAll();

    $vehiculosModelos = $pdo->query(
           'SELECT m.id_modelo, m.id_vehiculo_marca, m.nombre_modelo
            FROM vehiculos_modelos m
            INNER JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = m.id_vehiculo_marca
            WHERE m.activo = 1
             AND vm.activo = 1
            ORDER BY m.nombre_modelo ASC'
    )->fetchAll();

    $where = [];
    $params = [];

    if ($filters['nombre'] !== '') {
        $where[] = 'c.nombre LIKE :f_nombre';
        $params['f_nombre'] = '%' . $filters['nombre'] . '%';
    }

    if ($filters['telefono'] !== '') {
        $where[] = 'COALESCE(c.telefono, "") LIKE :f_telefono';
        $params['f_telefono'] = '%' . $filters['telefono'] . '%';
    }

    if ($filters['vehiculo_marca_id'] > 0) {
        $where[] = 'c.id_vehiculo_marca = :f_vehiculo_marca_id';
        $params['f_vehiculo_marca_id'] = $filters['vehiculo_marca_id'];
    }

    if ($filters['modelo_id'] > 0) {
        $where[] = 'c.id_modelo = :f_modelo_id';
        $params['f_modelo_id'] = $filters['modelo_id'];
    }

    if ($filters['activo'] === '1' || $filters['activo'] === '0') {
        $where[] = 'c.activo = :f_activo';
        $params['f_activo'] = (int) $filters['activo'];
    }

    $sqlClientes =
        'SELECT c.id_cliente,
                c.nombre,
                c.direccion,
                c.telefono,
                c.patente,
                c.activo,
                vm.nombre_marca_v AS vehiculo_marca,
                vmo.nombre_modelo AS vehiculo_modelo
         FROM clientes c
         LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = c.id_vehiculo_marca
         LEFT JOIN vehiculos_modelos vmo ON vmo.id_modelo = c.id_modelo';

    if (count($where) > 0) {
        $sqlClientes .= ' WHERE ' . implode(' AND ', $where);
    }

    $sqlClientes .= ' ORDER BY c.id_cliente DESC';

    $stmtClientes = $pdo->prepare($sqlClientes);
    $stmtClientes->execute($params);
    $clientes = $stmtClientes->fetchAll();
} catch (Throwable $e) {
    $vehiculosMarcas = [];
    $vehiculosModelos = [];
    $clientes = [];
    $error = 'No fue posible cargar la lista de clientes: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 1200px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .btn-danger { background: #b91c1c; color: #fff; }
        .btn-small { padding: 0.45rem 0.7rem; border-radius: 8px; font-size: 0.86rem; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
        label { display: block; font-size: 0.9rem; margin-bottom: 0.25rem; font-weight: 600; }
        input[type="text"], select { width: 100%; box-sizing: border-box; border: 1px solid #c6d2e4; border-radius: 8px; padding: 0.55rem; }
        .check-label { display: inline-flex; align-items: center; gap: 0.45rem; margin-top: 1.6rem; font-weight: 600; }
        .check-label input[type="checkbox"] { width: auto; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.6rem; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.55rem; text-align: left; font-size: 0.93rem; vertical-align: top; }
        .tag { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .ok { background: #dcfce7; color: #166534; }
        .off { background: #fee2e2; color: #991b1b; }
        .msg-error { background: #fee2e2; color: #991b1b; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-ok { background: #dcfce7; color: #166534; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <div>
            <h1>Clientes</h1>
            <p>Alta, modificacion y baja logica de clientes.</p>
        </div>
        <div class="actions">
            <a class="btn btn-muted" href="/dashboard.php">Volver al panel</a>
            <a class="btn btn-muted" href="/clientes.php?show_filters=1">Buscar</a>
            <a class="btn btn-primary" href="/clientes.php?new=1">Nuevo</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="msg-ok"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($showFilters): ?>
    <div class="panel">
        <h2>Filtros de busqueda</h2>
        <form method="get" action="/clientes.php">
            <input type="hidden" name="show_filters" value="1">

            <div class="grid">
                <div>
                    <label for="f_nombre">Nombre</label>
                    <input id="f_nombre" name="f_nombre" value="<?= htmlspecialchars($filters['nombre']) ?>" placeholder="Nombre del cliente">
                </div>
                <div>
                    <label for="f_telefono">Teléfono</label>
                    <input id="f_telefono" name="f_telefono" value="<?= htmlspecialchars($filters['telefono']) ?>" placeholder="Teléfono del cliente">
                </div>
                <div>
                    <label for="f_vehiculo_marca_id">Vehículo marca</label>
                    <select id="f_vehiculo_marca_id" name="f_vehiculo_marca_id">
                        <option value="0">Todas</option>
                        <?php foreach ($vehiculosMarcas as $row): ?>
                            <option value="<?= (int) $row['id_vehiculo_marca'] ?>" <?= $filters['vehiculo_marca_id'] === (int) $row['id_vehiculo_marca'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre_marca_v']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_modelo_id">Vehículo modelo</label>
                    <select id="f_modelo_id" name="f_modelo_id">
                        <option value="0">Todos</option>
                        <?php foreach ($vehiculosModelos as $row): ?>
                            <option value="<?= (int) $row['id_modelo'] ?>" <?= $filters['modelo_id'] === (int) $row['id_modelo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre_modelo']) ?>
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
                <a class="btn btn-muted" href="/clientes.php">Limpiar</a>
                <a class="btn btn-muted" href="/clientes.php">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <div class="panel" id="clientes-form-panel">
        <h2><?= $formMode === 'edit' ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
        <form method="post" action="/clientes.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id_cliente" value="<?= (int) $form['id_cliente'] ?>">

            <div class="grid">
                <div>
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" maxlength="120" required value="<?= htmlspecialchars((string) $form['nombre']) ?>">
                </div>
                <div>
                    <label for="direccion">Dirección</label>
                    <input id="direccion" name="direccion" maxlength="200" value="<?= htmlspecialchars((string) $form['direccion']) ?>">
                </div>
                <div>
                    <label for="telefono">Teléfono</label>
                    <input id="telefono" name="telefono" maxlength="30" value="<?= htmlspecialchars((string) $form['telefono']) ?>">
                </div>
                <div>
                    <label for="id_vehiculo_marca">Vehículo marca</label>
                    <select id="id_vehiculo_marca" name="id_vehiculo_marca" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($vehiculosMarcas as $row): ?>
                            <option value="<?= (int) $row['id_vehiculo_marca'] ?>" <?= (string) $form['id_vehiculo_marca'] === (string) $row['id_vehiculo_marca'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre_marca_v']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="id_modelo">Vehículo modelo</label>
                    <select id="id_modelo" name="id_modelo" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($vehiculosModelos as $row): ?>
                            <option value="<?= (int) $row['id_modelo'] ?>" data-marca-id="<?= (int) $row['id_vehiculo_marca'] ?>" <?= (string) $form['id_modelo'] === (string) $row['id_modelo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre_modelo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="patente">Patente</label>
                    <input id="patente" name="patente" maxlength="20"
                           value="<?= htmlspecialchars((string) $form['patente']) ?>"
                           placeholder="Ej: AB 123 CD"
                           style="text-transform:uppercase">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $form['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $form['id_cliente'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/clientes.php">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="panel">
        <h2>Lista de clientes</h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Teléfono</th>
                <th>Vehículo marca</th>
                <th>Vehículo modelo</th>
                <th>Patente</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($clientes) === 0): ?>
                <tr><td colspan="8">No hay clientes cargados.</td></tr>
            <?php else: ?>
                <?php foreach ($clientes as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_cliente'] ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['direccion'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['telefono'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['vehiculo_marca'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['vehiculo_modelo'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['patente'] ?? '')) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/clientes.php?edit=<?= (int) $row['id_cliente'] ?>">Editar</a>
                                <form method="post" action="/clientes.php" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id_cliente" value="<?= (int) $row['id_cliente'] ?>">
                                    <input type="hidden" name="nuevo_activo" value="<?= (int) $row['activo'] === 1 ? 0 : 1 ?>">
                                    <button class="btn <?= (int) $row['activo'] === 1 ? 'btn-danger' : 'btn-primary' ?> btn-small" type="submit">
                                        <?= (int) $row['activo'] === 1 ? 'Dar de baja' : 'Activar' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    (function () {
        var marcaSelect = document.getElementById('id_vehiculo_marca');
        var modeloSelect = document.getElementById('id_modelo');

        if (!marcaSelect || !modeloSelect) {
            return;
        }

        function filtrarModelos() {
            var marcaId = marcaSelect.value;
            var hasVisibleOption = false;

            for (var i = 0; i < modeloSelect.options.length; i++) {
                var option = modeloSelect.options[i];
                var optionMarcaId = option.getAttribute('data-marca-id');

                if (!optionMarcaId) {
                    option.hidden = false;
                    continue;
                }

                var visible = marcaId !== '' && optionMarcaId === marcaId;
                option.hidden = !visible;

                if (visible) {
                    hasVisibleOption = true;
                }
            }

            var selected = modeloSelect.options[modeloSelect.selectedIndex];
            if (selected && selected.getAttribute('data-marca-id') !== marcaId) {
                modeloSelect.value = '';
            }

            if (!hasVisibleOption) {
                modeloSelect.value = '';
            }
        }

        marcaSelect.addEventListener('change', filtrarModelos);
        filtrarModelos();
    })();
</script>
</body>
</html>
