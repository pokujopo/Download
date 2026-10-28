<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiAutomationRequest;
use App\Http\Requests\UpdateAiAutomationRequest;
use App\Models\AiAutomation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAutomationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $automation = $request->user()
            ->aiAutomations()
            ->latest()
            ->first();

        if (!$automation) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $automation,
        ]);
    }

   
        public function store(
            StoreAiAutomationRequest $request
        ): JsonResponse {
            $user = $request->user();

            $platforms = $request->validated('platforms');

            $connectedPlatforms = $user->socialAccounts()
                ->where('status', 'active')
                ->whereIn('provider', $platforms)
                ->pluck('provider')
                ->unique()
                ->values();

            $missingPlatforms = collect($platforms)
                ->diff($connectedPlatforms)
                ->values();

            if ($missingPlatforms->isNotEmpty()) {
                return response()->json([
                    'message' => 'Connect your social account before enabling AI automation.',
                    'code' => 'SOCIAL_ACCOUNT_REQUIRED',
                    'missing_platforms' => $missingPlatforms,
                ], 422);
            }

            $validated = $request->validated();

            $automation = $user->aiAutomations()->create([
                ...$validated,
                'posts_per_day' => count(
                    $validated['posting_times']
                ),
                'last_run_at' => null,
                'next_run_at' => null,
            ]);

            return response()->json([
                'message' => 'AI automation created successfully.',
                'data' => $automation,
            ], 201);
        }

    public function update(
        UpdateAiAutomationRequest $request,
        AiAutomation $automation,
    ): JsonResponse {
        abort_unless(
            $automation->user_id === $request->user()->id,
            404
        );

        $data = $request->validated();

        if (array_key_exists('posting_times', $data)) {
            $data['posts_per_day'] = count(
                $data['posting_times']
            );

            /*
             * Recalculate the schedule from the new times.
             * The scheduler will pick it up from the next run.
             */
            $data['next_run_at'] = null;
        }

        $automation->update($data);

        return response()->json([
            'message' => 'AI automation updated successfully.',
            'data' => $automation->fresh(),
        ]);
    }

    public function destroy(
        Request $request,
        AiAutomation $automation,
    ): JsonResponse {
        abort_unless(
            $automation->user_id === $request->user()->id,
            404
        );

        $automation->delete();

        return response()->json([
            'message' => 'AI automation deleted successfully.',
        ]);
    }
}