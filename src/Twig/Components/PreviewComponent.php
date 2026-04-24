<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Twig\Components;

use Survos\ZebraBundle\Preview\PreviewRequest;
use Survos\ZebraBundle\Preview\PreviewResult;
use Survos\ZebraBundle\Preview\PreviewServiceInterface;

final class PreviewComponent
{
    public string $zpl = '';
    public ?float $width = null;
    public ?float $height = null;
    public ?int $dpmm = null;
    public string $format = 'png';
    public int $index = 0;
    public string $alt = 'Zebra label preview';
    public bool $showWarnings = true;

    public function __construct(
        private readonly PreviewServiceInterface $previewService,
        private readonly int $defaultDpmm,
        private readonly float $defaultWidthInches,
        private readonly float $defaultHeightInches,
    ) {
    }

    public function getPreview(): PreviewResult
    {
        return $this->previewService->preview(new PreviewRequest(
            zpl: $this->zpl,
            widthInches: $this->width ?? $this->defaultWidthInches,
            heightInches: $this->height ?? $this->defaultHeightInches,
            dpmm: $this->dpmm ?? $this->defaultDpmm,
            format: $this->format,
            index: $this->index,
        ));
    }
}
