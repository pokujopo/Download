<?php

namespace App\Domain\Content\Services;

use App\Domain\Media\Services\MediaService;
use App\Domain\Publishing\Jobs\PublishSocialContent;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ContentService
{
    public function __construct(
        private MediaService $mediaService,
    ) {
    }

    public function create(
        User $user,
        ?string $caption,
        array $media,
        array $destinations,
        bool $autoPublish = true,
    ): Content {
        return DB::transaction(
            function () use (
                $user,
                $caption,
                $media,
                $destinations,
                $autoPublish
            ): Content {
                $content = Content::create([
                    'user_id' => $user->id,
                    'caption' => $caption,
                    'status' => 'draft',
                ]);

                foreach ($media as $item) {
                    $this->mediaService->create(
                        content: $content,
                        media: [$item],
                    );
                }

                foreach ($destinations as $destination) {
                    $account = $user->socialAccounts()
                        ->findOrFail(
                            $destination['social_account_id']
                        );

                    $scheduledAt = $destination['scheduled_at'] ?? null;

                    $contentDestination = $content->destinations()->create([
                        'social_account_id' => $account->id,
                        'provider' => $account->provider,
                        'status' => $scheduledAt
                            ? 'scheduled'
                            : 'pending',
                        'scheduled_at' => $scheduledAt,
                    ]);

                    if (!$scheduledAt && $autoPublish) {
                        PublishSocialContent::dispatch(
                            $contentDestination->id
                        )->afterCommit();
                    }
                }

                return $content;
            }
        );
    }
}