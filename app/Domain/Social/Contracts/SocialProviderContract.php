<?php

namespace App\Domain\Social\Contracts;

use App\Domain\Publishing\DTOs\PublishResult;
use App\Domain\Social\DTOs\PublishContext;

interface SocialProviderContract
{
    public function validate(PublishContext $context): void;

    public function publish(PublishContext $context): PublishResult;

    public function getStatus(PublishContext $context): PublishResult;

    public function disconnect(string $providerAccountId): void;
}