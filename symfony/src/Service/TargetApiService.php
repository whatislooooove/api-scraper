<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;

class TargetApiService
{
    const string POSTS_LIST_API_URL = 'https://proof.moneymediagroup.co.uk/api/posts';
    const array MAX_PAGE_POINTS = [10, 100, 500, 3000, 10000, 15000, 30000, 50000];
    const int MAX_PAGE_ACCURACY = 1000;

    // TODO: указать тип
    private $client;
    private int $roundedMaxPage;

    public function __construct()
    {
        // TODO: обернуть в retryable
        $this->client = HttpClient::create();
    }

    public function getRoundedMaxPage(): int
    {
        if (!isset($this->roundedMaxPage)) {
            $this->calculateRoundedMaxPage();
        }

        return $this->roundedMaxPage;
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
}
