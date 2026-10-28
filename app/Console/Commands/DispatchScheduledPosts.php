<?php

namespace App\Console\Commands;

use App\Domain\Publishing\Jobs\PublishSocialContent;
use App\Models\ContentDestination;
use Illuminate\Console\Command;

final class DispatchScheduledPosts extends Command
{
    protected $signature = 'posts:dispatch-scheduled';

    protected $description = 'Dispatch scheduled social posts that are due';

    public function handle(): int
    {
        $destinations = ContentDestination::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($destinations as $destination) {
            $destination->update([
                'status' => 'queued',
            ]);

            PublishSocialContent::dispatch(
                $destination->id
            );
        }

        $this->info(
            "Dispatched {$destinations->count()} scheduled destination(s)."
        );

        return self::SUCCESS;
    }
}