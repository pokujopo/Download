<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'caption',
        'media_path',
        'media_url',
        'media_type',
        'original_name',
        'file_size',
        'mime_type',
        'status',
        'platforms',
        'scheduled_at',
    ];

    protected $casts = [
        'platforms' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class);
    }
}