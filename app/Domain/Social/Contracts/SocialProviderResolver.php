<?php

namespace App\Domain\Social\Contracts;

use App\Domain\Social\Enums\SocialProvider;

interface SocialProviderResolver
{
    public function resolve(SocialProvider $provider): SocialProviderContract;
}