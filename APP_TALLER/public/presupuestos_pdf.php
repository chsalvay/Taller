<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/src/Auth.php';
require_once $projectRoot . '/src/Database.php';

use App\Src\Auth;
use App\Src\Database;

Auth::startSession();
Auth::requireRole('Admin');

function pdfEscape(string $text): string
{
    $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    if ($encoded === false) {
        $encoded = $text;
    }

    $encoded = str_replace(["\r\n", "\r", "\n"], ' ', $encoded);
    $encoded = str_replace('\\', '\\\\', $encoded);
    $encoded = str_replace('(', '\\(', $encoded);
    $encoded = str_replace(')', '\\)', $encoded);

    return $encoded;
}

function textLen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function textSubstr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
    }

    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function wrapText(string $text, int $maxChars = 90): array
{
    $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if ($normalized === '') {
        return [''];
    }

    $words = explode(' ', $normalized);
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (textLen($candidate) <= $maxChars) {
            $line = $candidate;
            continue;
        }

        if ($line !== '') {
            $lines[] = $line;
            $line = $word;
            continue;
        }

        $lines[] = textSubstr($word, 0, $maxChars);
        $line = textSubstr($word, $maxChars);
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function formatMoney(float $value): string
{
    return '$ ' . number_format($value, 2, ',', '.');
}

function pdfText(float $x, float $y, int $size, string $text): string
{
    return 'BT /F1 ' . $size . ' Tf ' . $x . ' ' . $y . ' Td (' . pdfEscape($text) . ') Tj ET';
}

function pdfEstimatedTextWidth(int $size, string $text): float
{
    return textLen($text) * ($size * 0.52);
}

function pdfCenteredText(float $centerX, float $y, int $size, string $text): string
{
    $x = $centerX - (pdfEstimatedTextWidth($size, $text) / 2);

    return pdfText($x, $y, $size, $text);
}

function pdfRightAlignedText(float $rightX, float $y, int $size, string $text): string
{
    $x = $rightX - pdfEstimatedTextWidth($size, $text);

    return pdfText($x, $y, $size, $text);
}

function pdfRect(float $x, float $y, float $width, float $height, bool $fill = false): string
{
    return $x . ' ' . $y . ' ' . $width . ' ' . $height . ' re ' . ($fill ? 'f' : 'S');
}

function pdfLine(float $x1, float $y1, float $x2, float $y2): string
{
    return $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . ' l S';
}

function buildSimplePdf(array $pages): string
{
    $objects = [];

    $addObject = static function (string $value) use (&$objects): int {
        $objects[] = $value;

        return count($objects);
    };

    $catalogId = $addObject('<< /Type /Catalog /Pages 2 0 R >>');
    $pagesId = $addObject('');
    $fontId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $pageIds = [];
    foreach ($pages as $pageCommands) {
        $stream = implode("\n", $pageCommands);
        $contentId = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");
        $pageId = $addObject(
            '<< /Type /Page /Parent ' . $pagesId . ' 0 R /MediaBox [0 0 595 842] ' .
            '/Resources << /Font << /F1 ' . $fontId . ' 0 R >> >> ' .
            '/Contents ' . $contentId . ' 0 R >>'
        );
        $pageIds[] = $pageId;
    }

    $kids = implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds));
    $objects[$pagesId - 1] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $value) {
        $objectNumber = $index + 1;
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= $objectNumber . " 0 obj\n" . $value . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n";
    $pdf .= '0 ' . (count($objects) + 1) . "\n";
    $pdf .= sprintf('%010d %05d f %s', 0, 65535, "\n");

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf('%010d %05d n %s', $offsets[$i], 0, "\n");
    }

    $pdf .= "trailer\n";
    $pdf .= '<< /Size ' . (count($objects) + 1) . ' /Root ' . $catalogId . ' 0 R >>' . "\n";
    $pdf .= "startxref\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= '%%EOF';

    return $pdf;
}

$idPresupuesto = (int) ($_GET['id'] ?? 0);
if ($idPresupuesto <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ID de presupuesto inválido.';
    exit;
}

try {
    $pdo = Database::connect($projectRoot);

    $stmt = $pdo->prepare(
        'SELECT id_presupuesto, numero_presupuesto, fecha, cliente, monto_total
         FROM presupuesto
         WHERE id_presupuesto = :id_presupuesto AND activo = 1
         LIMIT 1'
    );
    $stmt->execute(['id_presupuesto' => $idPresupuesto]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presupuesto) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'No se encontró el presupuesto solicitado.';
        exit;
    }

    $stmtDetalle = $pdo->prepare(
        'SELECT material, cantidad, precio_venta
         FROM presupuesto_detalle
         WHERE id_presupuesto = :id_presupuesto
         ORDER BY id_detalle ASC'
    );
    $stmtDetalle->execute(['id_presupuesto' => $idPresupuesto]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error al generar el PDF: ' . $e->getMessage();
    exit;
}

$itemRows = [];
if ($detalles === []) {
    $itemRows[] = [
        'material_lines' => ['Sin ítems registrados.'],
        'cantidad' => '',
        'precio_venta' => '',
        'subtotal' => '',
    ];
} else {
    foreach ($detalles as $item) {
        $material = trim((string) ($item['material'] ?? 'Material'));
        $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
        $precioVenta = (float) ($item['precio_venta'] ?? 0);
        $subtotal = $cantidad * $precioVenta;

        $itemRows[] = [
            'material_lines' => wrapText($material, 32),
            'cantidad' => (string) $cantidad,
            'precio_venta' => formatMoney($precioVenta),
            'subtotal' => formatMoney($subtotal),
        ];
    }
}

$chunks = [];
$currentChunk = [];
$lineBudget = 18;
$usedLines = 0;

foreach ($itemRows as $row) {
    $entryCost = max(1, count($row['material_lines'])) + 1;
    if ($currentChunk !== [] && ($usedLines + $entryCost) > $lineBudget) {
        $chunks[] = $currentChunk;
        $currentChunk = [];
        $usedLines = 0;
        $lineBudget = 24;
    }

    $currentChunk[] = $row;
    $usedLines += $entryCost;
}

if ($currentChunk !== [] || $chunks === []) {
    $chunks[] = $currentChunk;
}

$pageCount = count($chunks);
$pages = [];

foreach ($chunks as $pageIndex => $chunkItems) {
    $pageNumber = $pageIndex + 1;
    $commands = [];
    $tableLeft = 50.0;
    $tableRight = 545.0;
    $colCantidadLeft = 266.0;
    $colPrecioLeft = 348.0;
    $colSubtotalLeft = 448.0;
    $headerTitle = 'Presupuesto';
    $headerNumber = 'Presupuesto #' . (string) ($presupuesto['numero_presupuesto'] ?? $presupuesto['id_presupuesto']);
    $headerGenerated = 'Generado el ' . date('d/m/Y H:i');

    $commands[] = '1 1 1 rg';
    $commands[] = pdfRect(36, 772, 523, 42, true);
    $commands[] = '0.82 0.87 0.93 RG';
    $commands[] = '1 w';
    $commands[] = pdfRect(36, 772, 523, 42, false);
    $commands[] = '0 0 0 rg';
    $commands[] = pdfText(50, 792, 18, $headerTitle);
    $commands[] = pdfCenteredText(297.5, 792, 11, $headerNumber);
    $commands[] = pdfRightAlignedText(545, 792, 10, $headerGenerated);

    $commands[] = '0.82 0.87 0.93 RG';
    $commands[] = '1 w';
    $commands[] = pdfRect(36, 692, 523, 64, false);
    $commands[] = '0.12 0.18 0.29 rg';
    $commands[] = pdfText(50, 739, 11, 'Datos principales');
    $commands[] = '0.72 0.78 0.86 RG';
    $commands[] = pdfLine(50, 733, 545, 733);
    $commands[] = '0 0 0 rg';
    $commands[] = pdfText(50, 710, 10, 'Cliente');
    $commands[] = pdfText(145, 710, 11, (string) ($presupuesto['cliente'] ?? ''));
    $commands[] = pdfText(320, 710, 10, 'Fecha');
    $commands[] = pdfText(405, 710, 11, (string) ($presupuesto['fecha'] ?? ''));

    if ($pageIndex === 0) {
        $itemsTop = 680;
    } else {
        $commands[] = '0.40 0.46 0.56 rg';
        $commands[] = pdfText(50, 650, 10, 'Continuación del detalle de ítems');
        $itemsTop = 628;
    }

    $itemLineCount = 0;
    foreach ($chunkItems as $row) {
        $itemLineCount += max(1, count($row['material_lines']));
    }
    $itemGapCount = max(0, count($chunkItems) - 1);
    $itemsHeight = 64 + ($itemLineCount * 14) + ($itemGapCount * 10);

    if ($pageIndex === $pageCount - 1) {
        $itemsHeight += 54;
    }

    $itemsBottom = max(72, $itemsTop - $itemsHeight);

    $commands[] = '0.82 0.87 0.93 RG';
    $commands[] = pdfRect(36, $itemsBottom, 523, $itemsHeight, false);
    $headerRowTop = $itemsTop - 28;
    $headerRowBottom = $itemsTop - 50;
    $commands[] = '0.12 0.18 0.29 rg';
    $commands[] = pdfText(50, $itemsTop - 14, 11, 'Detalle de ítems');
    $commands[] = '0.97 0.98 1 rg';
    $commands[] = pdfRect($tableLeft, $headerRowBottom, $tableRight - $tableLeft, $headerRowTop - $headerRowBottom, true);
    $commands[] = '0.72 0.78 0.86 RG';
    $commands[] = pdfLine($tableLeft, $itemsTop - 20, $tableRight, $itemsTop - 20);
    $commands[] = pdfLine($tableLeft, $headerRowBottom, $tableRight, $headerRowBottom);
    $commands[] = '0.12 0.18 0.29 rg';
    $commands[] = pdfText($tableLeft + 4, $itemsTop - 39, 9, 'Material');
    $commands[] = pdfCenteredText(($colCantidadLeft + $colPrecioLeft) / 2, $itemsTop - 39, 9, 'Cant.');
    $commands[] = pdfCenteredText(($colPrecioLeft + $colSubtotalLeft) / 2, $itemsTop - 39, 9, 'Precio venta');
    $commands[] = pdfCenteredText(($colSubtotalLeft + $tableRight) / 2, $itemsTop - 39, 9, 'Subtotal');
    $commands[] = '0.72 0.78 0.86 RG';
    $commands[] = pdfLine($colCantidadLeft, $itemsBottom + 8, $colCantidadLeft, $headerRowTop);
    $commands[] = pdfLine($colPrecioLeft, $itemsBottom + 8, $colPrecioLeft, $headerRowTop);
    $commands[] = pdfLine($colSubtotalLeft, $itemsBottom + 8, $colSubtotalLeft, $headerRowTop);
    $commands[] = '0 0 0 rg';

    $itemY = $itemsTop - 72;
    foreach ($chunkItems as $row) {
        $rowTopY = $itemY;
        foreach ($row['material_lines'] as $line) {
            $commands[] = pdfText($tableLeft + 4, $itemY, 10, $line);
            $itemY -= 14;
        }
        $rowBottomY = $itemY + 4;
        $rowCenterY = $rowTopY;

        if ($row['cantidad'] !== '') {
            $commands[] = pdfCenteredText(($colCantidadLeft + $colPrecioLeft) / 2, $rowCenterY, 10, $row['cantidad']);
            $commands[] = pdfRightAlignedText($colSubtotalLeft - 8, $rowCenterY, 9, $row['precio_venta']);
            $commands[] = pdfRightAlignedText($tableRight - 8, $rowCenterY, 9, $row['subtotal']);
        }

        $commands[] = '0.88 0.91 0.95 RG';
        $commands[] = pdfLine($tableLeft, $rowBottomY - 7, $tableRight, $rowBottomY - 7);
        $commands[] = '0 0 0 rg';
        $itemY -= 12;
    }

    if ($pageIndex === $pageCount - 1) {
        $totalRowTop = $itemsBottom + 30;
        $commands[] = '0.93 0.95 0.99 rg';
        $commands[] = pdfRect($tableLeft, $itemsBottom + 4, $tableRight - $tableLeft, 22, true);
        $commands[] = '0.55 0.64 0.78 RG';
        $commands[] = '1.2 w';
        $commands[] = pdfLine($tableLeft, $totalRowTop, $tableRight, $totalRowTop);
        $commands[] = pdfLine($colSubtotalLeft, $itemsBottom + 4, $colSubtotalLeft, $totalRowTop);
        $commands[] = '0.12 0.18 0.29 rg';
        $commands[] = pdfRightAlignedText($colSubtotalLeft - 10, $itemsBottom + 12, 10, 'Monto total');
        $commands[] = pdfRightAlignedText($tableRight - 8, $itemsBottom + 12, 10, formatMoney((float) ($presupuesto['monto_total'] ?? 0)));
        $commands[] = '1 w';
    }

    $commands[] = '0.45 0.50 0.58 rg';
    $commands[] = pdfText(50, 42, 9, 'APP_TALLER');
    $commands[] = pdfText(470, 42, 9, 'Pagina ' . $pageNumber . ' de ' . $pageCount);

    $pages[] = $commands;
}

$pdf = buildSimplePdf($pages);
$fileName = 'presupuesto_' . (int) $presupuesto['id_presupuesto'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));

echo $pdf;
