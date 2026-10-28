<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Content\Services\ContentStatusService;
use App\Domain\Publishing\Services\PublishingService;
use App\Models\ContentDestination;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class PublishSocialContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(
        public int $destinationId,
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                "publish-destination:{$this->destinationId}"
            ))
                ->expireAfter(300)
                ->releaseAfter(10),
        ];
    }

    public function handle(
        PublishingService $publishingService,
    ): void {
        $destination = ContentDestination::query()
            ->find($this->destinationId);

        if (!$destination) {
            return;
        }

        if ($destination->status->value === 'published') {
            return;
        }

        if ($destination->status->value === 'cancelled') {
            return;
        }

        $destination->update([
            'status' => 'queued',
        ]);

        $result = $publishingService->publish($destination);

        if (!$result->success && $result->retryable) {
            throw new RuntimeException(
                $result->errorMessage
                ?? $result->errorCode
                ?? 'Retryable publishing failure.'
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $destination = ContentDestination::query()
            ->find($this->destinationId);

        if (!$destination) {
            return;
        }

        if ($destination->status->value === 'published') {
            return;
        }

        $destination->update([
            'status' => 'failed',
            'error_code' => $destination->error_code
                ?? 'JOB_FAILED',
            'error_message' => $destination->error_message
                ?? $exception->getMessage(),
        ]);

        app(ContentStatusService::class)->sync(
            $destination->content()->firstOrFail()
        );
    }
}