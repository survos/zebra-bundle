<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Print;

interface PrinterServiceInterface
{
    public function print(string $zpl, ?string $printerName = null): void;

    public function printLabel(string $zplBody, ?string $printerName = null): void;

    public function testLabel(?string $printerName = null): void;

    public function calibrate(?string $printerName = null, int $settleSeconds = 3): void;

    public function saveSettings(?string $printerName = null): void;
}
