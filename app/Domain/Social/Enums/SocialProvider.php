<?php

namespace App\Domain\Social\Enums;

enum SocialProvider: string
{
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';
    case LINKEDIN = 'linkedin';
    case TIKTOK = 'tiktok';
}