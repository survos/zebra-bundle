<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Print;

final readonly class PrinterService implements PrinterServiceInterface
{
    public function __construct(
        private PrinterRegistry $printerRegistry,
    ) {
    }

    public function print(string $zpl, ?string $printerName = null): void
    {
        $printer = null === $printerName ? $this->printerRegistry->getDefault() : $this->printerRegistry->get($printerName);
        $payload = $this->withZplModeGuard($zpl);

        match ($printer->type) {
            'tcp' => $this->printToTcp($printer, $payload),
            'cups' => $this->printToCups($printer, $payload),
            'usb' => $this->printToUsb($printer, $payload),
            'file' => $this->printToFile($printer, $payload),
            'null' => null,
            default => throw new \RuntimeException(sprintf('Zebra printer type "%s" is not implemented yet.', $printer->type)),
        };
    }

    private function printToTcp(PrinterDefinition $printer, string $zpl): void
    {
        if (!$printer->host) {
            throw new \RuntimeException(sprintf('Zebra printer "%s" is missing a host.', $printer->name));
        }

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $printer->host, $printer->port),
            $errorCode,
            $errorMessage,
            $printer->timeout,
        );

        if (false === $socket) {
            throw new \RuntimeException(sprintf('Unable to connect to Zebra printer "%s": %s (%d).', $printer->name, $errorMessage, $errorCode));
        }

        try {
            $bytes = $this->writeAll($socket, $zpl);
            if ($bytes < strlen($zpl)) {
                throw new \RuntimeException(sprintf('Failed to write the full print payload to Zebra printer "%s".', $printer->name));
            }
        } finally {
            fclose($socket);
        }
    }

    private function printToUsb(PrinterDefinition $printer, string $zpl): void
    {
        $device = $printer->device ?: $printer->path;
        if (!$device) {
            throw new \RuntimeException(sprintf('Zebra USB printer "%s" is missing a device path.', $printer->name));
        }

        $bytes = @file_put_contents($device, $zpl);
        if (false === $bytes || $bytes < strlen($zpl)) {
            throw new \RuntimeException(sprintf('Unable to write the print payload to Zebra USB printer "%s" at "%s".', $printer->name, $device));
        }
    }

    private function printToCups(PrinterDefinition $printer, string $zpl): void
    {
        if (!$printer->queue) {
            throw new \RuntimeException(sprintf('Zebra CUPS printer "%s" is missing a queue.', $printer->name));
        }

        $process = proc_open(
            ['lp', '-d', $printer->queue, '-o', 'raw'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException(sprintf('Unable to start CUPS lp process for Zebra printer "%s".', $printer->name));
        }

        try {
            $bytes = $this->writeAll($pipes[0], $zpl);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        } catch (\Throwable $exception) {
            proc_terminate($process);

            throw $exception;
        }

        if ($bytes < strlen($zpl) || 0 !== $exitCode) {
            $output = trim(implode("\n", array_filter([$stdout ?: null, $stderr ?: null])));

            throw new \RuntimeException(sprintf(
                'CUPS failed to print Zebra payload on queue "%s"%s.',
                $printer->queue,
                $output !== '' ? ': ' . $output : '',
            ));
        }
    }

    private function printToFile(PrinterDefinition $printer, string $zpl): void
    {
        if (!$printer->path) {
            throw new \RuntimeException(sprintf('Zebra file printer "%s" is missing a path.', $printer->name));
        }

        $targetPath = $printer->path;
        if (is_dir($targetPath)) {
            $targetPath = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sprintf('zebra-%s.zpl', date('Ymd-His'));
        }

        $bytes = @file_put_contents($targetPath, $zpl);
        if (false === $bytes || $bytes < strlen($zpl)) {
            throw new \RuntimeException(sprintf('Unable to write the print payload for Zebra printer "%s" to "%s".', $printer->name, $targetPath));
        }
    }

    private function withZplModeGuard(string $zpl): string
    {
        return "^XA^SZ2^XZ\n" . ltrim($zpl);
    }

    /**
     * @param resource $stream
     */
    private function writeAll($stream, string $payload): int
    {
        $written = 0;
        $length = strlen($payload);

        while ($written < $length) {
            $bytes = fwrite($stream, substr($payload, $written));
            if (false === $bytes || 0 === $bytes) {
                break;
            }

            $written += $bytes;
        }

        return $written;
    }
}
