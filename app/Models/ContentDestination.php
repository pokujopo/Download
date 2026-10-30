<?php

namespace App\Models;

use App\Domain\Publishing\Enums\DestinationStatus;
use App\Domain\Social\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDestination extends Model
{
    protected $fillable = [
        'content_id',
        'social_account_id',
        'provider',
        'status',
        'scheduled_at',
        'provider_reference',
        'external_media_id',
        'error_code',
        'error_message',
        'provider_response',
        'attempts',
        'last_attempt_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'status' => DestinationStatus::class,
            'scheduled_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'published_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}