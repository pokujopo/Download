<?php

namespace App\Models;

use App\Domain\Content\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    protected $fillable = [
        'user_id',
        'caption',
        'status',
        'scheduled_at',
        'published_at',
        'source',
        'source_clip_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ContentMedia::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(ContentDestination::class);
    }
}