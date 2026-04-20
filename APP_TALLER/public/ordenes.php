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
$showForm = isset($_GET['nueva']) || isset($_GET['edit']);
$editId   = (int) ($_GET['edit'] ?? 0);
if (isset($_GET['nueva']) && !isset($_GET['edit'])) {
    $editId = 0;
}

$filters = [
    'cliente'    => trim((string) ($_GET['f_cliente'] ?? '')),
    'patente'    => trim((string) ($_GET['f_patente'] ?? '')),
    'estado'     => (string) ($_GET['f_estado'] ?? ''),
    'fecha_desde'=> trim((string) ($_GET['f_fecha_desde'] ?? '')),
    'fecha_hasta'=> trim((string) ($_GET['f_fecha_hasta'] ?? '')),
];
$hasActiveFilters =
    $filters['cliente'] !== '' ||
    $filters['patente'] !== '' ||
    $filters['estado'] !== '' ||
    $filters['fecha_desde'] !== '' ||
    $filters['fecha_hasta'] !== '';
$showFilters = isset($_GET['show_filters']) || $hasActiveFilters;

// Datos precargados para edición
$formOrden = [
    'id'         => 0,
    'id_cliente' => 0,
    'fecha_ot'   => date('Y-m-d'),
    'descripcion'=> '',
    'estado'     => 'abierta',
];
$formDetalle = []; // [['id_repuesto'=>X,'cantidad'=>Y],['desc_libre'=>'...']]

try {
    $pdo = Database::connect($projectRoot);

    // Asegurar columnas nuevas en ordenes_trabajo
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ordenes_trabajo'")->fetchAll(\PDO::FETCH_COLUMN);
    if (!in_array('id_cliente', $cols)) $pdo->exec('ALTER TABLE ordenes_trabajo ADD COLUMN id_cliente INT NULL AFTER id');
    if (!in_array('fecha_ot', $cols)) $pdo->exec('ALTER TABLE ordenes_trabajo ADD COLUMN fecha_ot DATE NULL AFTER descripcion');

    // Crear tabla detalle si no existe
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS ordenes_trabajo_detalle (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_orden INT UNSIGNED NOT NULL,
            id_repuesto INT NULL,
            descripcion_libre VARCHAR(255) NULL,
            cantidad INT UNSIGNED NOT NULL DEFAULT 1,
            CONSTRAINT fk_otd_orden FOREIGN KEY (id_orden) REFERENCES ordenes_trabajo(id) ON DELETE CASCADE,
            CONSTRAINT fk_otd_repuesto FOREIGN KEY (id_repuesto) REFERENCES repuestos(id_repuesto) ON DELETE SET NULL
        )
    ');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage());
    exit;
}

// ── GUARDAR NUEVA ORDEN ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_orden') {
    $idCliente  = (int) ($_POST['id_cliente'] ?? 0);
    $patente    = strtoupper(trim((string) ($_POST['patente'] ?? '')));
    $fechaOT    = trim((string) ($_POST['fecha_ot'] ?? date('Y-m-d')));
    $descripciones = array_filter(array_map('trim', (array) ($_POST['descripciones'] ?? [])));
    $descripcion = implode(' | ', $descripciones);
    $repuestosIds = array_map('intval', (array) ($_POST['repuestos'] ?? []));
    $libres       = array_filter(array_map('trim', (array) ($_POST['items_libres'] ?? [])));

    // Obtener nombre, vehículo y patente desde la base según el cliente seleccionado
    $clienteNom = '';
    $vehiculo   = '';
    if ($idCliente > 0) {
        $stmtCli = $pdo->prepare('
            SELECT c.nombre,
                   COALESCE(vm.nombre_marca_v,\'\') AS marca,
                   COALESCE(mo.nombre_modelo,\'\') AS modelo,
                   COALESCE(c.patente,\'\') AS patente
            FROM clientes c
            LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = c.id_vehiculo_marca
            LEFT JOIN vehiculos_modelos mo ON mo.id_modelo = c.id_modelo
            WHERE c.id_cliente = :id AND c.activo = 1
        ');
        $stmtCli->execute([':id' => $idCliente]);
        $rowCli = $stmtCli->fetch(\PDO::FETCH_ASSOC);
        if ($rowCli) {
            $clienteNom = $rowCli['nombre'];
            $vehiculo   = trim($rowCli['marca'] . ' ' . $rowCli['modelo']);
            if ($patente === '') {
                $patente = strtoupper($rowCli['patente']);
            }
        }
    }

    if ($idCliente <= 0 || $clienteNom === '' || $patente === '') {
        $error = 'Seleccioná un cliente y completá la patente.';
        $showForm = true;
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('
                INSERT INTO ordenes_trabajo (id_cliente, cliente, vehiculo, patente, descripcion, estado, fecha_ot)
                VALUES (:id_cliente, :cliente, :vehiculo, :patente, :descripcion, \'abierta\', :fecha_ot)
            ');
            $stmt->execute([
                ':id_cliente'  => $idCliente ?: null,
                ':cliente'     => $clienteNom,
                ':vehiculo'    => $vehiculo,
                ':patente'     => $patente,
                ':descripcion' => $descripcion ?: null,
                ':fecha_ot'    => $fechaOT ?: date('Y-m-d'),
            ]);
            $idOrden = (int) $pdo->lastInsertId();

            $stmtDet = $pdo->prepare('
                INSERT INTO ordenes_trabajo_detalle (id_orden, id_repuesto, descripcion_libre, cantidad)
                VALUES (:id_orden, :id_repuesto, :desc_libre, :cantidad)
            ');
            $stmtStock = $pdo->prepare('
                UPDATE repuestos SET stock_actual = stock_actual - :cant
                WHERE id_repuesto = :id AND stock_actual >= :cant_check
            ');

            foreach ($repuestosIds as $idRep) {
                if ($idRep > 0) {
                    $cantKey = 'cant_rep_' . $idRep;
                    $cant = max(1, (int) ($_POST[$cantKey] ?? 1));
                    $stmtDet->execute([
                        ':id_orden'   => $idOrden,
                        ':id_repuesto' => $idRep,
                        ':desc_libre' => null,
                        ':cantidad'   => $cant,
                    ]);
                    $stmtStock->execute([':cant' => $cant, ':cant_check' => $cant, ':id' => $idRep]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new \RuntimeException('Stock insuficiente para el repuesto ID ' . $idRep . '.');
                    }
                }
            }

            foreach ($libres as $linea) {
                if ($linea !== '') {
                    $stmtDet->execute([
                        ':id_orden'    => $idOrden,
                        ':id_repuesto' => null,
                        ':desc_libre'  => substr($linea, 0, 255),
                        ':cantidad'    => 1,
                    ]);
                }
            }

            $pdo->commit();
            $success  = 'Orden de trabajo #' . $idOrden . ' creada correctamente.';
            $showForm = false;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error    = 'Error al guardar: ' . htmlspecialchars($e->getMessage());
            $showForm = true;
        }
    }
}

// ── ACTUALIZAR ORDEN EXISTENTE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_orden') {
    $idOrdenEdit = (int) ($_POST['id_orden'] ?? 0);
    $idCliente   = (int) ($_POST['id_cliente'] ?? 0);
    $fechaOT     = trim((string) ($_POST['fecha_ot'] ?? date('Y-m-d')));
    $estado      = in_array($_POST['estado'] ?? '', ['abierta','en_progreso','cerrada']) ? $_POST['estado'] : 'abierta';
    $descripciones = array_filter(array_map('trim', (array) ($_POST['descripciones'] ?? [])));
    $descripcion = implode(' | ', $descripciones);
    $repuestosIds = array_map('intval', (array) ($_POST['repuestos'] ?? []));
    $libres       = array_filter(array_map('trim', (array) ($_POST['items_libres'] ?? [])));

    $clienteNom = '';
    $vehiculo   = '';
    $patente    = '';
    if ($idCliente > 0) {
        $stmtCli = $pdo->prepare('
            SELECT c.nombre,
                   COALESCE(vm.nombre_marca_v,\'\') AS marca,
                   COALESCE(mo.nombre_modelo,\'\') AS modelo,
                   COALESCE(c.patente,\'\') AS patente
            FROM clientes c
            LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = c.id_vehiculo_marca
            LEFT JOIN vehiculos_modelos mo ON mo.id_modelo = c.id_modelo
            WHERE c.id_cliente = :id AND c.activo = 1
        ');
        $stmtCli->execute([':id' => $idCliente]);
        $rowCli = $stmtCli->fetch(\PDO::FETCH_ASSOC);
        if ($rowCli) {
            $clienteNom = $rowCli['nombre'];
            $vehiculo   = trim($rowCli['marca'] . ' ' . $rowCli['modelo']);
            $patente    = strtoupper($rowCli['patente']);
        }
    }

    if ($idOrdenEdit <= 0 || $idCliente <= 0 || $clienteNom === '') {
        $error = 'Datos inválidos para actualizar la orden.';
        $showForm = true;
        $editId   = $idOrdenEdit;
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('
                UPDATE ordenes_trabajo
                SET id_cliente=:id_cliente, cliente=:cliente, vehiculo=:vehiculo,
                    patente=:patente, descripcion=:descripcion, estado=:estado, fecha_ot=:fecha_ot
                WHERE id=:id
            ')->execute([
                ':id_cliente'  => $idCliente,
                ':cliente'     => $clienteNom,
                ':vehiculo'    => $vehiculo,
                ':patente'     => $patente,
                ':descripcion' => $descripcion ?: null,
                ':estado'      => $estado,
                ':fecha_ot'    => $fechaOT ?: date('Y-m-d'),
                ':id'          => $idOrdenEdit,
            ]);

            // Borrar detalles anteriores: primero restituir stock previo
            $oldDets = $pdo->prepare('SELECT id_repuesto, cantidad FROM ordenes_trabajo_detalle WHERE id_orden = :id AND id_repuesto IS NOT NULL');
            $oldDets->execute([':id' => $idOrdenEdit]);
            $stmtRestoreStock = $pdo->prepare('UPDATE repuestos SET stock_actual = stock_actual + :cant WHERE id_repuesto = :id');
            foreach ($oldDets->fetchAll(\PDO::FETCH_ASSOC) as $old) {
                $stmtRestoreStock->execute([':cant' => (int)$old['cantidad'], ':id' => (int)$old['id_repuesto']]);
            }

            $pdo->prepare('DELETE FROM ordenes_trabajo_detalle WHERE id_orden = :id')->execute([':id' => $idOrdenEdit]);

            $stmtDet = $pdo->prepare('
                INSERT INTO ordenes_trabajo_detalle (id_orden, id_repuesto, descripcion_libre, cantidad)
                VALUES (:id_orden, :id_repuesto, :desc_libre, :cantidad)
            ');
            $stmtStock = $pdo->prepare('
                UPDATE repuestos SET stock_actual = stock_actual - :cant
                WHERE id_repuesto = :id AND stock_actual >= :cant_check
            ');

            foreach ($repuestosIds as $idRep) {
                if ($idRep > 0) {
                    $cant = max(1, (int) ($_POST['cant_rep_' . $idRep] ?? 1));
                    $stmtDet->execute([':id_orden'=>$idOrdenEdit,':id_repuesto'=>$idRep,':desc_libre'=>null,':cantidad'=>$cant]);
                    $stmtStock->execute([':cant' => $cant, ':cant_check' => $cant, ':id' => $idRep]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new \RuntimeException('Stock insuficiente para el repuesto ID ' . $idRep . '.');
                    }
                }
            }
            foreach ($libres as $linea) {
                if ($linea !== '') {
                    $stmtDet->execute([':id_orden'=>$idOrdenEdit,':id_repuesto'=>null,':desc_libre'=>substr($linea,0,255),':cantidad'=>1]);
                }
            }

            $pdo->commit();
            $success  = 'Orden #' . $idOrdenEdit . ' actualizada correctamente.';
            $showForm = false;
            $editId   = 0;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error    = 'Error al actualizar: ' . htmlspecialchars($e->getMessage());
            $showForm = true;
            $editId   = $idOrdenEdit;
        }
    }
}

// ── CARGAR DATOS PARA EDICIÓN ──────────────────────────────────────────────
if ($editId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $rowOrd = $pdo->prepare('SELECT * FROM ordenes_trabajo WHERE id=:id LIMIT 1');
        $rowOrd->execute([':id' => $editId]);
        $rowOrd = $rowOrd->fetch(\PDO::FETCH_ASSOC);
        if ($rowOrd) {
            $formOrden = [
                'id'          => (int)$rowOrd['id'],
                'id_cliente'  => (int)($rowOrd['id_cliente'] ?? 0),
                'fecha_ot'    => $rowOrd['fecha_ot'] ?? date('Y-m-d'),
                'descripcion' => $rowOrd['descripcion'] ?? '',
                'estado'      => $rowOrd['estado'],
            ];
            $rowsDet = $pdo->prepare('SELECT * FROM ordenes_trabajo_detalle WHERE id_orden=:id ORDER BY id');
            $rowsDet->execute([':id' => $editId]);
            $formDetalle = $rowsDet->fetchAll(\PDO::FETCH_ASSOC);
        }
    } catch (Throwable) {}
}


$clientes  = [];
$repuestos = [];
try {
    $clientes = $pdo->query('
        SELECT c.id_cliente, c.nombre, c.patente,
               c.id_vehiculo_marca,
               COALESCE(vm.nombre_marca_v, \'\') AS marca,
               COALESCE(mo.nombre_modelo, \'\') AS modelo
        FROM clientes c
        LEFT JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = c.id_vehiculo_marca
        LEFT JOIN vehiculos_modelos mo ON mo.id_modelo = c.id_modelo
        WHERE c.activo = 1
        ORDER BY c.nombre
    ')->fetchAll(\PDO::FETCH_ASSOC);
    $repuestos = $pdo->query('
        SELECT r.id_repuesto, r.sku, r.nombre, cat.nombre_categoria, r.stock_actual,
               GROUP_CONCAT(DISTINCT vmo.id_vehiculo_marca) AS marcas_compat
        FROM repuestos r
        LEFT JOIN categorias cat ON cat.id_categoria = r.id_categoria
        LEFT JOIN compatibilidad_vehiculos cv ON cv.id_repuesto = r.id_repuesto
        LEFT JOIN vehiculos_modelos vmo ON vmo.id_modelo = cv.id_modelo
        WHERE r.activo = 1
        GROUP BY r.id_repuesto, r.sku, r.nombre, cat.nombre_categoria, r.stock_actual
        ORDER BY cat.nombre_categoria, r.nombre
    ')->fetchAll(\PDO::FETCH_ASSOC);
} catch (Throwable) {}

// ── LISTA DE ORDENES ───────────────────────────────────────────────────────
$ordenes = [];
try {
    $where  = [];
    $params = [];
    if ($filters['cliente'] !== '') {
        $where[] = '(o.cliente LIKE :f_cliente OR o.patente LIKE :f_cliente2)';
        $params['f_cliente']  = '%' . $filters['cliente'] . '%';
        $params['f_cliente2'] = '%' . $filters['cliente'] . '%';
    }
    if ($filters['patente'] !== '') {
        $where[] = 'o.patente LIKE :f_patente';
        $params['f_patente'] = '%' . $filters['patente'] . '%';
    }
    if ($filters['estado'] !== '') {
        $where[] = 'o.estado = :f_estado';
        $params['f_estado'] = $filters['estado'];
    }
    if ($filters['fecha_desde'] !== '') {
        $where[] = 'o.fecha_ot >= :f_fecha_desde';
        $params['f_fecha_desde'] = $filters['fecha_desde'];
    }
    if ($filters['fecha_hasta'] !== '') {
        $where[] = 'o.fecha_ot <= :f_fecha_hasta';
        $params['f_fecha_hasta'] = $filters['fecha_hasta'];
    }
    // Excluir siempre las órdenes cerradas (van a "Órdenes terminadas")
    if ($filters['estado'] === '') {
        $where[] = "o.estado != 'cerrada'";
    }

    $sql = '
        SELECT o.id, o.cliente, o.vehiculo, o.patente, o.estado, o.fecha_ot,
               COUNT(d.id) AS cant_items
        FROM ordenes_trabajo o
        LEFT JOIN ordenes_trabajo_detalle d ON d.id_orden = o.id'
        . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '') .
        ' GROUP BY o.id
        ORDER BY o.id DESC
        LIMIT 200';
    $stmtOrd = $pdo->prepare($sql);
    $stmtOrd->execute($params);
    $ordenes = $stmtOrd->fetchAll(\PDO::FETCH_ASSOC);
} catch (Throwable) {}

// Agrupar repuestos por categoría para el checklist
$repPorCat = [];
foreach ($repuestos as $r) {
    $cat = $r['nombre_categoria'] ?? 'Sin categoría';
    $repPorCat[$cat][] = $r;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Órdenes de Trabajo - APP_TALLER</title>
<style>
*{box-sizing:border-box}
body{font-family:Segoe UI,Arial,sans-serif;margin:1.5rem;background:#f3f6fb;color:#0f172a}
.container{max-width:1200px;margin:0 auto}
.top{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}
.actions{display:flex;gap:.5rem;flex-wrap:wrap}
.btn{display:inline-block;text-decoration:none;border:0;border-radius:10px;padding:.65rem 1rem;cursor:pointer;font-weight:600;font-size:.95rem}
.btn-primary{background:#0f172a;color:#fff}.btn-primary:hover{background:#1e293b}
.btn-muted{background:#e2e8f0;color:#0f172a}.btn-muted:hover{background:#cbd5e1}
.btn-danger{background:#b91c1c;color:#fff}
.btn-small{padding:.45rem .7rem;border-radius:8px;font-size:.86rem}
.panel{background:#fff;border:1px solid #d8e1ef;border-radius:12px;padding:1rem;margin-bottom:1rem}
.panel h2{margin-top:0;font-size:1.1rem;margin-bottom:.75rem}
.msg-ok{background:#dcfce7;color:#166534;padding:.6rem;border-radius:8px;margin-bottom:1rem}
.msg-err{background:#fee2e2;color:#991b1b;padding:.6rem;border-radius:8px;margin-bottom:1rem}
/* TABLA */
table{width:100%;border-collapse:collapse;margin-top:.6rem}
th,td{border-bottom:1px solid #e2e8f0;padding:.55rem;text-align:left;font-size:.93rem;vertical-align:top}
.num{text-align:right}
th{font-weight:700}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:999px;font-size:.8rem;font-weight:700}
.badge-abierta{background:#dbeafe;color:#1d4ed8}
.badge-en_progreso{background:#fef9c3;color:#854d0e}
.badge-cerrada{background:#dcfce7;color:#166534}
/* FORM */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem}
.form-group{display:flex;flex-direction:column;gap:.25rem}
label{display:block;font-size:.9rem;margin-bottom:.25rem;font-weight:600;color:#0f172a}
input[type=text],input[type=date],select,textarea{width:100%;box-sizing:border-box;border:1px solid #c6d2e4;border-radius:8px;padding:.55rem;font-family:inherit;font-size:.93rem}
input[type=text]:focus,input[type=date]:focus,select:focus,textarea:focus{outline:2px solid #0f172a;border-color:#0f172a}
/* CHECKLIST REPUESTOS */
.rep-section{margin-bottom:1.2rem}
.rep-cat-title{font-size:.82rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.4rem;margin-top:.8rem}
.rep-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.35rem}
.rep-item{display:flex;align-items:center;gap:.5rem;padding:.35rem .5rem;border-radius:6px;border:1px solid #e2e8f0;background:#f8fafc;font-size:.88rem}
.rep-item input[type=checkbox]{width:16px;height:16px;cursor:pointer;flex-shrink:0}
.rep-item .rep-info{flex:1;min-width:0}
.rep-item .rep-name{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rep-item .rep-meta{font-size:.75rem;color:#64748b}
.rep-item .cant-wrap{display:none;align-items:center;gap:.25rem;margin-top:.25rem}
.rep-item.checked .cant-wrap{display:flex}
.rep-item .cant-wrap input{width:55px;padding:.2rem .35rem}
/* ITEMS LIBRES */
.libre-list{display:flex;flex-direction:column;gap:.4rem;margin-top:.5rem}
.libre-row{display:flex;gap:.5rem;align-items:center}
.libre-row input{flex:1}
.btn-add-inline{background:#e2e8f0;border:none;border-radius:6px;padding:.3rem .7rem;font-size:.82rem;font-weight:600;color:#0f172a;cursor:pointer;white-space:nowrap}
.btn-add-inline:hover{background:#cbd5e1}
.btn-remove{background:#fee2e2;color:#991b1b;border:none;border-radius:6px;padding:.25rem .5rem;cursor:pointer;font-size:.85rem}
.form-actions{display:flex;gap:.5rem;margin-top:1rem;justify-content:flex-end}
@media(max-width:640px){.grid2,.grid3{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
  <div class="top">
    <div>
      <h1>Órdenes de Trabajo</h1>
      <p>Alta y seguimiento de órdenes de trabajo.</p>
    </div>
    <div class="actions">
      <a href="/dashboard.php" class="btn btn-muted">Volver al panel</a>
      <?php if (!$showForm): ?>
      <a href="/ordenes.php?show_filters=1" class="btn btn-muted">Buscar</a>
      <a href="?nueva" class="btn btn-primary">Nuevo</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($success): ?>
  <div class="msg-ok"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="msg-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($showForm): ?>
  <!-- ═══════════ FORMULARIO NUEVA / EDITAR ORDEN ═══════════ -->
  <?php
    $isEdit       = $formOrden['id'] > 0;
    $formAction   = $isEdit ? '?edit=' . $formOrden['id'] : '?nueva';
    $actionValue  = $isEdit ? 'update_orden' : 'save_orden';
    // IDs de repuestos y cantidades ya cargados
    $detRepIds   = [];
    $detRepCants = [];
    $detLibres   = [];
    foreach ($formDetalle as $d) {
        if ($d['id_repuesto']) {
            $detRepIds[]                      = (int)$d['id_repuesto'];
            $detRepCants[(int)$d['id_repuesto']] = (int)$d['cantidad'];
        } else {
            $detLibres[] = $d['descripcion_libre'];
        }
    }
    // Descripciones del campo motivo
    $descVals = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $descVals = (array)($_POST['descripciones'] ?? ['']);
    } elseif ($isEdit && $formOrden['descripcion'] !== '') {
        $descVals = explode(' | ', $formOrden['descripcion']);
    }
    if (empty($descVals)) $descVals = [''];
    // Cliente preseleccionado
    $preClienteId = $isEdit ? $formOrden['id_cliente'] : (int)($_POST['id_cliente'] ?? 0);
    $preIdMarca   = 0;
    foreach ($clientes as $cl) {
        if ((int)$cl['id_cliente'] === $preClienteId) {
            $preIdMarca = (int)($cl['id_vehiculo_marca'] ?? 0);
            break;
        }
    }
  ?>
  <form method="post" action="<?= htmlspecialchars($formAction) ?>" autocomplete="off">
  <input type="hidden" name="action" value="<?= $actionValue ?>">
  <?php if ($isEdit): ?>
  <input type="hidden" name="id_orden" value="<?= $formOrden['id'] ?>">
  <?php endif; ?>

  <div class="panel">
    <h2><?= $isEdit ? 'Editar orden de trabajo #' . $formOrden['id'] : 'Orden de trabajo' ?></h2>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="flex:1;min-width:200px">
        <label for="sel_cliente">Cliente *</label>
        <select id="sel_cliente" name="id_cliente" required onchange="rellenarCliente(this)">
          <option value="">— Seleccionar cliente —</option>
          <?php foreach ($clientes as $cl):
            $auto = trim($cl['marca'] . ' ' . $cl['modelo']);
            $sel  = ($preClienteId === (int)$cl['id_cliente']) ? 'selected' : '';
            $patenteCl = strtoupper((string)($cl['patente'] ?? ''));
            $label = htmlspecialchars($cl['nombre']);
            if ($auto)      $label .= ' — ' . htmlspecialchars($auto);
            if ($patenteCl) $label .= ' — ' . htmlspecialchars($patenteCl);
          ?>
          <option value="<?= $cl['id_cliente'] ?>" <?= $sel ?>
            data-patente="<?= htmlspecialchars($patenteCl) ?>"
            data-id-marca-vehiculo="<?= (int)($cl['id_vehiculo_marca'] ?? 0) ?>">
            <?= $label ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:0 0 auto">
        <label for="fecha_ot">Fecha de la OT *</label>
        <input type="date" id="fecha_ot" name="fecha_ot" required
               style="width:auto"
               value="<?= htmlspecialchars($isEdit ? $formOrden['fecha_ot'] : ($_POST['fecha_ot'] ?? date('Y-m-d'))) ?>">
      </div>
      <?php if ($isEdit): ?>
      <div class="form-group" style="flex:0 0 auto">
        <label for="estado">Estado</label>
        <select id="estado" name="estado" style="width:auto">
          <?php foreach (['abierta'=>'Abierta','en_progreso'=>'En progreso','cerrada'=>'Cerrada'] as $val=>$lbl): ?>
          <option value="<?= $val ?>" <?= $formOrden['estado']===$val?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
    <div style="margin-top:.9rem">
        <label>Descripción / motivo</label>
        <div class="libre-list" id="descList">
          <?php foreach ($descVals as $i => $dv): ?>
          <div class="libre-row">
            <input type="text" name="descripciones[]" autocomplete="off" placeholder="Motivo del ingreso"
                   value="<?= htmlspecialchars((string)$dv) ?>">
            <button type="button" class="btn-add-inline" onclick="addDesc()" style="display:none">+ Agregar</button>
            <button type="button" class="btn-remove" onclick="removeDesc(this)">&#10005;</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-muted btn-small" style="display:none" onclick="addDesc()">+ Agregar descripci&oacute;n</button>
    </div>
  </div>

  <!-- ── REPUESTOS ── -->
  <div class="panel" id="panel-repuestos" style="display:none">
    <h2>Repuestos en stock</h2>
    <?php if (empty($repuestos)): ?>
      <p style="color:#64748b;font-size:.9rem">No hay repuestos cargados en el sistema.</p>
    <?php else: ?>
    <div class="rep-section">
      <?php foreach ($repPorCat as $cat => $items): ?>
      <div class="rep-cat-title"><?= htmlspecialchars($cat) ?></div>
      <div class="rep-grid">
        <?php foreach ($items as $r): ?>
        <div class="rep-item<?= in_array($r['id_repuesto'], $detRepIds) ? ' checked' : '' ?>"
             id="ri_<?= $r['id_repuesto'] ?>"
             data-marcas-compat="<?= htmlspecialchars((string)($r['marcas_compat'] ?? '')) ?>"
             data-stock="<?= (int)$r['stock_actual'] ?>"
             data-nombre="<?= htmlspecialchars($r['nombre']) ?>">
          <input type="checkbox"
                 id="rep_<?= $r['id_repuesto'] ?>"
                 name="repuestos[]"
                 value="<?= $r['id_repuesto'] ?>"
                 <?= in_array($r['id_repuesto'], $detRepIds) ? 'checked' : '' ?>
                 onchange="toggleCant(this, <?= $r['id_repuesto'] ?>)">
          <div class="rep-info">
            <label for="rep_<?= $r['id_repuesto'] ?>" class="rep-name" title="<?= htmlspecialchars($r['nombre']) ?>">
              <?= htmlspecialchars($r['nombre']) ?>
            </label>
            <div class="rep-meta">SKU: <?= htmlspecialchars($r['sku']) ?> &nbsp;|&nbsp; Stock: <?= (int)$r['stock_actual'] ?></div>
            <div class="cant-wrap">
              <label style="font-size:.78rem;font-weight:600;white-space:nowrap">Cant.:</label>
              <input type="number" name="cant_rep_<?= $r['id_repuesto'] ?>"
                     id="cant_<?= $r['id_repuesto'] ?>"
                     value="<?= $detRepCants[$r['id_repuesto']] ?? 1 ?>" min="1" max="<?= (int)$r['stock_actual'] ?>">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── ITEMS LIBRES ── -->
  <div class="panel" id="panel-libres" style="display:none">
    <h2>Repuestos sin registrar</h2>
    <p style="font-size:.85rem;color:#64748b;margin-top:0">Escribí cualquier ítem que no figure en el stock.</p>
    <div class="libre-list" id="libreList">
      <?php
        $initLibres = ($isEdit && !empty($detLibres)) ? $detLibres : [''];
        foreach ($initLibres as $il):
      ?>
      <div class="libre-row">
        <input type="text" name="items_libres[]" autocomplete="off" placeholder="Descripción del trabajo o repuesto"
               value="<?= htmlspecialchars((string)$il) ?>">
        <button type="button" class="btn-add-inline" onclick="addLibre()" style="display:none">+ Agregar</button>
        <button type="button" class="btn-remove" onclick="removeLibre(this)">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm btn-add-libre" style="display:none" onclick="addLibre()">+ Agregar ítem</button>
  </div>

  <div class="form-actions">
    <a href="/ordenes.php" class="btn btn-muted">Cancelar</a>
    <button type="submit" class="btn btn-primary" onclick="return validarStock(event)"><?= $isEdit ? 'Guardar cambios' : 'Guardar orden de trabajo' ?></button>
  </div>
  </form>

  <?php else: ?>
  <!-- ═══════════ LISTA DE ORDENES ═══════════ -->

  <?php if ($showFilters): ?>
  <div class="panel">
    <h2>Filtros de búsqueda</h2>
    <form method="get" action="/ordenes.php">
      <input type="hidden" name="show_filters" value="1">
      <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem">
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
          <label for="f_estado">Estado</label>
          <select id="f_estado" name="f_estado">
            <option value="">Todos</option>
            <option value="abierta"     <?= $filters['estado']==='abierta'     ?'selected':'' ?>>Abierta</option>
            <option value="en_progreso" <?= $filters['estado']==='en_progreso' ?'selected':'' ?>>En progreso</option>
            <option value="cerrada"     <?= $filters['estado']==='cerrada'     ?'selected':'' ?>>Cerrada</option>
          </select>
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
        <a class="btn btn-muted" href="/ordenes.php">Limpiar</a>
        <a class="btn btn-muted" href="/ordenes.php">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
  <div class="panel">
    <h2>Lista de Órdenes de Trabajo</h2>
    <table>
      <thead>
        <tr>
          <th class="num">#</th>
          <th>Fecha OT</th>
          <th>Cliente</th>
          <th>Vehículo</th>
          <th>Patente</th>
          <th class="num">Items</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ordenes)): ?>
        <tr><td colspan="8" style="text-align:center;color:#64748b;padding:2rem">No hay órdenes registradas todavía.</td></tr>
        <?php else: ?>
        <?php foreach ($ordenes as $o): ?>
        <tr>
          <td class="num"><?= $o['id'] ?></td>
          <td><?= htmlspecialchars($o['fecha_ot'] ?? '—') ?></td>
          <td><?= htmlspecialchars($o['cliente']) ?></td>
          <td><?= htmlspecialchars($o['vehiculo']) ?></td>
          <td><?= htmlspecialchars($o['patente']) ?></td>
          <td class="num"><?= (int)$o['cant_items'] ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($o['estado']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $o['estado']))) ?></span></td>
          <td>
            <div class="actions">
              <a href="/ordenes.php?edit=<?= (int)$o['id'] ?>" class="btn btn-muted btn-small">Editar</a>
              <?php if ($o['estado'] !== 'cerrada'): ?>
              <a href="/cerrar_orden.php?id=<?= (int)$o['id'] ?>" class="btn btn-primary btn-small">Terminar</a>
              <?php endif; ?>
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

<script>
function rellenarCliente(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        const p = document.getElementById('patente');
        if (p && !p.value) p.value = opt.dataset.patente || '';
        filtrarRepuestos(opt.dataset.idMarcaVehiculo || '');
        document.getElementById('panel-repuestos').style.display = '';
        document.getElementById('panel-libres').style.display = '';
    } else {
        document.getElementById('panel-repuestos').style.display = 'none';
        document.getElementById('panel-libres').style.display = 'none';
    }
}

var MULTIMARCA_ID = '8'; // id_vehiculo_marca de la marca "Multimarca"

function filtrarRepuestos(idMarca) {
    document.querySelectorAll('.rep-item').forEach(function(item) {
        const compat = item.dataset.marcasCompat || '';
        // Sin compat registrada, sin marca seleccionada, o multimarca = siempre visible
        if (!compat || !idMarca || idMarca === '0') {
            item.style.display = '';
        } else {
            const ids = compat.split(',');
            // Visible si es compatible con el vehículo seleccionado O es multimarca
            item.style.display = (ids.includes(idMarca) || ids.includes(MULTIMARCA_ID)) ? '' : 'none';
        }
    });
    // Ocultar títulos de categoría si no tienen items visibles
    document.querySelectorAll('.rep-cat-title').forEach(function(title) {
        const grid = title.nextElementSibling;
        if (!grid) return;
        const visible = grid.querySelectorAll('.rep-item:not([style*="none"])');
        title.style.display = visible.length ? '' : 'none';
    });
}

function updateAddBtn(listId) {
    const rows = document.querySelectorAll('#' + listId + ' .libre-row');
    rows.forEach(function(row, i) {
        const btn = row.querySelector('.btn-add-inline');
        if (btn) btn.style.display = (i === rows.length - 1) ? '' : 'none';
    });
}

function addDesc() {
    const list = document.getElementById('descList');
    const row = document.createElement('div');
    row.className = 'libre-row';
    row.innerHTML = '<input type="text" name="descripciones[]" autocomplete="off" placeholder="Motivo del ingreso">'
                  + '<button type="button" class="btn-add-inline" onclick="addDesc()">+ Agregar</button>'
                  + '<button type="button" class="btn-remove" onclick="removeDesc(this)">✕</button>';
    list.appendChild(row);
    updateAddBtn('descList');
    row.querySelector('input').focus();
}

function removeDesc(btn) {
    const list = document.getElementById('descList');
    if (list.children.length > 1) {
        btn.closest('.libre-row').remove();
    } else {
        btn.closest('.libre-row').querySelector('input').value = '';
    }
    updateAddBtn('descList');
}

function toggleCant(chk, id) {
    const item = document.getElementById('ri_' + id);
    const cantInput = document.getElementById('cant_' + id);
    if (chk.checked) {
        item.classList.add('checked');
        cantInput.required = true;
        cantInput.focus();
    } else {
        item.classList.remove('checked');
        cantInput.required = false;
        cantInput.value = 1;
    }
}

function addLibre() {
    const list = document.getElementById('libreList');
    const row = document.createElement('div');
    row.className = 'libre-row';
    row.innerHTML = '<input type="text" name="items_libres[]" autocomplete="off" placeholder="Descripción del trabajo o repuesto">'
                  + '<button type="button" class="btn-add-inline" onclick="addLibre()">+ Agregar</button>'
                  + '<button type="button" class="btn-remove" onclick="removeLibre(this)">✕</button>';
    list.appendChild(row);
    updateAddBtn('libreList');
    row.querySelector('input').focus();
}

function removeLibre(btn) {
    const list = document.getElementById('libreList');
    if (list.children.length > 1) {
        btn.closest('.libre-row').remove();
    } else {
        btn.closest('.libre-row').querySelector('input').value = '';
    }
    updateAddBtn('libreList');
}

function validarStock(e) {
    var errores = [];
    document.querySelectorAll('.rep-item.checked').forEach(function(item) {
        var stock  = parseInt(item.dataset.stock, 10) || 0;
        var nombre = item.dataset.nombre || 'Repuesto';
        var id     = item.id.replace('ri_', '');
        var cant   = parseInt(document.getElementById('cant_' + id).value, 10) || 0;
        if (cant > stock) {
            errores.push('• ' + nombre + ': solicitado ' + cant + ', stock disponible ' + stock);
        }
    });
    if (errores.length) {
        e.preventDefault();
        alert('No se puede guardar la orden.\nLas siguientes cantidades superan el stock registrado:\n\n' + errores.join('\n'));
        return false;
    }
    return true;
}

// Si el form se recargó con un cliente ya elegido (error de validación o edición), mostrar los paneles
(function() {
    var sel = document.getElementById('sel_cliente');
    if (sel && sel.value) {
        document.getElementById('panel-repuestos').style.display = '';
        document.getElementById('panel-libres').style.display = '';
        filtrarRepuestos(sel.options[sel.selectedIndex].dataset.idMarcaVehiculo || '');
    }
    // En edicion, los paneles siempre visibles
    <?php if ($isEdit ?? false): ?>
    var pr = document.getElementById('panel-repuestos');
    var pl = document.getElementById('panel-libres');
    if (pr) pr.style.display = '';
    if (pl) pl.style.display = '';
    <?php endif; ?>
    // Marcar como required los inputs de cantidad de repuestos ya seleccionados
    document.querySelectorAll('.rep-item.checked input[type=number]').forEach(function(inp) {
        inp.required = true;
    });
    // Mostrar botón "+ Agregar" en el último row de cada lista
    updateAddBtn('descList');
    updateAddBtn('libreList');
})();
</script>
</body>
</html>
