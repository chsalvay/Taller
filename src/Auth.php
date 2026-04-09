<?php

declare(strict_types=1);

namespace App\Src;

use PDO;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return self::isLoggedIn() ? $_SESSION['user'] : null;
    }

    public static function attemptLogin(PDO $pdo, string $username, string $password): bool
    {
        $sql = 'SELECT u.id, u.username, u.password, u.activo, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE u.username = :username
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $record = $stmt->fetch();

        if (!$record || (int) $record['activo'] !== 1) {
            return false;
        }

        if ((string) $record['password'] !== $password) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $record['id'],
            'username' => (string) $record['username'],
            'rol' => (string) $record['rol'],
        ];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /index.php');
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        $user = self::user();

        if (!$user || !isset($user['rol']) || $user['rol'] !== $role) {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }
    }
}
