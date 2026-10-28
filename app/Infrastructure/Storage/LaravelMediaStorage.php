<?php

namespace App\Infrastructure\Storage;

use App\Domain\Media\Contracts\MediaStorageContract;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class LaravelMediaStorage implements MediaStorageContract
{
    public function store(UploadedFile $file): array
    {
        //$disk = config('filesystems.default');
        $disk = 'public';

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        $path = $storage->putFile(
            'content-media',
            $file
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'url' => url(Storage::url($path)),
        ];
    }

    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }
}