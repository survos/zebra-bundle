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

        match ($printer->type) {
            'tcp' => $this->printToTcp($printer, $zpl),
            'usb' => $this->printToUsb($printer, $zpl),
            'file' => $this->printToFile($printer, $zpl),
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
            $bytes = fwrite($socket, $zpl);
            if (false === $bytes || $bytes < strlen($zpl)) {
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
}
