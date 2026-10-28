<?php

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Services\AIContentService;
use App\Domain\AI\Services\ImageSearchService;
use App\Domain\AI\Services\ResearchService;
use App\Domain\Content\Services\ContentService;
use App\Domain\Social\Capabilities\SocialCapabilityResolver;
use App\Models\AiAutomation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RunContentAutomation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [
        60,
        300,
        900,
    ];

    public int $timeout = 300;

    public function __construct(
        public int $automationId,
    ) {
    }

    public function handle(
        AIContentService $aiContentService,
        ResearchService $researchService,
        ImageSearchService $imageSearchService,
        ContentService $contentService,
        SocialCapabilityResolver $capabilityResolver,
    ): void {
        $lock = Cache::lock(
            "ai-automation:{$this->automationId}",
            1800
        );

        if (!$lock->get()) {
            return;
        }

        try {
            $automation = AiAutomation::query()
                ->with('user.socialAccounts')
                ->whereKey($this->automationId)
                ->where('enabled', true)
                ->first();

            if (!$automation) {
                return;
            }

            /*
             * Prevent stale/duplicate queued jobs from running
             * after another job has already completed this run.
             */
            if (
                $automation->next_run_at !== null
                && $automation->next_run_at->isFuture()
            ) {
                return;
            }

            $platforms = $automation->platforms ?? [];

            $accounts = $automation->user->socialAccounts
                ->filter(
                    fn ($account) =>
                        in_array(
                            $account->provider->value,
                            $platforms,
                            true
                        )
                        && $account->status->value === 'active'
                )
                ->values();

            if ($accounts->isEmpty()) {
                return;
            }

            /*
             * Research once for the entire automation run.
             */
            $research = $researchService->research(
                niche: $automation->niche,
                topics: $automation->topics ?? [],
            );

            /*
             * Resolve capabilities for all selected platforms.
             */
            $capabilities = $accounts->mapWithKeys(
                fn ($account) => [
                    $account->id => $capabilityResolver->resolve(
                        $account->provider
                    ),
                ]
            );

            /*
             * Generate one piece of content.
             */
            $generated = $aiContentService->generate(
                niche: $automation->niche,
                topics: $automation->topics ?? [],
                tone: $automation->tone,
                capabilities: $capabilities->first(),
                research: $research,
            );

            /*
             * Find one image when at least one selected
             * platform supports image media.
             */
            $media = [];

            $needsImage = $capabilities->contains(
                fn ($capability) => $capability->image
            );

            if ($needsImage) {
                $image = $imageSearchService->findAndDownload(
                    $generated->topic ?? $automation->niche
                );

                $media[] = [
                    'file' => $image['file'],
                    'type' => $image['type'],
                ];
            }

            /*
             * Build destinations for all selected accounts.
             */
            $destinations = $accounts
                ->map(
                    fn ($account) => [
                        'social_account_id' => $account->id,
                    ]
                )
                ->all();

            /*
             * Create ONE content record with ALL destinations.
             */
            $contentService->create(
                user: $automation->user,
                caption: $generated->caption,
                media: $media,
                destinations: $destinations,
                autoPublish: $automation->auto_publish,
            );

            /*
             * Calculate the next configured posting time.
             */
            $timezone = $automation->timezone
                ?: config('app.timezone');

            $now = now()->setTimezone($timezone);

            $nextRun = $this->nextPostingTime(
                now: $now,
                postingTimes: $automation->posting_times ?? [],
                timezone: $timezone,
            );

            /*
             * Store exact configured time in UTC.
             */
            $automation->update([
                'last_run_at' => $now->utc(),
                'next_run_at' => $nextRun->utc(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function nextPostingTime(
        Carbon $now,
        array $postingTimes,
        string $timezone,
    ): Carbon {
        $times = collect($postingTimes)
            ->filter(
                fn ($time) =>
                    is_string($time)
                    && preg_match(
                        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                        $time
                    )
            )
            ->unique()
            ->sort()
            ->values();

        /*
         * Safety fallback if no posting times are configured.
         */
        if ($times->isEmpty()) {
            return $now
                ->copy()
                ->addDay()
                ->startOfMinute();
        }

        /*
         * Find the next configured time today.
         */
        foreach ($times as $time) {
            $candidate = Carbon::createFromFormat(
                'Y-m-d H:i',
                $now->format('Y-m-d') . ' ' . $time,
                $timezone,
            );

            if ($candidate->isAfter($now)) {
                return $candidate->startOfMinute();
            }
        }

        /*
         * No remaining time today.
         * Use the first configured time tomorrow.
         */
        $firstTime = $times->first();

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $now->copy()
                ->addDay()
                ->format('Y-m-d') . ' ' . $firstTime,
            $timezone,
        )->startOfMinute();
    }
}