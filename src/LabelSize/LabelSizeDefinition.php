<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\LabelSize;

final readonly class LabelSizeDefinition
{
    public function __construct(
        public string $name,
        public float $widthInches,
        public float $heightInches,
        public int $dpmm,
        public ?string $description = null,
    ) {
    }
}
