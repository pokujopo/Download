<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAutomation extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'niche',
        'topics',
        'tone',
        'posts_per_day',
        'posting_times',
        'platforms',
        'auto_publish',
        'timezone',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'topics' => 'array',
        'posting_times' => 'array',
        'platforms' => 'array',
        'auto_publish' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}