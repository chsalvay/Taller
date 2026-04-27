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
        $commands = $pageCommands;
        $stream = implode("\n", $commands);
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
    $pdf .= 'xref' . "\n";
    $pdf .= '0 ' . (count($objects) + 1) . "\n";
    $pdf .= sprintf('%010d %05d f %s', 0, 65535, "\n");

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf('%010d %05d n %s', $offsets[$i], 0, "\n");
    }

    $pdf .= 'trailer' . "\n";
    $pdf .= '<< /Size ' . (count($objects) + 1) . ' /Root ' . $catalogId . ' 0 R >>' . "\n";
    $pdf .= 'startxref' . "\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= '%%EOF';

    return $pdf;
}

$idOrden = (int) ($_GET['id'] ?? 0);
if ($idOrden <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ID de orden inválido.';
    exit;
}

try {
    $pdo = Database::connect($projectRoot);

    $stmtOrden = $pdo->prepare(
        'SELECT id, cliente, vehiculo, patente, descripcion, estado, fecha_ot, fecha_creacion
         FROM ordenes_trabajo
         WHERE id = :id
         LIMIT 1'
    );
    $stmtOrden->execute(['id' => $idOrden]);
    $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'No se encontró la orden solicitada.';
        exit;
    }

    $stmtDetalle = $pdo->prepare(
        'SELECT d.id_repuesto,
                d.descripcion_libre,
                d.cantidad,
                r.codigo,
                r.nombre AS repuesto_nombre
         FROM ordenes_trabajo_detalle d
         LEFT JOIN repuestos r ON r.id_repuesto = d.id_repuesto
         WHERE d.id_orden = :id
         ORDER BY d.id ASC'
    );
    $stmtDetalle->execute(['id' => $idOrden]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error al generar el PDF: ' . $e->getMessage();
    exit;
}

$estadoMap = [
    'abierta' => 'Abierta',
    'en_progreso' => 'En progreso',
    'cerrada' => 'Cerrada',
];

$descripcion = trim((string) ($orden['descripcion'] ?? ''));
$descripcionItems = array_values(array_filter(array_map(
    static fn(string $item): string => trim($item),
    explode('|', $descripcion)
), static fn(string $item): bool => $item !== ''));

$descripcionEntries = [];
if ($descripcionItems === []) {
    $descripcionEntries[] = ['Sin descripcion registrada.'];
} else {
    foreach ($descripcionItems as $descripcionItem) {
        $descripcionEntries[] = wrapText($descripcionItem, 98);
    }
}

$itemTexts = [];
if (count($detalles) === 0) {
    $itemTexts[] = 'Sin items registrados.';
} else {
    foreach ($detalles as $item) {
        $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
        if (!empty($item['id_repuesto'])) {
            $nombre = trim((string) ($item['repuesto_nombre'] ?? 'Repuesto'));
            $itemTexts[] = $nombre . ' x' . $cantidad;
            continue;
        }

        $descLibre = trim((string) ($item['descripcion_libre'] ?? 'Item libre'));
        $itemTexts[] = $descLibre . ' x' . $cantidad;
    }
}

$itemEntries = [];
foreach ($itemTexts as $itemText) {
    $itemEntries[] = wrapText($itemText, 78);
}

$chunks = [];
$currentChunk = [];
$lineBudget = 18;
$usedLines = 0;

foreach ($itemEntries as $entryLines) {
    $entryCost = count($entryLines) + 1;
    if ($currentChunk !== [] && ($usedLines + $entryCost) > $lineBudget) {
        $chunks[] = $currentChunk;
        $currentChunk = [];
        $usedLines = 0;
        $lineBudget = 28;
    }

    $currentChunk[] = $entryLines;
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
    $headerOrderText = 'Orden #' . (int) $orden['id'];
    $headerGeneratedText = 'Generado el ' . date('d/m/Y H:i');

    $commands[] = '1 1 1 rg';
    $commands[] = pdfRect(36, 772, 523, 42, true);
    $commands[] = '0.82 0.87 0.93 RG';
    $commands[] = '1 w';
    $commands[] = pdfRect(36, 772, 523, 42, false);
    $commands[] = '0 0 0 rg';
    $commands[] = pdfText(50, 792, 18, 'Orden de trabajo');
    $commands[] = pdfCenteredText(297.5, 792, 11, $headerOrderText);
    $commands[] = '0 0 0 rg';
    $commands[] = pdfRightAlignedText(545, 792, 10, $headerGeneratedText);

    $commands[] = '0.82 0.87 0.93 RG';
    $commands[] = '1 w';
    $commands[] = pdfRect(36, 680, 523, 76, false);
    $commands[] = '0.12 0.18 0.29 rg';
    $commands[] = pdfText(50, 739, 11, 'Datos principales');
    $commands[] = '0.72 0.78 0.86 RG';
    $commands[] = pdfLine(50, 733, 545, 733);
    $commands[] = '0 0 0 rg';
    $commands[] = pdfText(50, 714, 10, 'Cliente');
    $commands[] = pdfText(145, 714, 11, (string) ($orden['cliente'] ?? ''));
    $commands[] = pdfText(50, 695, 10, 'Vehículo');
    $commands[] = pdfText(145, 695, 11, (string) ($orden['vehiculo'] ?? ''));
    $commands[] = pdfText(320, 714, 10, 'Fecha OT');
    $commands[] = pdfText(405, 714, 11, (string) ($orden['fecha_ot'] ?? ''));
    $commands[] = pdfText(320, 695, 10, 'Patente');
    $commands[] = pdfText(405, 695, 11, (string) ($orden['patente'] ?? ''));

    if ($pageIndex === 0) {
        $descTop = 668;
        $descLineCount = 0;
        foreach ($descripcionEntries as $entryLines) {
            $descLineCount += count($entryLines);
        }
        $descGapCount = max(0, count($descripcionEntries) - 1);
        $descHeight = 40 + ($descLineCount * 14) + ($descGapCount * 4);
        $descBottom = $descTop - $descHeight;
        $commands[] = '0.82 0.87 0.93 RG';
        $commands[] = pdfRect(36, $descBottom, 523, $descHeight, false);
        $commands[] = '0.12 0.18 0.29 rg';
        $commands[] = pdfText(50, $descTop - 14, 11, 'Descripción / motivo');
        $commands[] = '0.72 0.78 0.86 RG';
        $commands[] = pdfLine(50, $descTop - 20, 545, $descTop - 20);
        $commands[] = '0 0 0 rg';

        $descY = $descTop - 40;
        foreach ($descripcionEntries as $entryLines) {
            foreach ($entryLines as $line) {
                $commands[] = pdfText(50, $descY, 10, $line);
                $descY -= 14;
            }
            $descY -= 4;
        }

        $itemsTop = $descBottom - 12;
    } else {
        $commands[] = '0.40 0.46 0.56 rg';
        $commands[] = pdfText(50, 624, 10, 'Continuación del detalle de ítems');
        $itemsTop = 602;
    }

    $itemLineCount = 0;
    foreach ($chunkItems as $entryLines) {
        $itemLineCount += count($entryLines);
    }
    $itemGapCount = max(0, count($chunkItems) - 1);
    $itemsHeight = 40 + ($itemLineCount * 14) + ($itemGapCount * 4);
    $itemsBottom = max(72, $itemsTop - $itemsHeight);

    $commands[] = '0.82 0.87 0.93 RG';
    $commands[] = pdfRect(36, $itemsBottom, 523, $itemsHeight, false);
    $commands[] = '0.12 0.18 0.29 rg';
    $commands[] = pdfText(50, $itemsTop - 14, 11, 'Detalle de ítems');
    $commands[] = '0.72 0.78 0.86 RG';
    $commands[] = pdfLine(50, $itemsTop - 20, 545, $itemsTop - 20);
    $commands[] = '0 0 0 rg';

    $itemY = $itemsTop - 40;
    foreach ($chunkItems as $entryLines) {
        foreach ($entryLines as $entryLine) {
            $commands[] = pdfText(50, $itemY, 10, $entryLine);
            $itemY -= 14;
        }
        $itemY -= 4;
    }

    $commands[] = '0.45 0.50 0.58 rg';
    $commands[] = pdfText(50, 42, 9, 'APP_TALLER');
    $commands[] = pdfText(470, 42, 9, 'Pagina ' . $pageNumber . ' de ' . $pageCount);

    $pages[] = $commands;
}

$pdf = buildSimplePdf($pages);
$fileName = 'orden_trabajo_' . (int) $orden['id'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));

echo $pdf;
