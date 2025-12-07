<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PostScraperService
{
    const string POSTS_LIST_API_URL = 'https://proof.moneymediagroup.co.uk/api/posts';
    const string POST_DETAIL_API_URL = 'https://proof.moneymediagroup.co.uk/api/post/%s';
    const array MAX_PAGE_POINTS = [10, 100, 500, 3000, 10000, 15000, 30000, 50000];
    const int MAX_PAGE_ACCURACY = 0; // 0 - точный номер последней страницы

    private int $roundedMaxPage;
    private ?string $proxy = null;

    public function __construct(private HttpClientInterface $client)
    {
    }

    public function setProxy(?string $proxy): void
    {
        $this->proxy = $proxy;
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

    public function getPostsList(int $page = 1): array
    {
        $response = $this->client->request(
            'GET',
            self::POSTS_LIST_API_URL,
            $this->makePostListRequestParams($page)
        );

        return json_decode($response->getContent(), true);
    }

    public function getPostDetail(string $uuid): array
    {
        $response = $this->client->request(
            'GET',
            sprintf(self::POST_DETAIL_API_URL, $uuid),
            $this->makePostDetailRequestParams()
        );

        return json_decode($response->getContent(), true);
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
                break;
            }
        }
    }

    private function makePostListRequestParams(int $page): array
    {
        $params = [
            'query' => [
                'page' => $page
            ],
        ];

        if ($this->proxy) {
            $params['proxy'] = $this->proxy;
        }

        return $params;
    }

    private function makePostDetailRequestParams(): array
    {
        return $this->getProxy() ? [
            'proxy' => $this->getProxy()
        ] : [];
    }
}
