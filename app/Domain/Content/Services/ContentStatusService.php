<?php

namespace App\Domain\Content\Services;

use App\Models\Content;

final class ContentStatusService
{
    public function sync(Content $content): void
    {
        $content->loadMissing('destinations');

        $destinations = $content->destinations;

        if ($destinations->isEmpty()) {
            return;
        }

        $total = $destinations->count();

        $published = $destinations
            ->where('status', 'published')
            ->count();

        $failed = $destinations
            ->where('status', 'failed')
            ->count();

        $processing = $destinations
            ->whereIn('status', [
                'queued',
                'publishing',
            ])
            ->count();

        $scheduled = $destinations
            ->where('status', 'scheduled')
            ->count();

        $status = match (true) {
            $published === $total => 'published',

            $processing > 0 => 'publishing',

            $published > 0 => 'partially_published',

            $failed === $total => 'failed',

            $scheduled === $total => 'scheduled',

            default => 'draft',
        };

        $updates = [
            'status' => $status,
        ];

        if (
            $status === 'published'
            && $content->published_at === null
        ) {
            $updates['published_at'] = now();
        }

        $content->update($updates);
    }
}