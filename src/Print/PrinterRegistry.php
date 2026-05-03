<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Print;

final readonly class PrinterRegistry
{
    /**
     * @param array<string, array{
     *   type?: string,
     *   host?: string|null,
     *   port?: int,
     *   timeout?: float|int,
     *   queue?: string|null,
     *   path?: string|null,
     *   device?: string|null,
     *   vendor_id?: string|null,
     *   product_id?: string|null,
     *   dpi?: int,
     *   label_width_in?: float|int,
     *   label_height_in?: float|int,
     *   width_inches?: float|int,
     *   height_inches?: float|int
     * }> $printers
     */
    public function __construct(
        private array $printers,
        private ?string $defaultPrinter = null,
    ) {
    }

    public function getDefault(): PrinterDefinition
    {
        if (null === $this->defaultPrinter || '' === $this->defaultPrinter) {
            throw new \RuntimeException('No default Zebra printer is configured.');
        }

        return $this->get($this->defaultPrinter);
    }

    public function get(string $name): PrinterDefinition
    {
        if (!isset($this->printers[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown Zebra printer "%s".', $name));
        }

        $config = $this->printers[$name];

        return new PrinterDefinition(
            $name,
            (string) ($config['type'] ?? 'tcp'),
            $config['host'] ?? null,
            (int) ($config['port'] ?? 9100),
            (float) ($config['timeout'] ?? 5.0),
            $config['queue'] ?? null,
            $config['path'] ?? null,
            $config['device'] ?? null,
            $config['vendor_id'] ?? null,
            $config['product_id'] ?? null,
            (int) ($config['dpi'] ?? 203),
            (float) ($config['label_width_in'] ?? $config['width_inches'] ?? 4.0),
            (float) ($config['label_height_in'] ?? $config['height_inches'] ?? 2.0),
        );
    }
}
