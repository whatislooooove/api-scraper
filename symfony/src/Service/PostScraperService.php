<?php

namespace App\Service;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PostScraperService
{
    const string POSTS_LIST_API_URL = 'https://proof.moneymediagroup.co.uk/api/posts';
    const string POST_DETAIL_API_URL = 'https://proof.moneymediagroup.co.uk/api/post/%s';
    const int MAX_LOCAL_TRIES = 7;
    const int DEFAULT_REQUEST_TIMEOUT = 30;

    public function __construct(private HttpClientInterface $client)
    {
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

    public function getPostDetailUrl(string $uuid): string
    {
        return sprintf(self::POST_DETAIL_API_URL, $uuid);
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
}
