<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\MediaStorageContract;
use App\Models\Content;
use Illuminate\Http\UploadedFile;

final class MediaService
{
    public function __construct(
        private MediaStorageContract $storage,
    ) {
    }

    public function create(
        Content $content,
        array $media,
    ): void {
        foreach ($media as $item) {
            if (
                isset($item['file'])
                && $item['file'] instanceof UploadedFile
            ) {
                $this->createFromUpload(
                    content: $content,
                    file: $item['file'],
                    type: $item['type'],
                );

                continue;
            }

            if (!empty($item['public_url'])) {
                $content->media()->create([
                    'type' => $item['type'],
                    'public_url' => $item['public_url'],
                ]);

                continue;
            }

            throw new \InvalidArgumentException(
                'Media must contain either a file or public_url.'
            );
        }
    }

    private function createFromUpload(
        Content $content,
        UploadedFile $file,
        string $type,
    ): void {
        $result = $this->storage->store($file);

        $content->media()->create([
            'type' => $type,
            'storage_disk' => $result['disk'],
            'storage_path' => $result['path'],
            'public_url' => $result['url'],
        ]);
    }
}