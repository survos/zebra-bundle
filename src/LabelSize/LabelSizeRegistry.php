<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\LabelSize;

final readonly class LabelSizeRegistry
{
    /**
     * @param array<string, LabelSizeDefinition> $labelSizes
     */
    public function __construct(
        private array $labelSizes,
        private ?string $defaultLabelSize = null,
    ) {
    }

    public function getDefault(): ?LabelSizeDefinition
    {
        if (null === $this->defaultLabelSize) {
            return null;
        }

        return $this->get($this->defaultLabelSize);
    }

    public function get(string $name): LabelSizeDefinition
    {
        if (!isset($this->labelSizes[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown Zebra label size "%s".', $name));
        }

        return $this->labelSizes[$name];
    }

    /**
     * @return array<string, LabelSizeDefinition>
     */
    public function all(): array
    {
        return $this->labelSizes;
    }

    /**
     * @param array<string, array{width_inches: float|int, height_inches: float|int, dpmm?: int, description?: string|null}> $customLabelSizes
     * @return array<string, LabelSizeDefinition>
     */
    public static function buildDefinitions(
        array $customLabelSizes,
        float $fallbackWidthInches,
        float $fallbackHeightInches,
        int $fallbackDpmm,
    ): array {
        $definitions = [];

        foreach (self::defaultDefinitions($fallbackWidthInches, $fallbackHeightInches, $fallbackDpmm) as $definition) {
            $definitions[$definition->name] = $definition;
        }

        foreach ($customLabelSizes as $name => $config) {
            $definitions[$name] = new LabelSizeDefinition(
                $name,
                (float) $config['width_inches'],
                (float) $config['height_inches'],
                (int) ($config['dpmm'] ?? $fallbackDpmm),
                $config['description'] ?? null,
            );
        }

        return $definitions;
    }

    /**
     * @return list<LabelSizeDefinition>
     */
    private static function defaultDefinitions(
        float $fallbackWidthInches,
        float $fallbackHeightInches,
        int $fallbackDpmm,
    ): array {
        return [
            new LabelSizeDefinition(
                'default',
                $fallbackWidthInches,
                $fallbackHeightInches,
                $fallbackDpmm,
                'Bundle default label size',
            ),
            new LabelSizeDefinition(
                '4x2',
                4.0,
                2.0,
                8,
                'Standard 4 x 2 inch shipping label',
            ),
            new LabelSizeDefinition(
                'gk420d_2_25x1_25',
                2.25,
                1.25,
                8,
                'Zebra GK420d 2.25 x 1.25 inch label',
            ),
        ];
    }
}
