<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScheduleSmartAutocutRotation extends Command
{
    protected $signature = 'smart-autocut:schedule-rotation
                            {--dry-run : Preview without saving scheduled posts}';

    protected $description = 'Schedule Smart Autocut clips in shared daily slots across all active social accounts.';

    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    private const DAILY_SLOTS = [
        [12, 0],
        [18, 0],
        [20, 0],
    ];

    private const ACTIVE_STATUSES = [
        'scheduled',
        'queued',
        'processing',
        'publishing',
        'published',
    ];

    public function handle(): int
    {
        foreach ([
            'contents',
            'content_media',
            'social_accounts',
            'content_destinations',
        ] as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $this->error("Required database table missing: {$table}");
                return self::FAILURE;
            }
        }

        $now = CarbonImmutable::now(self::TIMEZONE);
        $today = $now->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $clips = DB::table('contents as c')
            ->where('c.source', 'smart_autocut')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('content_media as cm')
                    ->whereColumn('cm.content_id', 'c.id')
                    ->where('cm.type', 'video')
                    ->whereNotNull('cm.public_url');
            })
            ->orderBy('c.id')
            ->get(['c.id', 'c.user_id', 'c.source_clip_id']);

        if ($clips->isEmpty()) {
            $this->warn('Hakuna clips za Smart Autocut zenye video URL zilizopatikana.');
            return self::SUCCESS;
        }

        $scheduledCount = 0;
        $this->info('PHP local time: ' . $now->format('Y-m-d H:i:s T'));
        $this->info('Mode: ' . ($dryRun ? 'DRY-RUN' : 'LIVE SCHEDULING'));
        $this->info('Policy: one shared slot per clip across all active accounts');
        $this->newLine();

        foreach ($clips->groupBy('user_id') as $userId => $userClips) {
            $accounts = DB::table('social_accounts')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->orderBy('id')
                ->get(['id', 'provider']);

            // TikTok OAuth foundation only: its publisher and per-post consent UI are not enabled yet.
            // Exclude TikTok from automatic rotation so no unsupported destination is created.
            $accounts = $accounts
                ->reject(fn ($account) => $account->provider === 'tiktok')
                ->values();

            // Respect the explicit YouTube auto-publishing safety gate.
            if (!(bool) config('services.youtube.autopublish_enabled', false)) {
                $accounts = $accounts
                    ->reject(fn ($account) => $account->provider === 'youtube')
                    ->values();
            }

            if ($accounts->isEmpty()) {
                $this->warn("User {$userId}: hakuna social account active.");
                continue;
            }

            $accountIds = $accounts->pluck('id')->map(fn ($id) => (int) $id)->all();
            $clipIds = $userClips->pluck('id')->map(fn ($id) => (int) $id)->all();
            $daysToCheck = max(30, (int) ceil($userClips->count() / 3) + 14);
            $rangeStart = $today->utc()->format('Y-m-d H:i:s');
            $rangeEnd = $today->addDays($daysToCheck + 1)->utc()->format('Y-m-d H:i:s');

            // Existing assignment lookup includes all statuses so a failed or
            // published clip is never automatically assigned twice to one account.
            $existingAssignments = DB::table('content_destinations')
                ->whereIn('content_id', $clipIds)
                ->whereIn('social_account_id', $accountIds)
                ->get([
                    'id', 'content_id', 'social_account_id', 'provider',
                    'status', 'scheduled_at', 'published_at', 'external_media_id',
                ]);

            $assignmentMap = [];
            $byClip = [];
            foreach ($existingAssignments as $assignment) {
                $cid = (int) $assignment->content_id;
                $aid = (int) $assignment->social_account_id;
                $assignmentMap[$cid][$aid] = true;
                $byClip[$cid][] = $assignment;
            }

            // Per-account schedule occupancy and daily post counts in the future window.
            $activities = DB::table('content_destinations')
                ->whereIn('social_account_id', $accountIds)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where(function ($query) use ($rangeStart, $rangeEnd) {
                    $query->where(function ($q) use ($rangeStart, $rangeEnd) {
                        $q->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '>=', $rangeStart)
                            ->where('scheduled_at', '<', $rangeEnd);
                    })->orWhere(function ($q) use ($rangeStart, $rangeEnd) {
                        $q->whereNotNull('published_at')
                            ->where('published_at', '>=', $rangeStart)
                            ->where('published_at', '<', $rangeEnd);
                    });
                })
                ->get(['content_id', 'social_account_id', 'status', 'scheduled_at', 'published_at']);

            $dailyCounts = [];
            $occupiedSlots = [];
            foreach ($activities as $activity) {
                $aid = (int) $activity->social_account_id;
                $timestamp = $activity->status === 'published'
                    ? ($activity->published_at ?? $activity->scheduled_at)
                    : ($activity->scheduled_at ?? $activity->published_at);

                if (!$timestamp) {
                    continue;
                }

                $localTime = CarbonImmutable::parse($timestamp, 'UTC')->setTimezone(self::TIMEZONE);
                $dayKey = $localTime->format('Y-m-d');
                $slotKey = $localTime->format('Y-m-d H:i:s');
                $dailyCounts[$aid][$dayKey] = ($dailyCounts[$aid][$dayKey] ?? 0) + 1;
                $occupiedSlots[$aid][$slotKey][] = (int) $activity->content_id;
            }

            $accountCreatedCount = array_fill_keys($accountIds, 0);

            foreach ($userClips as $clip) {
                $clipId = (int) $clip->id;
                $missingAccounts = $accounts->filter(
                    fn ($account) => !isset($assignmentMap[$clipId][(int) $account->id])
                )->values();

                if ($missingAccounts->isEmpty()) {
                    $this->line("SKIP {$clip->source_clip_id}: tayari ina destination kwa accounts zote active.");
                    continue;
                }

                $missingIds = $missingAccounts->pluck('id')->map(fn ($id) => (int) $id)->all();

                // If some platforms already have this clip scheduled at the exact same
                // future time, fill the missing platforms into that same slot when safe.
                $existingForClip = $byClip[$clipId] ?? [];
                $existingScheduled = array_values(array_filter(
                    $existingForClip,
                    fn ($row) => $row->status === 'scheduled' && !empty($row->scheduled_at)
                ));
                $distinctScheduledTimes = array_values(array_unique(array_map(
                    fn ($row) => (string) $row->scheduled_at,
                    $existingScheduled
                )));
                $preferredSlot = null;
                if (
                    count($existingForClip) > 0 &&
                    count($existingScheduled) === count($existingForClip) &&
                    count($distinctScheduledTimes) === 1
                ) {
                    $preferred = CarbonImmutable::parse($distinctScheduledTimes[0], 'UTC')
                        ->setTimezone(self::TIMEZONE);
                    if ($preferred->greaterThan($now)) {
                        $preferredSlot = $preferred;
                    }
                }

                $slotFits = function (CarbonImmutable $slot) use (
                    $accounts,
                    $missingIds,
                    $clipId,
                    &$dailyCounts,
                    &$occupiedSlots
                ): bool {
                    $dayKey = $slot->format('Y-m-d');
                    $slotKey = $slot->format('Y-m-d H:i:s');

                    foreach ($accounts as $account) {
                        $accountId = (int) $account->id;
                        $occupiedBy = $occupiedSlots[$accountId][$slotKey] ?? [];

                        // The same clip already assigned to an account may occupy the
                        // preferred slot; a different clip may not.
                        foreach ($occupiedBy as $occupiedContentId) {
                            if ((int) $occupiedContentId !== $clipId) {
                                return false;
                            }
                        }

                        // Only missing destinations consume a new daily slot on their account.
                        if (
                            in_array($accountId, $missingIds, true) &&
                            (($dailyCounts[$accountId][$dayKey] ?? 0) >= 3)
                        ) {
                            return false;
                        }
                    }
                    return true;
                };

                $chosenSlot = null;
                if ($preferredSlot && $slotFits($preferredSlot)) {
                    $chosenSlot = $preferredSlot;
                } else {
                    for ($offset = 0; $offset <= $daysToCheck; $offset++) {
                        $day = $today->addDays($offset);
                        foreach (self::DAILY_SLOTS as [$hour, $minute]) {
                            $slot = $day->setTime($hour, $minute);
                            if ($slot->lessThanOrEqualTo($now)) {
                                continue;
                            }
                            if ($slotFits($slot)) {
                                $chosenSlot = $slot;
                                break 2;
                            }
                        }
                    }
                }

                if (!$chosenSlot) {
                    $this->error("Hakuna shared slot ya bure kwa {$clip->source_clip_id}.");
                    continue;
                }

                $scheduledAtUtc = $chosenSlot->utc()->format('Y-m-d H:i:s');
                $slotLabel = $chosenSlot->format('Y-m-d H:i');
                $this->line(sprintf(
                    "%s | clip=%s | slot=%s EAT | missing accounts=%s",
                    $dryRun ? 'DRY-RUN' : 'SCHEDULE',
                    $clip->source_clip_id,
                    $slotLabel,
                    implode(',', $missingIds)
                ));

                foreach ($missingAccounts as $account) {
                    $accountId = (int) $account->id;
                    $created = true;

                    if (!$dryRun) {
                        $created = DB::transaction(function () use ($clipId, $account, $scheduledAtUtc) {
                            $exists = DB::table('content_destinations')
                                ->where('content_id', $clipId)
                                ->where('social_account_id', $account->id)
                                ->exists();
                            if ($exists) {
                                return false;
                            }

                            $timestamp = now('UTC')->format('Y-m-d H:i:s');
                            DB::table('content_destinations')->insert([
                                'content_id' => $clipId,
                                'social_account_id' => $account->id,
                                'provider' => $account->provider,
                                'status' => 'scheduled',
                                'scheduled_at' => $scheduledAtUtc,
                                'attempts' => 0,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ]);

                            DB::table('contents')
                                ->where('id', $clipId)
                                ->where('status', 'draft')
                                ->update([
                                    'status' => 'scheduled',
                                    'scheduled_at' => $scheduledAtUtc,
                                    'updated_at' => $timestamp,
                                ]);

                            return true;
                        });
                    }

                    if (!$created) {
                        $this->warn("SKIP {$clip->source_clip_id}: destination tayari imeundwa kwa account {$accountId}.");
                        $assignmentMap[$clipId][$accountId] = true;
                        continue;
                    }

                    // Reserve the identical slot in memory for dry-run and subsequent clips.
                    $dayKey = $chosenSlot->format('Y-m-d');
                    $slotKey = $chosenSlot->format('Y-m-d H:i:s');
                    $dailyCounts[$accountId][$dayKey] = ($dailyCounts[$accountId][$dayKey] ?? 0) + 1;
                    $occupiedSlots[$accountId][$slotKey][] = $clipId;
                    $assignmentMap[$clipId][$accountId] = true;
                    $accountCreatedCount[$accountId]++;
                    $scheduledCount++;
                }
            }

            foreach ($accounts as $account) {
                $aid = (int) $account->id;
                $this->info("Account {$aid} ({$account->provider}): {$accountCreatedCount[$aid]} destination(s) mpya ".($dryRun ? 'zilizokaguliwa.' : 'zilizopangwa.'));
            }
            $this->newLine();
        }

        if ($dryRun) {
            $this->info("DRY-RUN complete: {$scheduledCount} destination(s) zingepangwa. Hakuna mabadiliko yaliyohifadhiwa.");
        } else {
            $this->info("Rotation complete: {$scheduledCount} destination(s) zimepangwa kwa shared slots.");
        }

        return self::SUCCESS;
    }
}
