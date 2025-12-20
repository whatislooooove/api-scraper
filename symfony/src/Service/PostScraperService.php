<?php

namespace App\Service;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PostScraperService
{
    const string POSTS_LIST_API_URL = 'https://proof.moneymediagroup.co.uk/api/posts';
    const string POST_DETAIL_API_URL = 'https://proof.moneymediagroup.co.uk/api/post/%s';
    const array MAX_PAGE_POINTS = [10, 100, 500, 3000, 10000, 15000, 30000, 50000];
    const int MAX_PAGE_ACCURACY = 0; // 0 - точный номер последней страницы
    const int MAX_LOCAL_TRIES = 15;
    const int DEFAULT_REQUEST_TIMEOUT = 30;

    private int $roundedMaxPage;
    private ?string $proxy;

    public function __construct(
        private HttpClientInterface $client,
        private ProxyManagerService $pm,
        int $consumerIndex
    )
    {
        $this->proxy = $this->pm->getProxyById($consumerIndex);
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function getRoundedMaxPage(): int
    {
        if (!isset($this->roundedMaxPage)) {
            $this->calculateRoundedMaxPage();
        }

        return $this->roundedMaxPage;
    }

    public function getPostsListFromPage(int $page = 1): array
    {
        $retries = 0;
        $delayMs = 250;

        while (true) {
            try {
                $retries++;
                $response = $this->client->request(
                    'GET',
                    self::POSTS_LIST_API_URL,
                    $this->makePostListRequestParams($page)
                );

                return json_decode($response->getContent(), true);
            } catch (TransportException $e) {
                echo 'Page - ' . $page . ', retries - ' . $retries . PHP_EOL;
                if ($retries > self::MAX_LOCAL_TRIES) {
                    throw $e;
                }

                usleep($delayMs * 1000);
                $delayMs = (int)($delayMs * 1.5);
            }
        }
    }

    public function getPostDetail(string $uuid): array
    {
        $retries = 0;
        $delayMs = 250;

        while (true) {
            try {
                $response = $this->client->request(
                    'GET',
                    sprintf(self::POST_DETAIL_API_URL, $uuid),
                    $this->makePostDetailRequestParams()
                );

                return json_decode($response->getContent(), true);
            } catch (TransportException $e) {
                echo 'Id - ' . $uuid . ', retries - ' . $retries . PHP_EOL;
                if ($retries > self::MAX_LOCAL_TRIES) {
                    throw $e;
                }

                usleep($delayMs * 1000);
                $delayMs = (int)($delayMs * 1.5);
            }
        }
    }

    private function recursiveBinarySearch(int $minBound, int $maxBound): int
    {
        if (($maxBound - $minBound) <= self::MAX_PAGE_ACCURACY) {
            return $maxBound;
        }

        $currentMaxBound = $minBound + ($maxBound - $minBound) / 2;
        $response = $this->client->request(
            'GET',
            self::POSTS_LIST_API_URL, [
                'query' => [
                    'page' => $currentMaxBound
                ],
            ]
        );

        if ($response->getStatusCode() === 400) {
            return $this->recursiveBinarySearch($minBound, $currentMaxBound);
        }

        return $currentMaxBound;
    }

    private function calculateRoundedMaxPage(): void
    {
        foreach (self::MAX_PAGE_POINTS as $key => $currentBound) {
            $retries = 0;
            $delayMs = 250;

            while (true) {
                try {
                    $response = $this->client->request(
                        'GET',
                        self::POSTS_LIST_API_URL, [
                            'query' => [
                                'page' => $currentBound
                            ],
                        ]
                    );

                    if ($response->getStatusCode() === 400) {
                        $minBound = self::MAX_PAGE_POINTS[$key - 1] ?? self::MAX_PAGE_POINTS[$key];
                        $this->roundedMaxPage = $this->recursiveBinarySearch($minBound, $currentBound);
                        break(2);
                    }

                    break;

                } catch (TransportException $e) {
                    echo 'Current bound - ' . $currentBound . ', retries - ' . $retries . PHP_EOL;
                    if ($retries > self::MAX_LOCAL_TRIES) {
                        throw $e;
                    }

                    usleep($delayMs * 1000);
                    $delayMs = (int)($delayMs * 1.5);
                }
            }
        }
    }

    private function makePostListRequestParams(int $page): array
    {
        return [
            'query' => [
                'page' => $page
            ],
            'timeout' => self::DEFAULT_REQUEST_TIMEOUT
        ];
    }

    private function makePostDetailRequestParams(): array
    {
        return $this->getProxy() ? [
            'proxy' => $this->getProxy()
        ] : [];
    }
}
