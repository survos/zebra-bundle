<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Twig;

use Survos\ZebraBundle\Preview\PreviewRequest;
use Survos\ZebraBundle\Preview\PreviewServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ZebraExtension extends AbstractExtension
{
    public function __construct(
        private readonly PreviewServiceInterface $previewService,
        private readonly int $defaultDpmm,
        private readonly float $defaultWidthInches,
        private readonly float $defaultHeightInches,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('zpl_preview', $this->zplPreview(...)),
            new TwigFunction('zpl_preview_result', $this->zplPreviewResult(...)),
        ];
    }

    public function zplPreview(
        string $zpl,
        ?float $width = null,
        ?float $height = null,
        ?int $dpmm = null,
        string $format = 'png',
        int $index = 0,
    ): string {
        return $this->zplPreviewResult($zpl, $width, $height, $dpmm, $format, $index)->toDataUri();
    }

    public function zplPreviewResult(
        string $zpl,
        ?float $width = null,
        ?float $height = null,
        ?int $dpmm = null,
        string $format = 'png',
        int $index = 0,
    ): \Survos\ZebraBundle\Preview\PreviewResult {
        return $this->previewService->preview(new PreviewRequest(
            zpl: $zpl,
            widthInches: $width ?? $this->defaultWidthInches,
            heightInches: $height ?? $this->defaultHeightInches,
            dpmm: $dpmm ?? $this->defaultDpmm,
            format: $format,
            index: $index,
        ));
    }
}
