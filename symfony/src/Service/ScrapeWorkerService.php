<?php

namespace App\Service;

use App\Repository\DBAL\MessengerQueueRepository;

class ScrapeWorkerService
{
    const int UNHANDLED_QUEUE_LIMIT = 500;

    public function __construct(
        private MessengerQueueRepository $messengerRepository
    )
    {
    }

    public function waitIfNeed(): void
    {
        while (true) {
            $queueSize = $this->messengerRepository->countPendingMessages();

            if ($queueSize < self::UNHANDLED_QUEUE_LIMIT) {
                return;
            }

            sleep(1);
        }
    }
}
