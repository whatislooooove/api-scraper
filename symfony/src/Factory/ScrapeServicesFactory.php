<?php

namespace App\Factory;

use App\Service\ScrapeMasterService;
use Symfony\Component\Console\Output\OutputInterface;

class ScrapeServicesFactory
{

    // TODO: вынести в DTO
    public function makeMasterService(int $threads, int $itemsToHandleCount, OutputInterface $output, bool $isRestart): ScrapeMasterService
    {
        return new ScrapeMasterService($threads, $itemsToHandleCount, $output, $isRestart);
    }
}
