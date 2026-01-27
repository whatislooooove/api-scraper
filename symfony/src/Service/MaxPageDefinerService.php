<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MaxPageDefinerService
{
    const int MAX_PAGE_ACCURACY = 0; // 0 - точный номер последней страницы
    const array MAX_PAGE_POINTS = [10, 100, 500, 3000, 10000, 15000, 30000, 50000];

    public function __construct(private HttpClientInterface $client)
    {
    }

    public function getRoundedMaxPage(): int
    {
        return $this->calculateRoundedMaxPage();
    }

    private function calculateRoundedMaxPage(): int
    {
        foreach (self::MAX_PAGE_POINTS as $key => $currentBound) {
            $response = $this->client->request(
                'GET',
                PostScraperService::POSTS_LIST_API_URL, [
                    'query' => ['page' => $currentBound],
                ]
            );

            if ($response->getStatusCode() === 400) {
                $minBound = self::MAX_PAGE_POINTS[$key - 1] ?? self::MAX_PAGE_POINTS[$key];
                return $this->recursiveBinarySearch($minBound, $currentBound);
            }
        }

        return self::MAX_PAGE_POINTS[array_key_last(self::MAX_PAGE_POINTS)];
    }

    private function recursiveBinarySearch(int $minBound, int $maxBound): int
    {
        if (($maxBound - $minBound) <= self::MAX_PAGE_ACCURACY) {
            return $maxBound;
        }

        $currentMaxBound = $minBound + ($maxBound - $minBound) / 2;
        $response = $this->client->request(
            'GET',
            PostScraperService::POSTS_LIST_API_URL, [
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
}
