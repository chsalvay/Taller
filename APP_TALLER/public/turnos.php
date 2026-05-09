<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireLogin();

$config = require $projectRoot . '/config/app.php';
$user = Auth::user();
$isAdmin = isset($user['rol']) && $user['rol'] === 'Admin';

if (!$isAdmin) {
    http_response_code(403);
    die('Acceso denegado');
}

$error = '';
$success = '';
$turno_edit = null;
$action = $_GET['action'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

try {
    $pdo = Database::connect($projectRoot);

    // ── CREAR TURNO ─────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear') {
        $cliente = trim((string) ($_POST['cliente'] ?? ''));
        $vehiculo = trim((string) ($_POST['vehiculo'] ?? ''));
        $fecha_turno = trim((string) ($_POST['fecha_turno'] ?? ''));
        $hora_turno = trim((string) ($_POST['hora_turno'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));

        if ($cliente === '' || $vehiculo === '' || $fecha_turno === '' || $hora_turno === '') {
            $error = 'Cliente, vehículo, fecha y hora son obligatorios.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO turnos (cliente, vehiculo, fecha_turno, hora_turno, telefono, descripcion, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$cliente, $vehiculo, $fecha_turno, $hora_turno, $telefono, $descripcion, $estado]);
            $success = 'Turno creado correctamente.';
        }
    }

    // ── ACTUALIZAR TURNO ────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'actualizar') {
        $cliente = trim((string) ($_POST['cliente'] ?? ''));
        $vehiculo = trim((string) ($_POST['vehiculo'] ?? ''));
        $fecha_turno = trim((string) ($_POST['fecha_turno'] ?? ''));
        $hora_turno = trim((string) ($_POST['hora_turno'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));

        if ($id === 0 || $cliente === '' || $vehiculo === '' || $fecha_turno === '' || $hora_turno === '') {
            $error = 'Datos inválidos.';
        } else {
            $stmt = $pdo->prepare('
                UPDATE turnos
                SET cliente = ?, vehiculo = ?, fecha_turno = ?, hora_turno = ?, telefono = ?, descripcion = ?, estado = ?
                WHERE id_turno = ?
            ');
            $stmt->execute([$cliente, $vehiculo, $fecha_turno, $hora_turno, $telefono, $descripcion, $estado, $id]);
            $success = 'Turno actualizado correctamente.';
        }
    }

    // ── ELIMINAR TURNO ──────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'eliminar') {
        if ($id === 0) {
            $error = 'ID inválido.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM turnos WHERE id_turno = ?');
            $stmt->execute([$id]);
            $success = 'Turno eliminado correctamente.';
        }
    }

    // ── CARGAR TURNO PARA EDITAR ────────────────────────────────────────────
    if ($action === 'editar' && $id !== 0) {
        $stmt = $pdo->prepare('SELECT * FROM turnos WHERE id_turno = ?');
        $stmt->execute([$id]);
        $turno_edit = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$turno_edit) {
            $error = 'Turno no encontrado.';
            $turno_edit = null;
        }
    }

    // ── LISTAR TURNOS ──────────────────────────────────────────────────────
    $filtro_estado = $_GET['filtro_estado'] ?? '';
    $filtro_fecha = $_GET['filtro_fecha'] ?? '';

    $query = 'SELECT * FROM turnos WHERE 1 = 1';
    $params = [];

    if ($filtro_estado !== '') {
        $query .= ' AND estado = ?';
        $params[] = $filtro_estado;
    }

    if ($filtro_fecha !== '') {
        $query .= ' AND DATE(fecha_turno) = ?';
        $params[] = $filtro_fecha;
    }

    $query .= ' ORDER BY fecha_turno DESC, hora_turno DESC LIMIT 50';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $turnos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = 'Error de base de datos: ' . htmlspecialchars($e->getMessage());
    $turnos = [];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnos - <?= htmlspecialchars($config['name']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Segoe UI, Arial, sans-serif; margin: 0; background: #f5f7fb; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 14px; border: 1px solid #dfe5ef; padding: 1.5rem; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 1.8rem; }
        .btn { display: inline-block; padding: 0.65rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; font-size: 0.95rem; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-small { padding: 0.35rem 0.75rem; font-size: 0.85rem; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; }
        .alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .filters { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; }
        .filter-group { display: flex; gap: 0.5rem; align-items: center; }
        .filter-group label { font-weight: 600; }
        .filter-group select,
        .filter-group input { padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; }
        th { padding: 0.75rem; text-align: left; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
        td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .estado-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-weight: 600; font-size: 0.85rem; }
        .estado-pendiente { background: #fef08a; color: #854d0e; }
        .estado-confirmado { background: #d1fae5; color: #065f46; }
        .estado-cancelado { background: #fee2e2; color: #b91c1c; }
        .estado-completado { background: #dbeafe; color: #1e3a8a; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.active { display: flex; }
        .modal-content { background-color: #fff; margin: auto; padding: 2rem; border-radius: 14px; width: min(95%, 600px); box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-header h2 { margin: 0; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }
        .modal-close:hover { color: #0f172a; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; }
        .form-group input,
        .form-group textarea,
        .form-group select { width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; }
        .acciones { display: flex; gap: 0.5rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Gestión de Turnos</h1>
        <a href="/dashboard.php" class="btn btn-secondary">Volver</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($action === 'editar' && !$turno_edit): ?>
        <div class="alert alert-error">Turno no encontrado. Volviendo a la lista.</div>
        <meta http-equiv="refresh" content="2; url=/turnos.php">
    <?php endif; ?>

    <!-- ── FORMULARIO MODAL ──────────────────────────────────────────────────── -->
    <div id="formModal" class="modal <?= ($action === 'editar' && $turno_edit) || ($action === 'crear' && $_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') ? 'active' : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= ($action === 'editar' && $turno_edit) ? 'Editar Turno' : 'Nuevo Turno' ?></h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>

            <form method="post" action="/turnos.php?action=<?= ($action === 'editar' && $turno_edit) ? 'actualizar' : 'crear' ?><?= ($action === 'editar' && $turno_edit) ? '&id=' . htmlspecialchars((string) $turno_edit['id_turno']) : '' ?>">
                <div class="form-group">
                    <label for="cliente">Cliente *</label>
                    <input id="cliente" name="cliente" type="text" required value="<?= $turno_edit ? htmlspecialchars((string) $turno_edit['cliente']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="vehiculo">Vehículo *</label>
                    <input id="vehiculo" name="vehiculo" type="text" required value="<?= $turno_edit ? htmlspecialchars((string) $turno_edit['vehiculo']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="fecha_turno">Fecha *</label>
                    <input id="fecha_turno" name="fecha_turno" type="date" required value="<?= $turno_edit ? htmlspecialchars((string) $turno_edit['fecha_turno']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="hora_turno">Hora *</label>
                    <input id="hora_turno" name="hora_turno" type="time" required value="<?= $turno_edit ? htmlspecialchars((string) $turno_edit['hora_turno']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input id="telefono" name="telefono" type="tel" value="<?= $turno_edit ? htmlspecialchars((string) ($turno_edit['telefono'] ?? '')) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion"><?= $turno_edit ? htmlspecialchars((string) ($turno_edit['descripcion'] ?? '')) : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="pendiente" <?= ($turno_edit && $turno_edit['estado'] === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
                        <option value="confirmado" <?= ($turno_edit && $turno_edit['estado'] === 'confirmado') ? 'selected' : '' ?>>Confirmado</option>
                        <option value="cancelado" <?= ($turno_edit && $turno_edit['estado'] === 'cancelado') ? 'selected' : '' ?>>Cancelado</option>
                        <option value="completado" <?= ($turno_edit && $turno_edit['estado'] === 'completado') ? 'selected' : '' ?>>Completado</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── FILTROS Y BOTÓN CREAR ─────────────────────────────────────────────── -->
    <div class="filters">
        <div class="filter-group">
            <label for="filtro_estado">Estado:</label>
            <select id="filtro_estado" onchange="aplicarFiltros()">
                <option value="">Todos</option>
                <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="confirmado" <?= $filtro_estado === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                <option value="cancelado" <?= $filtro_estado === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                <option value="completado" <?= $filtro_estado === 'completado' ? 'selected' : '' ?>>Completado</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="filtro_fecha">Fecha:</label>
            <input type="date" id="filtro_fecha" value="<?= htmlspecialchars((string) $filtro_fecha) ?>" onchange="aplicarFiltros()">
        </div>

        <button class="btn btn-primary" onclick="openModal()">+ Nuevo Turno</button>
    </div>

    <!-- ── TABLA DE TURNOS ────────────────────────────────────────────────────── -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Vehículo</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($turnos as $turno): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $turno['cliente']) ?></td>
                        <td><?= htmlspecialchars((string) $turno['vehiculo']) ?></td>
                        <td><?= htmlspecialchars((string) $turno['fecha_turno']) ?></td>
                        <td><?= htmlspecialchars((string) $turno['hora_turno']) ?></td>
                        <td><?= htmlspecialchars((string) ($turno['telefono'] ?? '')) ?></td>
                        <td>
                            <span class="estado-badge estado-<?= htmlspecialchars((string) $turno['estado']) ?>">
                                <?= htmlspecialchars((string) $turno['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="acciones">
                                <a href="/turnos.php?action=editar&id=<?= (int) $turno['id_turno'] ?>" class="btn btn-secondary btn-small">Editar</a>
                                <form method="post" action="/turnos.php?action=eliminar&id=<?= (int) $turno['id_turno'] ?>" onsubmit="return confirm('¿Confirmas eliminar este turno?')" style="margin: 0;">
                                    <button type="submit" class="btn btn-danger btn-small">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($turnos)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #64748b;">No hay turnos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('formModal').classList.add('active');
}

function closeModal() {
    document.getElementById('formModal').classList.remove('active');
}

function aplicarFiltros() {
    const estado = document.getElementById('filtro_estado').value;
    const fecha = document.getElementById('filtro_fecha').value;
    let url = '/turnos.php';
    const params = [];
    if (estado) params.push('filtro_estado=' + encodeURIComponent(estado));
    if (fecha) params.push('filtro_fecha=' + encodeURIComponent(fecha));
    if (params.length > 0) url += '?' + params.join('&');
    window.location.href = url;
}

// Abrir modal si viene de editar o crear con error
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('formModal');
    if (modal.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    }
});
</script>
</body>
</html>
