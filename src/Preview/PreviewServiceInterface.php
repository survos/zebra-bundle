<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Preview;

interface PreviewServiceInterface
{
    public function preview(PreviewRequest $request): PreviewResult;
}
