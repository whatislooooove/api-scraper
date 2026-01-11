<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PageProgressCoordinator
{
    const string DONE = 'done';

    public function __construct(
        #[Autowire(service: 'cache.scraper')]
        private CacheItemPoolInterface $cache
    ) {}

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function isScraped(int $page): bool
    {
        $item = $this->cache->getItem("page-$page");

        return $item->isHit() && $item->get() === self::DONE;
    }

    public function markDone(int $page): void
    {
        $item = $this->cache->getItem("page-$page");
        $item->set(self::DONE);
        $this->cache->save($item);
    }
}
