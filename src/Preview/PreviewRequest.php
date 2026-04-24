<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Preview;

final readonly class PreviewRequest
{
    public function __construct(
        public string $zpl,
        public float $widthInches = 4.0,
        public float $heightInches = 2.0,
        public int $dpmm = 8,
        public string $format = 'png',
        public int $index = 0,
        public int $rotation = 0,
    ) {
    }

    public function cacheKey(): string
    {
        return hash('xxh128', json_encode([
            'zpl' => $this->zpl,
            'width' => $this->widthInches,
            'height' => $this->heightInches,
            'dpmm' => $this->dpmm,
            'format' => $this->format,
            'index' => $this->index,
            'rotation' => $this->rotation,
        ], JSON_THROW_ON_ERROR));
    }
}
