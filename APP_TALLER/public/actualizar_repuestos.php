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

// ── Catálogos ─────────────────────────────────────────────────────────────────
$catalogs = [];
try {
    $catalogs['proveedores']      = $pdo->query('SELECT id_proveedor AS id, razon_social AS nombre FROM proveedores WHERE activo = 1 ORDER BY razon_social')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['categorias']       = $pdo->query('SELECT id_categoria AS id, nombre_categoria AS nombre FROM categorias WHERE activo = 1 ORDER BY nombre_categoria')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['marcas']           = $pdo->query('SELECT id_marca AS id, nombre_marca AS nombre FROM marcas WHERE activo = 1 ORDER BY nombre_marca')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['vehiculos_marcas'] = $pdo->query('SELECT id_vehiculo_marca AS id, nombre_marca_v AS nombre FROM vehiculos_marcas WHERE activo = 1 ORDER BY nombre_marca_v')->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['vehiculos_modelos'] = $pdo->query(
        'SELECT m.id_modelo AS id, m.nombre_modelo AS nombre, vm.id_vehiculo_marca AS marca_id
         FROM vehiculos_modelos m
         INNER JOIN vehiculos_marcas vm ON vm.id_vehiculo_marca = m.id_vehiculo_marca
         WHERE m.activo = 1 AND vm.activo = 1
         ORDER BY vm.nombre_marca_v, m.nombre_modelo'
    )->fetchAll(PDO::FETCH_ASSOC);
    $catalogs['motorizaciones']   = $pdo->query('SELECT id_motorizacion AS id, nombre_motor AS nombre FROM motorizaciones WHERE activo = 1 ORDER BY nombre_motor')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'No se pudieron cargar los catálogos: ' . $e->getMessage();
}

// ── Guardar cambios masivos ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_prices') {
    $ids                  = (array) ($_POST['id_repuesto']          ?? []);
    $preciosCosto         = (array) ($_POST['precio_costo']         ?? []);
    $preciosVenta         = (array) ($_POST['precio_venta']         ?? []);
    $preciosVentaOrig     = (array) ($_POST['precio_venta_original'] ?? []);
    $updated = 0;

    try {
        $stmt = $pdo->prepare(
            'UPDATE repuestos
             SET precio_costo = :precio_costo,
                 precio_venta = :precio_venta
             WHERE id_repuesto = :id_repuesto'
        );

        foreach ($ids as $i => $idRaw) {
            $id         = (int) $idRaw;
            $costo      = (float) ($preciosCosto[$i] ?? 0);
            $venta      = (float) ($preciosVenta[$i] ?? 0);
            $ventaOrig  = (float) ($preciosVentaOrig[$i] ?? -1);
            if ($id <= 0) continue;
            // Solo actualizar si el precio de venta cambió
            if (abs($venta - $ventaOrig) < 0.001) continue;
            $stmt->execute([
                'precio_costo'  => $costo,
                'precio_venta'  => $venta,
                'id_repuesto'   => $id,
            ]);
            $updated++;
        }

        $success = $updated > 0
            ? "Se actualizaron $updated repuesto(s) correctamente."
            : 'No se detectaron cambios en los precios de venta.';
    } catch (Throwable $e) {
        $error = 'No fue posible guardar los cambios: ' . $e->getMessage();
    }
}

// ── Filtros ───────────────────────────────────────────────────────────────────
$filters = [
    'proveedor_id'       => (int) ($_GET['f_proveedor_id']       ?? ($_POST['f_proveedor_id']       ?? 0)),
    'categoria_id'       => (int) ($_GET['f_categoria_id']       ?? ($_POST['f_categoria_id']       ?? 0)),
    'marca_id'           => (int) ($_GET['f_marca_id']           ?? ($_POST['f_marca_id']           ?? 0)),
    'vehiculo_marca_id'  => (int) ($_GET['f_vehiculo_marca_id']  ?? ($_POST['f_vehiculo_marca_id']  ?? 0)),
    'vehiculo_modelo_id' => (int) ($_GET['f_vehiculo_modelo_id'] ?? ($_POST['f_vehiculo_modelo_id'] ?? 0)),
    'motorizacion_id'    => (int) ($_GET['f_motorizacion_id']    ?? ($_POST['f_motorizacion_id']    ?? 0)),
];

// Preservar filtros desde POST (después del guardado)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($filters as $k => $v) {
        $postKey = 'f_' . $k;
        if (isset($_POST[$postKey])) {
            $filters[$k] = (int) $_POST[$postKey];
        }
    }
}

$searched = array_sum($filters) > 0;

$repuestos = [];
if ($searched) {
    try {
        $where  = ['r.activo = 1'];
        $params = [];

        if ($filters['proveedor_id'] > 0) {
            $where[]                     = 'r.id_proveedor = :f_proveedor_id';
            $params['f_proveedor_id']    = $filters['proveedor_id'];
        }
        if ($filters['categoria_id'] > 0) {
            $where[]                     = 'r.id_categoria = :f_categoria_id';
            $params['f_categoria_id']    = $filters['categoria_id'];
        }
        if ($filters['marca_id'] > 0) {
            $where[]                     = 'r.id_marca = :f_marca_id';
            $params['f_marca_id']        = $filters['marca_id'];
        }
        if ($filters['vehiculo_modelo_id'] > 0) {
            $where[]                          = 'cv.id_modelo = :f_vehiculo_modelo_id';
            $params['f_vehiculo_modelo_id']   = $filters['vehiculo_modelo_id'];
        } elseif ($filters['vehiculo_marca_id'] > 0) {
            $where[]                          = 'vm.id_vehiculo_marca = :f_vehiculo_marca_id';
            $params['f_vehiculo_marca_id']    = $filters['vehiculo_marca_id'];
        }
        if ($filters['motorizacion_id'] > 0) {
            $where[]                       = 'cv.id_motorizacion = :f_motorizacion_id';
            $params['f_motorizacion_id']   = $filters['motorizacion_id'];
        }

        $sql =
            'SELECT r.id_repuesto,
                    r.codigo,
                    r.nombre,
                    COALESCE(r.precio_costo, 0) AS precio_costo,
                    COALESCE(r.precio_venta, 0) AS precio_venta,
                    ma.nombre_marca        AS marca,
                    vm.nombre_marca_v      AS vehiculo_marca,
                    vmo.nombre_modelo      AS vehiculo_modelo,
                    mot.nombre_motor       AS motorizacion,
                    cat.nombre_categoria   AS categoria,
                    prov.razon_social      AS proveedor
             FROM repuestos r
             LEFT JOIN marcas ma          ON ma.id_marca        = r.id_marca
             LEFT JOIN categorias cat     ON cat.id_categoria   = r.id_categoria
             LEFT JOIN proveedores prov   ON prov.id_proveedor  = r.id_proveedor
             LEFT JOIN (
                 SELECT id_repuesto, MIN(id_modelo) AS id_modelo, MIN(id_motorizacion) AS id_motorizacion
                 FROM compatibilidad_vehiculos WHERE activo = 1
                 GROUP BY id_repuesto
             ) cv ON cv.id_repuesto = r.id_repuesto
             LEFT JOIN vehiculos_modelos vmo ON vmo.id_modelo        = cv.id_modelo
             LEFT JOIN vehiculos_marcas  vm  ON vm.id_vehiculo_marca  = vmo.id_vehiculo_marca
             LEFT JOIN motorizaciones    mot ON mot.id_motorizacion   = cv.id_motorizacion
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.nombre';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $repuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'No fue posible cargar los repuestos: ' . $e->getMessage();
    }
}

function filterQS(array $filters, string $extra = ''): string
{
    $parts = [];
    foreach ($filters as $k => $v) {
        if ($v > 0) $parts[] = 'f_' . $k . '=' . $v;
    }
    if ($extra !== '') $parts[] = $extra;
    return $parts !== [] ? '?' . implode('&', $parts) : '';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar lista de repuestos - APP_TALLER</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 1.5rem; background: #f3f6fb; color: #0f172a; }
        .container { max-width: 1300px; margin: 0 auto; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .btn { display: inline-block; text-decoration: none; border: 0; border-radius: 10px; padding: 0.65rem 1rem; cursor: pointer; font-weight: 600; font-size: 0.95rem; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }
        .panel { background: #fff; border: 1px solid #d8e1ef; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
        label { display: block; font-size: 0.9rem; margin-bottom: 0.25rem; font-weight: 600; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #c6d2e4; border-radius: 8px; padding: 0.55rem; }
        input[type="number"] { text-align: right; }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.9rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 0.55rem 0.6rem; text-align: left; font-size: 0.91rem; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        .num { text-align: right; }
        .msg-error { background: #fee2e2; color: #991b1b; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-ok    { background: #dcfce7; color: #166534; padding: 0.6rem; border-radius: 8px; margin-bottom: 1rem; }
        .price-input { width: 120px; padding: 0.35rem 0.5rem; border: 1px solid #c6d2e4; border-radius: 6px; text-align: right; }
        .price-input:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 2px #e0e7ff; }
        .btn-save-all { background: #0f172a; color: #fff; border: none; border-radius: 10px; padding: 0.7rem 1.4rem; font-weight: 700; cursor: pointer; font-size: 0.97rem; }
        .btn-save-all:hover { background: #1e293b; }
        .hint { font-size: 0.85rem; color: #64748b; margin-top: 0.4rem; }
        .empty-msg { padding: 1.5rem; text-align: center; color: #64748b; }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <div>
            <h1>Actualizar lista de repuestos</h1>
            <p>Filtrá y actualizá precios de costo y venta en forma masiva.</p>
        </div>
        <div class="actions" style="margin-top:0;">
            <a class="btn btn-muted" href="/compras.php">Volver a Repuestos</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="msg-ok"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="panel">
        <h2 style="margin-top:0;">Filtros de búsqueda</h2>
        <form method="get" action="/actualizar_repuestos.php" id="form-filtros" autocomplete="off">
            <div class="grid">
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
                    <label for="f_marca_id">Marca del repuesto</label>
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
                    <label for="f_vehiculo_marca_id">Vehículo marca</label>
                    <select id="f_vehiculo_marca_id" name="f_vehiculo_marca_id">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['vehiculos_marcas'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= $filters['vehiculo_marca_id'] === (int) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_vehiculo_modelo_id">Vehículo modelo</label>
                    <select id="f_vehiculo_modelo_id" name="f_vehiculo_modelo_id">
                        <option value="0">Todos</option>
                        <?php foreach ($catalogs['vehiculos_modelos'] as $row): ?>
                            <option
                                value="<?= (int) $row['id'] ?>"
                                data-marca="<?= (int) $row['marca_id'] ?>"
                                <?= $filters['vehiculo_modelo_id'] === (int) $row['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_motorizacion_id">Motorización</label>
                    <select id="f_motorizacion_id" name="f_motorizacion_id">
                        <option value="0">Todas</option>
                        <?php foreach ($catalogs['motorizaciones'] as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= $filters['motorizacion_id'] === (int) $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $row['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="porcentaje">Porcentaje (%)</label>
                    <input
                        id="porcentaje"
                        name="porcentaje"
                        type="number"
                        step="0.01"
                        value="0"
                        style="text-align:right;"
                    >
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Buscar</button>
                <button class="btn btn-muted" type="button" id="btn-calcular">Calcular</button>
                <a class="btn btn-muted" href="/actualizar_repuestos.php">Limpiar</a>
            </div>
        </form>
    </div>

    <!-- Resultados y edición -->
    <?php if ($searched): ?>
    <div class="panel">
        <h2 style="margin-top:0;">Resultados (<?= count($repuestos) ?> repuesto<?= count($repuestos) !== 1 ? 's' : '' ?>)</h2>

        <?php if (count($repuestos) === 0): ?>
            <p class="empty-msg">No se encontraron repuestos con esos filtros.</p>
        <?php else: ?>
        <form method="post" action="/actualizar_repuestos.php" autocomplete="off">
            <input type="hidden" name="action" value="update_prices">
            <!-- Preservar filtros activos para restaurar resultados post-guardado -->
            <?php foreach ($filters as $k => $v): ?>
                <input type="hidden" name="f_<?= htmlspecialchars($k) ?>" value="<?= (int) $v ?>">
            <?php endforeach; ?>

            <table>
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Marca</th>
                    <th>Vehículo</th>
                    <th>Motorización</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th class="num">Precio costo</th>
                    <th class="num">Precio venta</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($repuestos as $rep): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $rep['codigo']) ?></td>
                    <td><?= htmlspecialchars((string) $rep['nombre']) ?></td>
                    <td><?= htmlspecialchars((string) ($rep['marca'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars(trim(($rep['vehiculo_marca'] ?? '') . ' ' . ($rep['vehiculo_modelo'] ?? ''))) ?: '-' ?></td>
                    <td><?= htmlspecialchars((string) ($rep['motorizacion'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($rep['categoria'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars((string) ($rep['proveedor'] ?? '-')) ?></td>
                    <td class="num">
                        <input type="hidden" name="id_repuesto[]" value="<?= (int) $rep['id_repuesto'] ?>">
                        <input
                            class="price-input"
                            type="number"
                            name="precio_costo[]"
                            step="0.01"
                            min="0"
                            value="<?= htmlspecialchars(number_format((float) $rep['precio_costo'], 2, '.', '')) ?>"
                        >
                    </td>
                    <td class="num">
                        <input type="hidden" name="precio_venta_original[]" value="<?= htmlspecialchars(number_format((float) $rep['precio_venta'], 2, '.', '')) ?>">
                        <input
                            class="price-input"
                            type="number"
                            name="precio_venta[]"
                            step="0.01"
                            min="0"
                            value="<?= htmlspecialchars(number_format((float) $rep['precio_venta'], 2, '.', '')) ?>"
                        >
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="actions">
                <button class="btn-save-all" type="submit">Guardar cambios</button>
                <p class="hint" style="margin:0;align-self:center;">Se guardan los precios tal como están en la tabla.</p>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php elseif (!$searched): ?>
    <div class="panel">
        <p class="empty-msg">Seleccioná al menos un filtro y presioná <strong>Buscar</strong> para ver los repuestos.</p>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var selMarca  = document.getElementById('f_vehiculo_marca_id');
    var selModelo = document.getElementById('f_vehiculo_modelo_id');
    if (!selMarca || !selModelo) return;

    var allOptions = Array.prototype.slice.call(selModelo.options).filter(function (o) { return o.value !== '0'; });

    function filterModelos() {
        var marcaId = selMarca.value;
        var current = selModelo.value;

        while (selModelo.options.length > 1) selModelo.remove(1);

        allOptions.forEach(function (opt) {
            if (marcaId === '0' || opt.dataset.marca === marcaId) {
                selModelo.add(opt.cloneNode(true));
            }
        });

        var stillValid = Array.prototype.some.call(selModelo.options, function (o) { return o.value === current; });
        selModelo.value = stillValid ? current : '0';
    }

    selMarca.addEventListener('change', function () {
        selModelo.value = '0';
        filterModelos();
    });

    filterModelos();
}());

// ── Calcular porcentaje sobre precio venta (solo en pantalla, sin guardar) ──
(function () {
    var btnCalcular = document.getElementById('btn-calcular');
    if (!btnCalcular) return;

    btnCalcular.addEventListener('click', function () {
        var porcentajeInput = document.getElementById('porcentaje');
        if (!porcentajeInput) return;

        var pct = parseFloat(porcentajeInput.value);
        if (isNaN(pct) || pct === 0) {
            alert('Ingresá un porcentaje distinto de 0 para calcular.');
            return;
        }

        var inputs = document.querySelectorAll('input[name="precio_venta[]"]');
        inputs.forEach(function (inp) {
            var row = inp.closest('tr');
            if (!row) return;
            var costoInp = row.querySelector('input[name="precio_costo[]"]');
            var costo = costoInp ? parseFloat(costoInp.value) : NaN;
            if (!isNaN(costo)) {
                inp.value = (Math.round(costo * (1 + pct / 100) * 100) / 100).toFixed(2);
            }
        });
    });
}());
</script>
</body>
</html>
