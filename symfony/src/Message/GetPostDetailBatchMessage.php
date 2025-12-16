<?php

namespace App\Message;

final class GetPostDetailBatchMessage
{
    // TODO: конструктор надо опустошить, иначе при ретрае будет падать
    public function __construct(
        private readonly array $postExternalIds
    ) {}

    public function getPostExternalIds(): array
    {
        return $this->postExternalIds;
    }
}
