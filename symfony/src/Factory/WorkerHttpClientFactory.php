<?php

namespace App\Factory;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\ProxyManagerService;

final class WorkerHttpClientFactory
{
    private ?HttpClientInterface $client = null;

    public function __construct(
        private HttpClientInterface $baseClient,
        private ProxyManagerService $proxyManager,
        private int $consumerIndex,
    ) {}

    public function getClient(): HttpClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $proxy = $this->proxyManager->getProxyById($this->consumerIndex);

        $this->client = $this->baseClient->withOptions([
            'proxy' => $proxy,
            'headers' => [
                'User-Agent' => 'ApiScraper/1.0',
            ],
            'timeout' => 20,
        ]);

        return $this->client;
    }
}
