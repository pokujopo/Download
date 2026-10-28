<?php

namespace App\Console\Commands;

use App\Domain\AI\Jobs\RunContentAutomation;
use App\Models\AiAutomation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchAiAutomations extends Command
{
    protected $signature = 'ai:automations';

    protected $description = 'Dispatch due AI content automations';

    public function handle(): int
    {
        AiAutomation::query()
            ->where('enabled', true)
            ->each(function (AiAutomation $automation) {
                if ($automation->next_run_at === null) {
                    $automation->update([
                        'next_run_at' => $this->nextPostingTime(
                            now()->setTimezone($automation->timezone),
                            $automation->posting_times ?? [],
                            $automation->timezone,
                        )->utc(),
                    ]);

                    return;
                }

                if ($automation->next_run_at->isFuture()) {
                    return;
                }

                RunContentAutomation::dispatch(
                    $automation->id
                );
            });

        return self::SUCCESS;
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

        if ($times->isEmpty()) {
            return $now
                ->copy()
                ->addDay()
                ->startOfMinute();
        }

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

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $now->copy()
                ->addDay()
                ->format('Y-m-d') . ' ' . $times->first(),
            $timezone,
        )->startOfMinute();
    }
}