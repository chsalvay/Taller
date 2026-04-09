<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Database.php';
require_once $projectRoot . '/src/Auth.php';

use App\Src\Auth;
use App\Src\Database;

$config = require $projectRoot . '/config/app.php';

Auth::startSession();

if (Auth::isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    if ($username === '' || $password === '') {
        $error = 'Debes completar usuario y contrasena.';
    } else {
        try {
            $pdo = Database::connect($projectRoot);
            if (Auth::attemptLogin($pdo, $username, $password)) {
                header('Location: /dashboard.php');
                exit;
            }
            $error = 'Usuario, contrasena o rol invalido.';
        } catch (Throwable $e) {
            $error = 'No fue posible validar el acceso: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($config['name']) ?></title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 0; background: #eef2f7; min-height: 100vh; display: grid; place-items: center; }
        .card { width: min(420px, 92vw); padding: 1.5rem; border: 1px solid #d5dbe7; border-radius: 12px; background: #fff; }
        h1 { margin-top: 0; margin-bottom: 0.35rem; }
        p { margin-top: 0; color: #4b5563; }
        label { display: block; margin-top: 0.8rem; margin-bottom: 0.3rem; font-weight: 600; }
        input { width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; margin-top: 1rem; padding: 0.75rem; border: none; border-radius: 8px; background: #0f172a; color: #fff; font-weight: 700; cursor: pointer; }
        .error { margin-top: 0.75rem; color: #b91c1c; font-size: 0.95rem; }
        .hint { margin-top: 1rem; padding: 0.75rem; border-radius: 8px; background: #f8fafc; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="card">
    <h1><?= htmlspecialchars($config['name']) ?></h1>
    <p>Ingreso al sistema</p>

    <form method="post" action="/index.php" autocomplete="off">
        <label for="username">Usuario</label>
        <input id="username" name="username" type="text" required>

        <label for="password">Contrasena</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Iniciar sesion</button>
    </form>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="hint">
        Usuario inicial: <strong>Admin</strong><br>
        Contrasena inicial: <strong>123456</strong>
    </div>
</div>
</body>
</html>
