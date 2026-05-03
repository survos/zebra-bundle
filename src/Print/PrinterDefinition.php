<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Print;

final readonly class PrinterDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $host = null,
        public int $port = 9100,
        public float $timeout = 5.0,
        public ?string $queue = null,
        public ?string $path = null,
        public ?string $device = null,
        public ?string $vendorId = null,
        public ?string $productId = null,
        public int $dpi = 203,
        public float $labelWidthInches = 4.0,
        public float $labelHeightInches = 2.0,
    ) {
    }

    public function printWidthDots(): int
    {
        return $this->inchesToDots($this->labelWidthInches);
    }

    public function labelLengthDots(): int
    {
        return $this->inchesToDots($this->labelHeightInches);
    }

    private function inchesToDots(float $inches): int
    {
        return (int) round($inches * $this->dpi);
    }
}
