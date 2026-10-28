<?php

namespace App\Http\Controllers;

use App\Domain\Content\Services\ContentService;
use App\Models\Content;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SmartAutocutImportController extends Controller
{
    private const PUBLIC_HOST =
        'smart-autocut-media.s3.us-east-005.backblazeb2.com';

    public function importClip(
        Request $request,
        ContentService $contentService
    ): JsonResponse {
        $data = $request->validate([
            'source' => ['required', 'in:smart_autocut'],
            'job_id' => ['required', 'string', 'max:120'],

            'clip' => ['required', 'array'],
            'clip.clip_id' => ['required', 'string', 'max:120'],
            'clip.object_key' => [
                'required',
                'string',
                'max:500',
                'starts_with:clips/',
            ],
            'clip.video_url' => ['required', 'url', 'max:2048'],
            'clip.caption' => ['nullable', 'string', 'max:2200'],
            'clip.title' => ['nullable', 'string', 'max:255'],
            'clip.reason' => ['nullable', 'string', 'max:1000'],
            'clip.selection_method' => ['nullable', 'string', 'max:40'],
            'clip.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'clip.start_seconds' => ['nullable', 'numeric', 'min:0'],
            'clip.end_seconds' => ['nullable', 'numeric', 'gt:0'],
            'clip.duration_seconds' => ['nullable', 'numeric', 'gt:0'],
            'clip.size_bytes' => ['nullable', 'integer', 'min:1'],
        ]);

        $clip = $data['clip'];
        $user = $request->user();
        $url = $clip['video_url'];
        $objectKey = $clip['object_key'];

        // Accept only this application's public B2 video URLs.
        $parts = parse_url($url);

        if (
            ($parts['scheme'] ?? null) !== 'https'
            || strtolower($parts['host'] ?? '') !== self::PUBLIC_HOST
            || !empty($parts['query'])
            || !empty($parts['user'])
            || !empty($parts['pass'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Video URL si public Backblaze URL inayokubalika.',
            ], 422);
        }

        $urlPath = rawurldecode(ltrim($parts['path'] ?? '', '/'));

        if (
            $urlPath !== $objectKey
            || !str_ends_with($objectKey, '.mp4')
            || in_array('..', explode('/', $objectKey), true)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'object_key haiendani na video_url.',
            ], 422);
        }

        // A retry returns the previously imported record instead of duplicating it.
        $existing = Content::query()
            ->where('source', 'smart_autocut')
            ->where('source_clip_id', $clip['clip_id'])
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clip ID hii tayari imetumika.',
                ], 409);
            }

            return $this->existingResponse($existing);
        }

        try {
            $content = DB::transaction(function () use (
                $contentService,
                $user,
                $data,
                $clip,
                $url
            ): Content {
                // Empty destinations + autoPublish=false guarantees no publishing.
                $content = $contentService->create(
                    user: $user,
                    caption: $clip['caption'] ?? $clip['title'] ?? null,
                    media: [[
                        'type' => 'video',
                        'public_url' => $url,
                    ]],
                    destinations: [],
                    autoPublish: false,
                );

                $content->update([
                    'source' => 'smart_autocut',
                    'source_clip_id' => $clip['clip_id'],
                ]);

                $media = $content->media()->latest('id')->firstOrFail();

                $media->update([
                    'metadata' => [
                        'source' => 'smart_autocut',
                        'job_id' => $data['job_id'],
                        'clip_id' => $clip['clip_id'],
                        'object_key' => $clip['object_key'],
                        'title' => $clip['title'] ?? null,
                        'reason' => $clip['reason'] ?? null,
                        'selection_method' => $clip['selection_method'] ?? null,
                        'score' => $clip['score'] ?? null,
                        'start_seconds' => $clip['start_seconds'] ?? null,
                        'end_seconds' => $clip['end_seconds'] ?? null,
                        'duration_seconds' => $clip['duration_seconds'] ?? null,
                        'size_bytes' => $clip['size_bytes'] ?? null,
                    ],
                ]);

                return $content->load(['media', 'destinations']);
            });
        } catch (QueryException $exception) {
            // Handle a concurrent retry that inserted this clip first.
            $existing = Content::query()
                ->where('source', 'smart_autocut')
                ->where('source_clip_id', $clip['clip_id'])
                ->first();

            if (!$existing) {
                throw $exception;
            }

            if ((int) $existing->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clip ID hii tayari imetumika.',
                ], 409);
            }

            return $this->existingResponse($existing);
        }

        return response()->json([
            'success' => true,
            'duplicate' => false,
            'message' => 'Clip imehifadhiwa kama draft. Hakuna post iliyochapishwa.',
            'content' => $this->payload($content),
        ], 201);
    }

    private function existingResponse(Content $content): JsonResponse
    {
        $content->load(['media', 'destinations']);

        return response()->json([
            'success' => true,
            'duplicate' => true,
            'message' => 'Clip hii ilikuwa imeingizwa tayari.',
            'content' => $this->payload($content),
        ]);
    }

    private function payload(Content $content): array
    {
        return [
            'id' => $content->id,
            'caption' => $content->caption,
            'status' => 'draft',
            'source' => $content->source,
            'source_clip_id' => $content->source_clip_id,

            'media' => $content->media->map(fn ($media) => [
                'id' => $media->id,
                'type' => $media->type,
                'public_url' => $media->public_url,
                'metadata' => $media->metadata,
            ])->values(),

            'destinations' => $content->destinations->values(),
        ];
    }
}
