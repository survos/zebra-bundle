<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Print;

interface PrinterServiceInterface
{
    public function print(string $zpl, ?string $printerName = null): void;
}
