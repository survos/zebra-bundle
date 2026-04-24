<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Preview;

final readonly class PreviewResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public string $content,
        public string $mimeType,
        public array $warnings = [],
    ) {
    }

    public function toDataUri(): string
    {
        return sprintf('data:%s;base64,%s', $this->mimeType, base64_encode($this->content));
    }
}
