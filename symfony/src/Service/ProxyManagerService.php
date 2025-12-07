<?php

namespace App\Service;

class ProxyManagerService
{
    public function __construct(private array $proxyList)
    {
    }

    public function getProxyById(int $id): ?string
    {
        return $this->proxyList[$id] ?? null;
    }
}
