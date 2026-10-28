<?php

namespace App\Domain\Media\Contracts;

use Illuminate\Http\UploadedFile;

interface MediaStorageContract
{
    public function store(UploadedFile $file): array;

    public function delete(string $disk, string $path): void;
}