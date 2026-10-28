<?php

namespace App\Domain\Social\Enums;

enum SocialAccountStatus: string
{
    case ACTIVE = 'active';
    case REAUTHORIZATION_REQUIRED = 'reauthorization_required';
    case DISCONNECTED = 'disconnected';
    case DISABLED = 'disabled';
}