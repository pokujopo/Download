<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event',
        'account_id',
        'external_media_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}