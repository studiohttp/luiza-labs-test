<?php

namespace App\Modules\Order\Contracts;

use Illuminate\Http\UploadedFile;

interface OrderImporterInterface
{
    public function import(UploadedFile $file): array;
}
