<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Database.php';

use App\Src\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect($projectRoot);
    $result = $pdo->query('SELECT 1 AS ok')->fetch();

    echo json_encode([
        'status' => 'ok',
        'database' => $result['ok'] === 1 ? 'connected' : 'unknown',
        'app' => 'APP_TALLER',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'database' => 'disconnected',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
