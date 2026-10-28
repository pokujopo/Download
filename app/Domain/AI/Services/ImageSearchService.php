<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\ImageSearchProviderContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ImageSearchService
{
    public function __construct(
        private ImageSearchProviderContract $provider,
    ) {
    }

    /**
     * @return array{
     *     file: UploadedFile,
     *     type: string,
     *     source_url: string,
     * }
     */
    public function findAndDownload(string $query): array
    {
        $images = $this->provider->search($query);

        if ($images === []) {
            throw new RuntimeException(
                "No images found for: {$query}"
            );
        }

        foreach ($images as $image) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'OncePostAnywhere/1.0',
                    ])
                    ->get($image['url']);

                if ($response->failed() || $response->body() === '') {
                    continue;
                }

                $contentType = strtolower(
                    $response->header('Content-Type', '')
                );

                $isImage = str_contains($contentType, 'jpeg')
                    || str_contains($contentType, 'jpg')
                    || str_contains($contentType, 'png')
                    || str_contains($contentType, 'webp');

                if (!$isImage) {
                    continue;
                }

                $sourcePath = tempnam(
                    sys_get_temp_dir(),
                    'opa_source_'
                );

                file_put_contents(
                    $sourcePath,
                    $response->body()
                );

                $normalizedPath = $this->normalizeForInstagram(
                    $sourcePath
                );

                @unlink($sourcePath);

                return [
                    'file' => new UploadedFile(
                        $normalizedPath,
                        'generated-image.jpg',
                        'image/jpeg',
                        null,
                        true,
                    ),
                    'type' => 'image',
                    'source_url' => $image['url'],
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        throw new RuntimeException(
            "Unable to download any usable image for: {$query}"
        );
    }

    private function normalizeForInstagram(string $sourcePath): string
    {
        $imageInfo = @getimagesize($sourcePath);

        if ($imageInfo === false) {
            throw new RuntimeException(
                'Downloaded file is not a valid image.'
            );
        }

        $source = @imagecreatefromstring(
            file_get_contents($sourcePath)
        );

        if ($source === false) {
            throw new RuntimeException(
                'Unable to decode downloaded image.'
            );
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $canvasSize = 1080;

        $canvas = imagecreatetruecolor(
            $canvasSize,
            $canvasSize
        );

        if ($canvas === false) {
            imagedestroy($source);

            throw new RuntimeException(
                'Unable to create image canvas.'
            );
        }

        /*
         * White background.
         * This also handles transparent PNG/WebP images.
         */
        $background = imagecolorallocate(
            $canvas,
            255,
            255,
            255
        );

        imagefill(
            $canvas,
            0,
            0,
            $background
        );

        /*
         * Fit the original image inside the 1080x1080 canvas
         * without cropping any part of it.
         */
        $scale = min(
            $canvasSize / $sourceWidth,
            $canvasSize / $sourceHeight
        );

        $newWidth = max(
            1,
            (int) round($sourceWidth * $scale)
        );

        $newHeight = max(
            1,
            (int) round($sourceHeight * $scale)
        );

        $x = (int) (($canvasSize - $newWidth) / 2);
        $y = (int) (($canvasSize - $newHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $x,
            $y,
            0,
            0,
            $newWidth,
            $newHeight,
            $sourceWidth,
            $sourceHeight
        );

        $outputPath = tempnam(
            sys_get_temp_dir(),
            'opa_normalized_'
        );

        if ($outputPath === false) {
            imagedestroy($source);
            imagedestroy($canvas);

            throw new RuntimeException(
                'Unable to create normalized image file.'
            );
        }

        /*
         * JPEG gives us a predictable Instagram-compatible image.
         */
        if (!imagejpeg($canvas, $outputPath, 90)) {
            imagedestroy($source);
            imagedestroy($canvas);
            @unlink($outputPath);

            throw new RuntimeException(
                'Unable to save normalized image.'
            );
        }

        imagedestroy($source);
        imagedestroy($canvas);

        return $outputPath;
    }
}