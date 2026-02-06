<?php

namespace App\Service\Proxy;

use App\Contracts\ProxyManager;
use Redis;
use Symfony\Component\Cache\Adapter\RedisAdapter;

final class YamlProxyService implements ProxyManager
{
    private Redis $redis;

    public function __construct(
        private array $proxyList,
        string $redisDsn,
        private int $concurrentLimit = 9
    )
    {
        $this->redis = RedisAdapter::createConnection($redisDsn);
        $this->bootstrap();
    }

    public function acquire(): ?string
    {
        $lua = <<<LUA
            -- KEYS[1] = proxy:free
            -- KEYS[2] = proxy:busy
            -- ARGV[1] = concurrentLimit
            -- ARGV[2] = timestamp
            -- ARGV[3] = ttlSeconds

            -- 1. Сначала проверяем "зависшие" прокси
            local busyProxies = redis.call('ZRANGEBYSCORE', KEYS[2], '-inf', ARGV[2])
            if #busyProxies > 0 then
                for i, proxy in ipairs(busyProxies) do
                    redis.call('ZREM', KEYS[2], proxy)
                    redis.call('SADD', KEYS[1], proxy)
                end
            end

            -- 2. Пытаемся взять свободный прокси
            local proxy = redis.call('SRANDMEMBER', KEYS[1])
            if not proxy then
                return nil
            end

            -- 3. Проверяем активные соединения
            local activeKey = 'proxy:active:' .. redis.sha1hex(proxy)
            local current = redis.call('GET', activeKey)
            current = tonumber(current) or 0

            -- 4. Если превышен лимит - возвращаем прокси в пул и пробуем другой
            if current >= tonumber(ARGV[1]) then
                redis.call('SADD', KEYS[1], proxy) -- возвращаем в пул
                return nil
            end

            -- 5. Бронируем прокси
            redis.call('INCR', activeKey)
            redis.call('EXPIRE', activeKey, ARGV[3])

            -- 6. Убираем из свободных и добавляем в занятые с таймстемпом
            redis.call('SREM', KEYS[1], proxy)
            redis.call('ZADD', KEYS[2], ARGV[2] + ARGV[3], proxy) -- timestamp + TTL

            return proxy
        LUA;

        $ttl = 30; // TTL для прокси (меньше чем в release!)
        $now = time();

        $proxy = $this->redis->eval(
            $lua,
            [
                'proxy:free',        // KEYS[1]
                'proxy:busy',        // KEYS[2]
                $this->concurrentLimit, // ARGV[1]
                $now,                // ARGV[2]
                $ttl                 // ARGV[3]
            ],
            2 // количество ключей
        );

        return $proxy ?: null;
    }

    public function release(string $proxy): void
    {
        $lua = <<<LUA
            -- KEYS[1] = proxy:busy
            -- KEYS[2] = proxy:free
            -- ARGV[1] = proxy
            -- ARGV[2] = activeKey

            -- 1. Уменьшаем счетчик активных соединений
            local current = redis.call('DECR', ARGV[2])

            -- 2. Если счетчик <= 0, удаляем ключ
            if current <= 0 then
                redis.call('DEL', ARGV[2])
            else
                -- 3. Иначе продлеваем TTL
                redis.call('EXPIRE', ARGV[2], 60)
            end

            -- 4. Удаляем из занятых
            redis.call('ZREM', KEYS[1], ARGV[1])

            -- 5. Добавляем в свободные
            redis.call('SADD', KEYS[2], ARGV[1])

            return 1
        LUA;

        $activeKey = $this->activeKey($proxy);

        $this->redis->eval(
            $lua,
            [
                'proxy:busy',        // KEYS[1]
                'proxy:free',        // KEYS[2]
                $proxy,              // ARGV[1]
                $activeKey           // ARGV[2]
            ],
            2
        );
    }

    public function markAsBad(string $proxy, int $timeout = 300): void
    {
        $key = 'proxy:bad:' . md5($proxy);
        $this->redis->setex($key, $timeout, 1);
        $this->release($proxy);
    }

    private function bootstrap(): void
    {
        if (!$this->redis->exists('proxy:free')) {
            foreach ($this->proxyList as $proxy) {
                $this->redis->sAdd('proxy:free', $proxy);
            }
        }

        if (!$this->redis->exists('proxy:busy')) {
        }
    }

    private function activeKey(string $proxy): string
    {
        return 'proxy:active:' . md5($proxy);
    }
}
