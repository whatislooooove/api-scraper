<?php

namespace App\Service\Proxy;

use App\Contracts\ProxyManager;
use Redis;
use Symfony\Component\Cache\Adapter\RedisAdapter;

final class YamlProxyService implements ProxyManager
{
    private Redis $redis;

    private ?string $currentProxy;

    public function __construct(
        private array $proxyList,
        string $redisDsn,
        private int $concurrentLimit = 10
    ) {
        $this->redis = RedisAdapter::createConnection($redisDsn);
        $this->bootstrap();
    }

    public function acquire(?string $curProxy = null): ?string
    {
        foreach ($this->redis->sMembers('proxy:free') as $proxy) {
            $key = $this->activeKey($proxy);

            // АТОМАРНО увеличиваем счётчик
            $current = $this->redis->incr($key);

            if ($current <= $this->concurrentLimit && $curProxy !== $proxy) {
                // если достигли лимита — убираем из free
                if ($current === $this->concurrentLimit) {
                    $this->redis->sRem('proxy:free', $proxy);
                }

                // TTL как защита от утечек
                $this->redis->expire($key, 60);

                $this->currentProxy = $proxy;
                return $proxy;
            }

            // откат если превысили
            $this->redis->decr($key);
        }

        return null;
    }

    public function release(string $proxy): void
    {
        $key = $this->activeKey($proxy);

        $current = $this->redis->decr($key);

        if ($current <= 0) {
            $this->redis->del($key);
            $this->redis->sAdd('proxy:free', $proxy);
            return;
        }

        // если освободился слот — возвращаем в free
        if ($current === $this->concurrentLimit - 1) {
            $this->redis->sAdd('proxy:free', $proxy);
        }
    }

    private function bootstrap(): void
    {
        if ($this->redis->exists('proxy:free')) {
            return;
        }

        foreach ($this->proxyList as $proxy) {
            $this->redis->sAdd('proxy:free', $proxy);
        }
    }

    private function activeKey(string $proxy): string
    {
        return 'proxy:active:' . md5($proxy);
    }
}
