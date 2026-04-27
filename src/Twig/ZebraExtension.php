<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Twig;

use Survos\ZebraBundle\LabelSize\LabelSizeRegistry;
use Survos\ZebraBundle\Preview\PreviewRequest;
use Survos\ZebraBundle\Preview\PreviewServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ZebraExtension extends AbstractExtension
{
    public function __construct(
        private readonly PreviewServiceInterface $previewService,
        private readonly LabelSizeRegistry $labelSizeRegistry,
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
        string|float|null $width = null,
        ?float $height = null,
        ?int $dpmm = null,
        string $format = 'png',
        int $index = 0,
        ?string $labelSize = null,
    ): string {
        return $this->zplPreviewResult($zpl, $width, $height, $dpmm, $format, $index, $labelSize)->toDataUri();
    }

    public function zplPreviewResult(
        string $zpl,
        string|float|null $width = null,
        ?float $height = null,
        ?int $dpmm = null,
        string $format = 'png',
        int $index = 0,
        ?string $labelSize = null,
    ): \Survos\ZebraBundle\Preview\PreviewResult {
        $resolvedLabelSize = \is_string($width) ? $width : $labelSize;
        $definition = $resolvedLabelSize ? $this->labelSizeRegistry->get($resolvedLabelSize) : $this->labelSizeRegistry->getDefault();
        $resolvedWidth = \is_string($width) ? null : $width;

        return $this->previewService->preview(new PreviewRequest(
            zpl: $zpl,
            widthInches: $resolvedWidth ?? $definition?->widthInches ?? $this->defaultWidthInches,
            heightInches: $height ?? $definition?->heightInches ?? $this->defaultHeightInches,
            dpmm: $dpmm ?? $definition?->dpmm ?? $this->defaultDpmm,
            format: $format,
            index: $index,
        ));
    }
}
