<?php

namespace App\Domain\Publishing\Enums;

enum DestinationStatus: string
{
    case PENDING = 'pending';
    case SCHEDULED = 'scheduled';
    case QUEUED = 'queued';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}