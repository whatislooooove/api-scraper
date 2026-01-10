<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class GetPostDetailBatchMessage
{
    public function __construct(
        private readonly string $uuid,
    )
    {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
