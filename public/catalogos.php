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

$seccion = (string) ($_GET['seccion'] ?? 'marca');
if (
    $seccion !== 'marca' &&
    $seccion !== 'vehiculo_marca' &&
    $seccion !== 'vehiculo_modelo' &&
    $seccion !== 'motorizacion' &&
    $seccion !== 'categoria' &&
    $seccion !== 'proveedor'
) {
    $seccion = 'marca';
}

if (isset($_SESSION['catalogos_success'])) {
    $success = (string) $_SESSION['catalogos_success'];
    unset($_SESSION['catalogos_success']);
}

if (isset($_SESSION['catalogos_error'])) {
    $error = (string) $_SESSION['catalogos_error'];
    unset($_SESSION['catalogos_error']);
}

$marcaForm = [
    'id_marca' => 0,
    'nombre_marca' => '',
    'activo' => '1',
];

$vehiculoMarcaForm = [
    'id_vehiculo_marca' => 0,
    'nombre_marca_v' => '',
    'activo' => '1',
];

$vehiculoModeloForm = [
    'id_modelo' => 0,
    'id_vehiculo_marca' => '',
    'nombre_modelo' => '',
    'activo' => '1',
];

$motorizacionForm = [
    'id_motorizacion' => 0,
    'nombre_motor' => '',
    'descripcion' => '',
    'activo' => '1',
];

$categoriaForm = [
    'id_categoria' => 0,
    'nombre_categoria' => '',
    'activo' => '1',
];

$proveedorForm = [
    'id_proveedor' => 0,
    'razon_social' => '',
    'cuit' => '',
    'activo' => '1',
];

try {
    $pdo = Database::connect($projectRoot);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

$hasDuplicate = static function ($pdo, string $sql, array $params): bool {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() !== false;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'marca_save') {
            $id = (int) ($_POST['id_marca'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_marca'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') {
                throw new RuntimeException('Debes ingresar el nombre de la marca de repuesto.');
            }

            if ($hasDuplicate(
                $pdo,
                'SELECT 1 FROM marcas WHERE LOWER(nombre_marca) = LOWER(:nombre) AND id_marca <> :id LIMIT 1',
                ['nombre' => $nombre, 'id' => $id]
            )) {
                throw new RuntimeException('Ya existe una marca de repuesto con ese nombre.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE marcas
                     SET nombre_marca = :nombre_marca,
                         activo = :activo
                     WHERE id_marca = :id_marca'
                );
                $stmt->execute([
                    'nombre_marca' => $nombre,
                    'activo' => $activo,
                    'id_marca' => $id,
                ]);
                $_SESSION['catalogos_success'] = 'Marca de repuesto actualizada correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO marcas (nombre_marca, activo)
                     VALUES (:nombre_marca, :activo)'
                );
                $stmt->execute([
                    'nombre_marca' => $nombre,
                    'activo' => $activo,
                ]);
                $_SESSION['catalogos_success'] = 'Marca de repuesto creada correctamente.';
            }

            header('Location: /catalogos.php?seccion=marca#marcas-panel');
            exit;
        }

        if ($action === 'marca_toggle') {
            $id = (int) ($_POST['id_marca'] ?? 0);
            $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('No se recibio una marca valida para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE marcas SET activo = :activo WHERE id_marca = :id_marca');
            $stmt->execute([
                'activo' => $nuevoActivo,
                'id_marca' => $id,
            ]);

            $_SESSION['catalogos_success'] = $nuevoActivo === 1
                ? 'Marca de repuesto activada correctamente.'
                : 'Marca de repuesto dada de baja correctamente.';

            header('Location: /catalogos.php?seccion=marca#marcas-panel');
            exit;
        }

        if ($action === 'vehiculo_marca_save') {
            $id = (int) ($_POST['id_vehiculo_marca'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_marca_v'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') {
                throw new RuntimeException('Debes ingresar el nombre de la marca de vehículo.');
            }

            if ($hasDuplicate(
                $pdo,
                'SELECT 1 FROM vehiculos_marcas WHERE LOWER(nombre_marca_v) = LOWER(:nombre) AND id_vehiculo_marca <> :id LIMIT 1',
                ['nombre' => $nombre, 'id' => $id]
            )) {
                throw new RuntimeException('Ya existe una marca de vehículo con ese nombre.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE vehiculos_marcas
                     SET nombre_marca_v = :nombre_marca_v,
                         activo = :activo
                     WHERE id_vehiculo_marca = :id_vehiculo_marca'
                );
                $stmt->execute([
                    'nombre_marca_v' => $nombre,
                    'activo' => $activo,
                    'id_vehiculo_marca' => $id,
                ]);
                $_SESSION['catalogos_success'] = 'Marca de vehículo actualizada correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO vehiculos_marcas (nombre_marca_v, activo)
                     VALUES (:nombre_marca_v, :activo)'
                );
                $stmt->execute([
                    'nombre_marca_v' => $nombre,
                    'activo' => $activo,
                ]);
                $_SESSION['catalogos_success'] = 'Marca de vehículo creada correctamente.';
            }

            header('Location: /catalogos.php?seccion=vehiculo_marca#vehiculos-marcas-panel');
            exit;
        }

        if ($action === 'vehiculo_marca_toggle') {
            $id = (int) ($_POST['id_vehiculo_marca'] ?? 0);
            $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('No se recibio una marca de vehículo valida para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE vehiculos_marcas SET activo = :activo WHERE id_vehiculo_marca = :id_vehiculo_marca');
            $stmt->execute([
                'activo' => $nuevoActivo,
                'id_vehiculo_marca' => $id,
            ]);

            $_SESSION['catalogos_success'] = $nuevoActivo === 1
                ? 'Marca de vehículo activada correctamente.'
                : 'Marca de vehículo dada de baja correctamente.';

            header('Location: /catalogos.php?seccion=vehiculo_marca#vehiculos-marcas-panel');
            exit;
        }

        if ($action === 'vehiculo_modelo_save') {
            $id = (int) ($_POST['id_modelo'] ?? 0);
            $idVehiculoMarca = (int) ($_POST['id_vehiculo_marca'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_modelo'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($idVehiculoMarca <= 0) {
                throw new RuntimeException('Debes seleccionar una marca de vehículo.');
            }

            if ($nombre === '') {
                throw new RuntimeException('Debes ingresar el nombre del modelo de vehículo.');
            }

            if ($hasDuplicate(
                $pdo,
                'SELECT 1
                 FROM vehiculos_modelos
                 WHERE id_vehiculo_marca = :id_vehiculo_marca
                   AND LOWER(nombre_modelo) = LOWER(:nombre)
                   AND id_modelo <> :id
                 LIMIT 1',
                [
                    'id_vehiculo_marca' => $idVehiculoMarca,
                    'nombre' => $nombre,
                    'id' => $id,
                ]
            )) {
                throw new RuntimeException('Ya existe un vehículo modelo con ese nombre para la marca seleccionada.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE vehiculos_modelos
                     SET id_vehiculo_marca = :id_vehiculo_marca,
                         nombre_modelo = :nombre_modelo,
                         activo = :activo
                     WHERE id_modelo = :id_modelo'
                );
                $stmt->execute([
                    'id_vehiculo_marca' => $idVehiculoMarca,
                    'nombre_modelo' => $nombre,
                    'activo' => $activo,
                    'id_modelo' => $id,
                ]);
                $_SESSION['catalogos_success'] = 'Vehículo modelo actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO vehiculos_modelos (id_vehiculo_marca, nombre_modelo, activo)
                     VALUES (:id_vehiculo_marca, :nombre_modelo, :activo)'
                );
                $stmt->execute([
                    'id_vehiculo_marca' => $idVehiculoMarca,
                    'nombre_modelo' => $nombre,
                    'activo' => $activo,
                ]);
                $_SESSION['catalogos_success'] = 'Vehículo modelo creado correctamente.';
            }

            header('Location: /catalogos.php?seccion=vehiculo_modelo#vehiculos-modelos-panel');
            exit;
        }

        if ($action === 'vehiculo_modelo_toggle') {
            $id = (int) ($_POST['id_modelo'] ?? 0);
            $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('No se recibio un modelo de vehículo valido para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE vehiculos_modelos SET activo = :activo WHERE id_modelo = :id_modelo');
            $stmt->execute([
                'activo' => $nuevoActivo,
                'id_modelo' => $id,
            ]);

            $_SESSION['catalogos_success'] = $nuevoActivo === 1
                ? 'Vehículo modelo activado correctamente.'
                : 'Vehículo modelo dado de baja correctamente.';

            header('Location: /catalogos.php?seccion=vehiculo_modelo#vehiculos-modelos-panel');
            exit;
        }

        if ($action === 'motorizacion_save') {
            $id = (int) ($_POST['id_motorizacion'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_motor'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') {
                throw new RuntimeException('Debes ingresar el nombre de la motorización.');
            }

            if ($hasDuplicate(
                $pdo,
                'SELECT 1 FROM motorizaciones WHERE LOWER(nombre_motor) = LOWER(:nombre) AND id_motorizacion <> :id LIMIT 1',
                ['nombre' => $nombre, 'id' => $id]
            )) {
                throw new RuntimeException('Ya existe una motorización con ese nombre.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE motorizaciones
                     SET nombre_motor = :nombre_motor,
                         descripcion = :descripcion,
                         activo = :activo
                     WHERE id_motorizacion = :id_motorizacion'
                );
                $stmt->execute([
                    'nombre_motor' => $nombre,
                    'descripcion' => $descripcion === '' ? null : $descripcion,
                    'activo' => $activo,
                    'id_motorizacion' => $id,
                ]);
                $_SESSION['catalogos_success'] = 'Motorización actualizada correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO motorizaciones (nombre_motor, descripcion, activo)
                     VALUES (:nombre_motor, :descripcion, :activo)'
                );
                $stmt->execute([
                    'nombre_motor' => $nombre,
                    'descripcion' => $descripcion === '' ? null : $descripcion,
                    'activo' => $activo,
                ]);
                $_SESSION['catalogos_success'] = 'Motorización creada correctamente.';
            }

            header('Location: /catalogos.php?seccion=motorizacion#motorizaciones-panel');
            exit;
        }

        if ($action === 'motorizacion_toggle') {
            $id = (int) ($_POST['id_motorizacion'] ?? 0);
            $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('No se recibio una motorización valida para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE motorizaciones SET activo = :activo WHERE id_motorizacion = :id_motorizacion');
            $stmt->execute([
                'activo' => $nuevoActivo,
                'id_motorizacion' => $id,
            ]);

            $_SESSION['catalogos_success'] = $nuevoActivo === 1
                ? 'Motorización activada correctamente.'
                : 'Motorización dada de baja correctamente.';

            header('Location: /catalogos.php?seccion=motorizacion#motorizaciones-panel');
            exit;
        }

        if ($action === 'categoria_save') {
            $id = (int) ($_POST['id_categoria'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_categoria'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') {
                throw new RuntimeException('Debes ingresar el nombre de la categoría.');
            }

            if ($hasDuplicate(
                $pdo,
                'SELECT 1 FROM categorias WHERE LOWER(nombre_categoria) = LOWER(:nombre) AND id_categoria <> :id LIMIT 1',
                ['nombre' => $nombre, 'id' => $id]
            )) {
                throw new RuntimeException('Ya existe una categoría con ese nombre.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE categorias
                     SET nombre_categoria = :nombre_categoria,
                         activo = :activo
                     WHERE id_categoria = :id_categoria'
                );
                $stmt->execute([
                    'nombre_categoria' => $nombre,
                    'activo' => $activo,
                    'id_categoria' => $id,
                ]);
                $_SESSION['catalogos_success'] = 'Categoría actualizada correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO categorias (nombre_categoria, activo)
                     VALUES (:nombre_categoria, :activo)'
                );
                $stmt->execute([
                    'nombre_categoria' => $nombre,
                    'activo' => $activo,
                ]);
                $_SESSION['catalogos_success'] = 'Categoría creada correctamente.';
            }

            header('Location: /catalogos.php?seccion=categoria#categorias-panel');
            exit;
        }

        if ($action === 'categoria_toggle') {
            $id = (int) ($_POST['id_categoria'] ?? 0);
            $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('No se recibio una categoría valida para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE categorias SET activo = :activo WHERE id_categoria = :id_categoria');
            $stmt->execute([
                'activo' => $nuevoActivo,
                'id_categoria' => $id,
            ]);

            $_SESSION['catalogos_success'] = $nuevoActivo === 1
                ? 'Categoría activada correctamente.'
                : 'Categoría dada de baja correctamente.';

            header('Location: /catalogos.php?seccion=categoria#categorias-panel');
            exit;
        }

        if ($action === 'proveedor_save') {
            $id = (int) ($_POST['id_proveedor'] ?? 0);
            $razonSocial = trim((string) ($_POST['razon_social'] ?? ''));
            $cuit = trim((string) ($_POST['cuit'] ?? ''));
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($razonSocial === '') {
                throw new RuntimeException('Debes ingresar la razon social del proveedor.');
            }

            if ($hasDuplicate(
                $pdo,
                'SELECT 1 FROM proveedores WHERE LOWER(razon_social) = LOWER(:razon_social) AND id_proveedor <> :id LIMIT 1',
                ['razon_social' => $razonSocial, 'id' => $id]
            )) {
                throw new RuntimeException('Ya existe un proveedor con esa razon social.');
            }

            if ($cuit !== '' && $hasDuplicate(
                $pdo,
                'SELECT 1 FROM proveedores WHERE cuit = :cuit AND id_proveedor <> :id LIMIT 1',
                ['cuit' => $cuit, 'id' => $id]
            )) {
                throw new RuntimeException('Ya existe un proveedor con ese CUIT.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE proveedores
                     SET razon_social = :razon_social,
                         cuit = :cuit,
                         activo = :activo
                     WHERE id_proveedor = :id_proveedor'
                );
                $stmt->execute([
                    'razon_social' => $razonSocial,
                    'cuit' => $cuit === '' ? null : $cuit,
                    'activo' => $activo,
                    'id_proveedor' => $id,
                ]);
                $_SESSION['catalogos_success'] = 'Proveedor actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO proveedores (razon_social, cuit, activo)
                     VALUES (:razon_social, :cuit, :activo)'
                );
                $stmt->execute([
                    'razon_social' => $razonSocial,
                    'cuit' => $cuit === '' ? null : $cuit,
                    'activo' => $activo,
                ]);
                $_SESSION['catalogos_success'] = 'Proveedor creado correctamente.';
            }

            header('Location: /catalogos.php?seccion=proveedor#proveedores-panel');
            exit;
        }

        if ($action === 'proveedor_toggle') {
            $id = (int) ($_POST['id_proveedor'] ?? 0);
            $nuevoActivo = (int) ($_POST['nuevo_activo'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('No se recibio un proveedor valido para actualizar.');
            }

            $stmt = $pdo->prepare('UPDATE proveedores SET activo = :activo WHERE id_proveedor = :id_proveedor');
            $stmt->execute([
                'activo' => $nuevoActivo,
                'id_proveedor' => $id,
            ]);

            $_SESSION['catalogos_success'] = $nuevoActivo === 1
                ? 'Proveedor activado correctamente.'
                : 'Proveedor dado de baja correctamente.';

            header('Location: /catalogos.php?seccion=proveedor#proveedores-panel');
            exit;
        }
    } catch (Throwable $e) {
        $_SESSION['catalogos_error'] = 'No fue posible completar la operacion: ' . $e->getMessage();

        if (str_starts_with($action, 'proveedor')) {
            header('Location: /catalogos.php?seccion=proveedor');
        } elseif (str_starts_with($action, 'categoria')) {
            header('Location: /catalogos.php?seccion=categoria');
        } elseif (str_starts_with($action, 'motorizacion')) {
            header('Location: /catalogos.php?seccion=motorizacion');
        } elseif (str_starts_with($action, 'vehiculo_modelo')) {
            header('Location: /catalogos.php?seccion=vehiculo_modelo');
        } elseif (str_starts_with($action, 'vehiculo_marca')) {
            header('Location: /catalogos.php?seccion=vehiculo_marca');
        } else {
            header('Location: /catalogos.php?seccion=marca');
        }
        exit;
    }
}

$editMarcaId = (int) ($_GET['edit_marca'] ?? 0);
$editVehiculoMarcaId = (int) ($_GET['edit_vehiculo_marca'] ?? 0);
$editVehiculoModeloId = (int) ($_GET['edit_vehiculo_modelo'] ?? 0);
$editMotorizacionId = (int) ($_GET['edit_motorizacion'] ?? 0);
$editCategoriaId = (int) ($_GET['edit_categoria'] ?? 0);
$editProveedorId = (int) ($_GET['edit_proveedor'] ?? 0);

if ($editMarcaId > 0) {
    $stmt = $pdo->prepare('SELECT id_marca, nombre_marca, activo FROM marcas WHERE id_marca = :id_marca LIMIT 1');
    $stmt->execute(['id_marca' => $editMarcaId]);
    $row = $stmt->fetch();

    if ($row) {
        $marcaForm = [
            'id_marca' => (int) $row['id_marca'],
            'nombre_marca' => (string) $row['nombre_marca'],
            'activo' => (string) ((int) $row['activo']),
        ];
    }
}

if ($editVehiculoMarcaId > 0) {
    $stmt = $pdo->prepare('SELECT id_vehiculo_marca, nombre_marca_v, activo FROM vehiculos_marcas WHERE id_vehiculo_marca = :id LIMIT 1');
    $stmt->execute(['id' => $editVehiculoMarcaId]);
    $row = $stmt->fetch();

    if ($row) {
        $vehiculoMarcaForm = [
            'id_vehiculo_marca' => (int) $row['id_vehiculo_marca'],
            'nombre_marca_v' => (string) $row['nombre_marca_v'],
            'activo' => (string) ((int) $row['activo']),
        ];
    }
}

if ($editVehiculoModeloId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id_modelo, id_vehiculo_marca, nombre_modelo, activo
         FROM vehiculos_modelos
         WHERE id_modelo = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editVehiculoModeloId]);
    $row = $stmt->fetch();

    if ($row) {
        $vehiculoModeloForm = [
            'id_modelo' => (int) $row['id_modelo'],
            'id_vehiculo_marca' => (string) ((int) $row['id_vehiculo_marca']),
            'nombre_modelo' => (string) $row['nombre_modelo'],
            'activo' => (string) ((int) $row['activo']),
        ];
    }
}

if ($editMotorizacionId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id_motorizacion, nombre_motor, descripcion, activo
         FROM motorizaciones
         WHERE id_motorizacion = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editMotorizacionId]);
    $row = $stmt->fetch();

    if ($row) {
        $motorizacionForm = [
            'id_motorizacion' => (int) $row['id_motorizacion'],
            'nombre_motor' => (string) $row['nombre_motor'],
            'descripcion' => (string) ($row['descripcion'] ?? ''),
            'activo' => (string) ((int) $row['activo']),
        ];
    }
}

if ($editCategoriaId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id_categoria, nombre_categoria, activo
         FROM categorias
         WHERE id_categoria = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editCategoriaId]);
    $row = $stmt->fetch();

    if ($row) {
        $categoriaForm = [
            'id_categoria' => (int) $row['id_categoria'],
            'nombre_categoria' => (string) $row['nombre_categoria'],
            'activo' => (string) ((int) $row['activo']),
        ];
    }
}

if ($editProveedorId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id_proveedor, razon_social, cuit, activo
         FROM proveedores
         WHERE id_proveedor = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editProveedorId]);
    $row = $stmt->fetch();

    if ($row) {
        $proveedorForm = [
            'id_proveedor' => (int) $row['id_proveedor'],
            'razon_social' => (string) $row['razon_social'],
            'cuit' => (string) ($row['cuit'] ?? ''),
            'activo' => (string) ((int) $row['activo']),
        ];
    }
}

$marcas = $pdo->query(
    'SELECT id_marca, nombre_marca, activo
     FROM marcas
     ORDER BY activo DESC, nombre_marca ASC'
)->fetchAll();

$vehiculosMarcas = $pdo->query(
    'SELECT id_vehiculo_marca, nombre_marca_v, activo
     FROM vehiculos_marcas
     ORDER BY activo DESC, nombre_marca_v ASC'
)->fetchAll();

$vehiculosMarcasActivas = $pdo->query(
    'SELECT id_vehiculo_marca, nombre_marca_v
     FROM vehiculos_marcas
     WHERE activo = 1
     ORDER BY nombre_marca_v ASC'
)->fetchAll();

$vehiculosModelos = $pdo->query(
    'SELECT vm.id_modelo,
            vm.id_vehiculo_marca,
            vm.nombre_modelo,
            vm.activo,
            vma.nombre_marca_v
     FROM vehiculos_modelos vm
     INNER JOIN vehiculos_marcas vma ON vma.id_vehiculo_marca = vm.id_vehiculo_marca
     ORDER BY vm.activo DESC, vma.nombre_marca_v ASC, vm.nombre_modelo ASC'
)->fetchAll();

$motorizaciones = $pdo->query(
    'SELECT id_motorizacion, nombre_motor, descripcion, activo
     FROM motorizaciones
     ORDER BY activo DESC, nombre_motor ASC'
)->fetchAll();

$categorias = $pdo->query(
    'SELECT id_categoria, nombre_categoria, activo
     FROM categorias
     ORDER BY activo DESC, nombre_categoria ASC'
)->fetchAll();

$proveedores = $pdo->query(
    'SELECT id_proveedor, razon_social, cuit, activo
     FROM proveedores
     ORDER BY activo DESC, razon_social ASC'
)->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogos - APP_TALLER</title>
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
            <h1>Catalogos</h1>
            <p>Selecciona una opcion y gestiona su CRUD.</p>
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

    <?php if ($seccion === 'marca'): ?>
    <div class="panel" id="marcas-panel">
        <h2>Marca Repuesto</h2>
        <form method="post" action="/catalogos.php">
            <input type="hidden" name="action" value="marca_save">
            <input type="hidden" name="id_marca" value="<?= (int) $marcaForm['id_marca'] ?>">

            <div class="grid">
                <div>
                    <label for="nombre_marca">Nombre</label>
                    <input id="nombre_marca" name="nombre_marca" maxlength="100" required value="<?= htmlspecialchars((string) $marcaForm['nombre_marca']) ?>">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $marcaForm['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $marcaForm['id_marca'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/catalogos.php?seccion=marca#marcas-panel">Cancelar</a>
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($marcas) === 0): ?>
                <tr><td colspan="4">No hay marcas de repuesto cargadas.</td></tr>
            <?php else: ?>
                <?php foreach ($marcas as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_marca'] ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre_marca']) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/catalogos.php?seccion=marca&edit_marca=<?= (int) $row['id_marca'] ?>#marcas-panel">Editar</a>
                                <form method="post" action="/catalogos.php#marcas-panel" style="display:inline;">
                                    <input type="hidden" name="action" value="marca_toggle">
                                    <input type="hidden" name="id_marca" value="<?= (int) $row['id_marca'] ?>">
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
    <?php endif; ?>

    <?php if ($seccion === 'vehiculo_marca'): ?>
    <div class="panel" id="vehiculos-marcas-panel">
        <h2>Vehículo Marca</h2>
        <form method="post" action="/catalogos.php">
            <input type="hidden" name="action" value="vehiculo_marca_save">
            <input type="hidden" name="id_vehiculo_marca" value="<?= (int) $vehiculoMarcaForm['id_vehiculo_marca'] ?>">

            <div class="grid">
                <div>
                    <label for="nombre_marca_v">Nombre</label>
                    <input id="nombre_marca_v" name="nombre_marca_v" maxlength="100" required value="<?= htmlspecialchars((string) $vehiculoMarcaForm['nombre_marca_v']) ?>">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $vehiculoMarcaForm['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $vehiculoMarcaForm['id_vehiculo_marca'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/catalogos.php?seccion=vehiculo_marca#vehiculos-marcas-panel">Cancelar</a>
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($vehiculosMarcas) === 0): ?>
                <tr><td colspan="4">No hay marcas de vehículo cargadas.</td></tr>
            <?php else: ?>
                <?php foreach ($vehiculosMarcas as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_vehiculo_marca'] ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre_marca_v']) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/catalogos.php?seccion=vehiculo_marca&edit_vehiculo_marca=<?= (int) $row['id_vehiculo_marca'] ?>#vehiculos-marcas-panel">Editar</a>
                                <form method="post" action="/catalogos.php#vehiculos-marcas-panel" style="display:inline;">
                                    <input type="hidden" name="action" value="vehiculo_marca_toggle">
                                    <input type="hidden" name="id_vehiculo_marca" value="<?= (int) $row['id_vehiculo_marca'] ?>">
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
    <?php endif; ?>

    <?php if ($seccion === 'vehiculo_modelo'): ?>
    <div class="panel" id="vehiculos-modelos-panel">
        <h2>Vehículo Modelo</h2>
        <form method="post" action="/catalogos.php">
            <input type="hidden" name="action" value="vehiculo_modelo_save">
            <input type="hidden" name="id_modelo" value="<?= (int) $vehiculoModeloForm['id_modelo'] ?>">

            <div class="grid">
                <div>
                    <label for="id_vehiculo_marca">Vehículo marca</label>
                    <select id="id_vehiculo_marca" name="id_vehiculo_marca" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($vehiculosMarcasActivas as $row): ?>
                            <option value="<?= (int) $row['id_vehiculo_marca'] ?>" <?= (string) $vehiculoModeloForm['id_vehiculo_marca'] === (string) $row['id_vehiculo_marca'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre_marca_v']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nombre_modelo">Nombre</label>
                    <input id="nombre_modelo" name="nombre_modelo" maxlength="100" required value="<?= htmlspecialchars((string) $vehiculoModeloForm['nombre_modelo']) ?>">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $vehiculoModeloForm['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $vehiculoModeloForm['id_modelo'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/catalogos.php?seccion=vehiculo_modelo#vehiculos-modelos-panel">Cancelar</a>
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Marca</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($vehiculosModelos) === 0): ?>
                <tr><td colspan="5">No hay modelos de vehículo cargados.</td></tr>
            <?php else: ?>
                <?php foreach ($vehiculosModelos as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_modelo'] ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre_marca_v']) ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre_modelo']) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/catalogos.php?seccion=vehiculo_modelo&edit_vehiculo_modelo=<?= (int) $row['id_modelo'] ?>#vehiculos-modelos-panel">Editar</a>
                                <form method="post" action="/catalogos.php#vehiculos-modelos-panel" style="display:inline;">
                                    <input type="hidden" name="action" value="vehiculo_modelo_toggle">
                                    <input type="hidden" name="id_modelo" value="<?= (int) $row['id_modelo'] ?>">
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
    <?php endif; ?>

    <?php if ($seccion === 'motorizacion'): ?>
    <div class="panel" id="motorizaciones-panel">
        <h2>Motorización</h2>
        <form method="post" action="/catalogos.php">
            <input type="hidden" name="action" value="motorizacion_save">
            <input type="hidden" name="id_motorizacion" value="<?= (int) $motorizacionForm['id_motorizacion'] ?>">

            <div class="grid">
                <div>
                    <label for="nombre_motor">Nombre</label>
                    <input id="nombre_motor" name="nombre_motor" maxlength="100" required value="<?= htmlspecialchars((string) $motorizacionForm['nombre_motor']) ?>">
                </div>
                <div>
                    <label for="descripcion">Descripcion</label>
                    <input id="descripcion" name="descripcion" maxlength="255" value="<?= htmlspecialchars((string) $motorizacionForm['descripcion']) ?>">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $motorizacionForm['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $motorizacionForm['id_motorizacion'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/catalogos.php?seccion=motorizacion#motorizaciones-panel">Cancelar</a>
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripcion</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($motorizaciones) === 0): ?>
                <tr><td colspan="5">No hay motorizaciones cargadas.</td></tr>
            <?php else: ?>
                <?php foreach ($motorizaciones as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_motorizacion'] ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre_motor']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['descripcion'] ?? '')) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/catalogos.php?seccion=motorizacion&edit_motorizacion=<?= (int) $row['id_motorizacion'] ?>#motorizaciones-panel">Editar</a>
                                <form method="post" action="/catalogos.php#motorizaciones-panel" style="display:inline;">
                                    <input type="hidden" name="action" value="motorizacion_toggle">
                                    <input type="hidden" name="id_motorizacion" value="<?= (int) $row['id_motorizacion'] ?>">
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
    <?php endif; ?>

    <?php if ($seccion === 'categoria'): ?>
    <div class="panel" id="categorias-panel">
        <h2>Categoría</h2>
        <form method="post" action="/catalogos.php">
            <input type="hidden" name="action" value="categoria_save">
            <input type="hidden" name="id_categoria" value="<?= (int) $categoriaForm['id_categoria'] ?>">

            <div class="grid">
                <div>
                    <label for="nombre_categoria">Nombre</label>
                    <input id="nombre_categoria" name="nombre_categoria" maxlength="100" required value="<?= htmlspecialchars((string) $categoriaForm['nombre_categoria']) ?>">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $categoriaForm['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $categoriaForm['id_categoria'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/catalogos.php?seccion=categoria#categorias-panel">Cancelar</a>
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($categorias) === 0): ?>
                <tr><td colspan="4">No hay categorías cargadas.</td></tr>
            <?php else: ?>
                <?php foreach ($categorias as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_categoria'] ?></td>
                        <td><?= htmlspecialchars((string) $row['nombre_categoria']) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/catalogos.php?seccion=categoria&edit_categoria=<?= (int) $row['id_categoria'] ?>#categorias-panel">Editar</a>
                                <form method="post" action="/catalogos.php#categorias-panel" style="display:inline;">
                                    <input type="hidden" name="action" value="categoria_toggle">
                                    <input type="hidden" name="id_categoria" value="<?= (int) $row['id_categoria'] ?>">
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
    <?php endif; ?>

    <?php if ($seccion === 'proveedor'): ?>
    <div class="panel" id="proveedores-panel">
        <h2>Proveedor</h2>
        <form method="post" action="/catalogos.php">
            <input type="hidden" name="action" value="proveedor_save">
            <input type="hidden" name="id_proveedor" value="<?= (int) $proveedorForm['id_proveedor'] ?>">

            <div class="grid">
                <div>
                    <label for="razon_social">Razon social</label>
                    <input id="razon_social" name="razon_social" maxlength="150" required value="<?= htmlspecialchars((string) $proveedorForm['razon_social']) ?>">
                </div>
                <div>
                    <label for="cuit">CUIT</label>
                    <input id="cuit" name="cuit" maxlength="20" value="<?= htmlspecialchars((string) $proveedorForm['cuit']) ?>">
                </div>
                <div>
                    <label class="check-label">
                        <input type="checkbox" name="activo" value="1" <?= (string) $proveedorForm['activo'] === '1' ? 'checked' : '' ?>>
                        Activo
                    </label>
                </div>
            </div>

            <div class="actions" style="margin-top: 0.9rem;">
                <button class="btn btn-primary" type="submit"><?= (int) $proveedorForm['id_proveedor'] > 0 ? 'Guardar cambios' : 'Guardar nuevo' ?></button>
                <a class="btn btn-muted" href="/catalogos.php?seccion=proveedor#proveedores-panel">Cancelar</a>
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Razon social</th>
                <th>CUIT</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($proveedores) === 0): ?>
                <tr><td colspan="5">No hay proveedores cargados.</td></tr>
            <?php else: ?>
                <?php foreach ($proveedores as $row): ?>
                    <tr>
                        <td><?= (int) $row['id_proveedor'] ?></td>
                        <td><?= htmlspecialchars((string) $row['razon_social']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['cuit'] ?? '')) ?></td>
                        <td>
                            <span class="tag <?= (int) $row['activo'] === 1 ? 'ok' : 'off' ?>">
                                <?= (int) $row['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-muted btn-small" href="/catalogos.php?seccion=proveedor&edit_proveedor=<?= (int) $row['id_proveedor'] ?>#proveedores-panel">Editar</a>
                                <form method="post" action="/catalogos.php#proveedores-panel" style="display:inline;">
                                    <input type="hidden" name="action" value="proveedor_toggle">
                                    <input type="hidden" name="id_proveedor" value="<?= (int) $row['id_proveedor'] ?>">
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
    <?php endif; ?>
</div>
</body>
</html>
