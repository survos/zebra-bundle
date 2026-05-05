<?php

declare(strict_types=1);

use Survos\ZebraBundle\LabelSize\LabelSizeRegistry;
use Survos\ZebraBundle\Print\PrinterRegistry;
use Survos\ZebraBundle\Print\PrinterService;
use Survos\ZebraBundle\Preview\PreviewRequest;

require __DIR__ . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

$labels = new LabelSizeRegistry([], 'gk420d_2_25x1_25');
$gk420d = $labels->getDefault();
assertSameValue(2.25, $gk420d?->widthInches, 'GK420d width should be registered.');
assertSameValue(1.25, $gk420d?->heightInches, 'GK420d height should be registered.');

$request = new PreviewRequest('^XA^FO20,20^FDHello^FS^XZ', 2.25, 1.25);
assertSameValue(32, strlen($request->cacheKey()), 'Preview cache key should use xxh128.');

$spoolDir = sys_get_temp_dir() . '/survos-zebra-smoke-' . bin2hex(random_bytes(4));
if (!mkdir($spoolDir) && !is_dir($spoolDir)) {
    throw new RuntimeException(sprintf('Unable to create smoke-test spool directory "%s".', $spoolDir));
}

$registry = new PrinterRegistry([
    'spool' => [
        'type' => 'file',
        'path' => $spoolDir,
        'dpi' => 203,
        'label_width_in' => 2.25,
        'label_height_in' => 1.25,
    ],
], 'spool');

(new PrinterService($registry))->printLabel('^FO20,20^A0N,30,30^FDHELLO^FS');

$files = glob($spoolDir . '/*.zpl') ?: [];
assertSameValue(1, count($files), 'File printer should write one spool file.');

$payload = file_get_contents($files[0]);
if (!str_contains((string) $payload, "^XA^SZ2^XZ\n^XA\n^PW457\n^LL254")) {
    throw new RuntimeException('Spool payload should include ZPL mode guard and configured media dimensions.');
}

unlink($files[0]);
rmdir($spoolDir);

echo "Smoke test passed.\n";
