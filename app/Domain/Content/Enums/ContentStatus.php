<?php

namespace App\Domain\Content\Enums;

enum ContentStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHING = 'publishing';
    case PARTIALLY_PUBLISHED = 'partially_published';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}