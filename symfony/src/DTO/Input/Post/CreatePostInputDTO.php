<?php

namespace App\DTO\Input\Post;

class CreatePostInputDTO
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $body = null;

    public ?\DateTimeImmutable $createdAt = null;

    public ?string $externalId = null;
}
