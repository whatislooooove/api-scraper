<?php

namespace App\Service\Proxy;

use App\Contracts\ProxyManager;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class YamlProxyService implements ProxyManager
{
    private \Predis\Client $redis;

    public function __construct(
        private array $proxyList,
        string $redisDsn
    )
    {
        $this->redis = RedisAdapter::createConnection($redisDsn);
        $this->bootstrap();
    }

    public function acquire(): ?string
    {
        return $this->redis->rPopLPush('proxy:queue', 'proxy:queue') ?: null;
    }

    private function bootstrap(): void
    {
        if ($this->redis->exists('proxy:queue')) {
            return;
        }

        foreach ($this->proxyList as $proxy) {
            $this->redis->lPush('proxy:queue', $proxy);
        }
    }
}
