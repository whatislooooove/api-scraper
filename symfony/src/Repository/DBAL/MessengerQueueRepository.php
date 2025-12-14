<?php

namespace App\Repository\DBAL;

use Doctrine\DBAL\Connection;

class MessengerQueueRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function countPendingMessages(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM messages'
        );
    }
}
