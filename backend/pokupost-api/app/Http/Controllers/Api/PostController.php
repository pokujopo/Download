<?php

namespace App\Http\Controllers\Api;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use App\Services\UploadService;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use Illuminate\Support\Facades\Auth;


class PostController extends Controller
{
    public function __construct(private UploadService $uploadService) {}

    public function index(): JsonResponse
    {
        $posts = Post::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $platforms = $request->input('platforms', []);

        if (is_string($platforms)) {
            $decoded = json_decode($platforms, true);
            $platforms = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $upload = $this->uploadService->uploadMedia($request->file('media'));

        $post = Post::create([
            'user_id' => Auth::id(),
            'caption' => $request->input('caption'),
            'media_path' => $upload['media_path'],
            'media_url' => $upload['media_url'],
            'media_type' => $upload['media_type'],
            'original_name' => $upload['original_name'],
            'file_size' => $upload['file_size'],
            'mime_type' => $upload['mime_type'],
            'status' => !empty($platforms) ? 'queued' : 'draft',
            'platforms' => $platforms,
            'scheduled_at' => $request->input('scheduled_at'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully.',
            'data' => new PostResource($post),
        ], 201);
    }

    public function show(Post $post): JsonResponse
    {
        abort_if($post->user_id !== Auth::id(), 403);

        return response()->json([
            'success' => true,
            'data' => new PostResource($post),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        abort_if($post->user_id !== Auth::id(), 403);

        $post->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully.',
            'data' => new PostResource($post->fresh()),
        ]);
    }

    public function destroy(Post $post): JsonResponse
    {
        abort_if($post->user_id !== Auth::id(), 403);

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully.',
        ]);
    }
}