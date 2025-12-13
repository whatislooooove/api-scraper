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
     )
     {
     }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
