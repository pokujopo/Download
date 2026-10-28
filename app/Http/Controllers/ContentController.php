<?php

namespace App\Http\Controllers;

use App\Domain\Content\Services\ContentService;
use App\Http\Requests\StoreContentRequest;
use App\Http\Resources\ContentResource;
use App\Models\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentController extends Controller
{
    public function __construct(
        private ContentService $contentService,
    ) {
    }


public function store(
    StoreContentRequest $request
): JsonResponse {
    $media = [];

    foreach (
        $request->input('media', []) as $index => $item
    ) {
        $media[$index] = [
            'type' => $item['type'],
            'file' => $request->file(
                "media.{$index}.file"
            ),
        ];
    }

    $content = $this->contentService->create(
        user: $request->user(),
        caption: $request->input('caption'),
        media: $media,
        destinations: $request->input('destinations', []),
    );

    return response()->json([
        'success' => true,
        'content' => $content->load([
            'media',
            'destinations',
        ]),
    ], 201);
}

    public function index(Request $request): AnonymousResourceCollection
    {
        $contents = $request->user()
            ->contents()
            ->with([
                'media',
                'destinations',
            ])
            ->latest()
            ->paginate(20);

        return ContentResource::collection($contents);
    }

    public function show(
        Request $request,
        Content $content
    ): ContentResource {
        abort_unless(
            $content->user_id === $request->user()->id,
            404
        );

        $content->load([
            'media',
            'destinations',
        ]);

        return new ContentResource($content);
    }
}