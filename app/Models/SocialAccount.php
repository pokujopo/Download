<?php

namespace App\Models;

use App\Domain\Social\Enums\SocialAccountStatus;
use App\Domain\Social\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_account_id',
        'username',
        'display_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'status' => SocialAccountStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(ContentDestination::class);
    }
}