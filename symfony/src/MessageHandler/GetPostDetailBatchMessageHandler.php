<?php

namespace App\MessageHandler;

use App\Factory\WorkerHttpClientFactory;
use App\Message\GetPostDetailBatchMessage;
use App\Repository\DBAL\PostWriteRepository;
use App\Service\PostScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class GetPostDetailBatchMessageHandler
{
    const int MAX_RETRIES = 15;
    private HttpClientInterface $client;

    public function __construct(
        WorkerHttpClientFactory $clientFactory,
        private PostWriteRepository $postRepository,
        private LoggerInterface $logger,
    ) {
        $this->client = $clientFactory->getClient();
    }

    public function __invoke(GetPostDetailBatchMessage $message): void
    {
        $postIds = $message->getPostExternalIds();

        $responses = $this->sendRequests($postIds);
        $postsData = $this->processResponses($responses);

        $this->savePostsData($postsData);
    }

    private function sendRequests(array $postIds): array
    {
        $responses = [];

        foreach ($postIds as $postId) {
            $responses[$postId] = $this->client->request(
                'GET',
                sprintf(PostScraperService::POST_DETAIL_API_URL, $postId),
                [
                    'timeout' => 15,
                    'max_duration' => 30,
                ]
            );
        }

        return $responses;
    }

    private function processResponses(array $responses): array
    {
        $postsData = [];
        $failedRequests = [];

        foreach ($this->client->stream($responses) as $response => $chunk) {
            $postId = $this->getPostIdFromResponse($response, $responses);

            try {
                if ($chunk->isLast()) {
                    $postData = $this->processResponse($response, $postId);
                    if ($postData !== null) {
                        $postsData[$postId] = $postData;
                    }
                }
            } catch (TransportException $e) {
                $this->logger->error('Transport error while processing post. ' . $e->getMessage(), [
                    'postId' => $postId,
                    'error' => $e->getMessage(),
                ]);
                $failedRequests[] = $postId;
            } catch (\Throwable $e) {
                $this->logger->error('Unexpected error while processing post', [
                    'postId' => $postId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($failedRequests)) {
            $this->retryFailedRequests($failedRequests, $postsData);
        }

        return $postsData;
    }

    private function processResponse($response, string $postId): ?array
    {
        try {
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Bad status code', [
                    'postId' => $postId,
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $data = $response->toArray();

            return [
                'external_id' => $data['id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'created_at' => $data['createdAt'],
                'body' => $data['body'],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process post response', [
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function retryFailedRequests(array $failedPostIds, array &$postsData): void
    {
        $this->logger->info('Retrying failed requests', [
            'failedCount' => count($failedPostIds),
            'postIds' => $failedPostIds,
        ]);

        $retryCount = 0;
        $delayMs = 500;

        while (!empty($failedPostIds) && $retryCount < self::MAX_RETRIES) {
            $retryCount++;
            $this->logger->warning("Retry #$retryCount." . ' Failed in chunk: ' . count($failedPostIds) . ". Delay - $delayMs ms ");
            $retryResponses = [];

            foreach ($failedPostIds as $postId) {
                $retryResponses[$postId] = $this->client->request(
                    'GET',
                    sprintf(PostScraperService::POST_DETAIL_API_URL, $postId),
                    [
                        'timeout' => 20,
                    ]
                );
            }

            $newFailedRequests = [];

            foreach ($this->client->stream($retryResponses) as $response => $chunk) {
                $postId = $this->getPostIdFromResponse($response, $retryResponses);

                try {
                    if ($chunk->isLast()) {
                        $postData = $this->processResponse($response, $postId);
                        if ($postData !== null) {
                            $postsData[$postId] = $postData;
                            $key = array_search($postId, $failedPostIds, true);
                            if ($key !== false) {
                                unset($failedPostIds[$key]);
                            }
                        } else {
                            $newFailedRequests[] = $postId;
                        }
                    }
                } catch (TransportException $e) {
                    $newFailedRequests[] = $postId;
                    $this->logger->warning('Retry failed for post. ' . $e->getMessage(), [
                        'postId' => $postId,
                        'retryCount' => $retryCount,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $failedPostIds = $newFailedRequests;

            if (!empty($failedPostIds)) {
                $this->logger->warning('Some requests still failed after retry', [
                    'retryCount' => $retryCount,
                    'remainingFailed' => count($failedPostIds),
                ]);

                if ($retryCount < self::MAX_RETRIES) {
                    usleep($delayMs * 1000);
                    $delayMs = (int)($delayMs * 1.5);
                }
            }
        }

        if (!empty($failedPostIds)) {
            $this->logger->error('Requests failed after all retry attempts', [
                'postIds' => $failedPostIds,
                'totalRetries' => self::MAX_RETRIES,
            ]);
        }
    }

    private function getPostIdFromResponse($response, array $responses): string
    {
        foreach ($responses as $postId => $resp) {
            if ($resp === $response) {
                return $postId;
            }
        }

        throw new \RuntimeException('Could not find post ID for response');
    }

    private function savePostsData(array $postsData): void
    {
        if (empty($postsData)) {
            $this->logger->warning('No post data to save');
            return;
        }

        try {
            $this->postRepository->upsertBatch($postsData);
            $this->logger->info('Successfully saved posts', [
                'count' => count($postsData),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save posts to database', [
                'error' => $e->getMessage(),
                'postsCount' => count($postsData),
            ]);
        }
    }
}
