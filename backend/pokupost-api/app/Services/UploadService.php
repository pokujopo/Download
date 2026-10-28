<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    public function uploadMedia(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $mediaType = str_starts_with($mime, 'video/') ? 'video' : 'image';

        $folder = $mediaType === 'video' ? 'uploads/videos' : 'uploads/images';
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $filename, 'public');

        return [
            'media_path' => $path,
            'media_url' => Storage::disk('public')->url($path),
            'media_type' => $mediaType,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $mime,
        ];
    }
}