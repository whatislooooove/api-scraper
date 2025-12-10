<?php

namespace App\Message;

final class GetPostDetailMessage
{
    /*
     * Add whatever properties and methods you need
     * to hold the data for this message class.
     */

     public function __construct(
         private readonly string $uuid,
         private readonly ?string $proxy,
     )
     {
     }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }
}
