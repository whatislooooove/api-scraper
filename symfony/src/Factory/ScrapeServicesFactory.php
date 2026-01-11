<?php

namespace App\Factory;

use App\Service\ProxyManagerService;
use App\Service\ScrapeMasterService;
use Symfony\Component\Console\Output\OutputInterface;

class ScrapeServicesFactory
{
    public function __construct(private ProxyManagerService $proxy)
    {
    }

    // TODO: вынести в DTO
    public function makeMasterService(int $threads, int $itemsToHandleCount, OutputInterface $output, bool $isRestart): ScrapeMasterService
    {
        return new ScrapeMasterService($threads, $itemsToHandleCount, $output, $isRestart);
    }
}
