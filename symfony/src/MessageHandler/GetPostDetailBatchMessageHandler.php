<?php

namespace App\MessageHandler;

use App\Factory\WorkerHttpClientFactory;
use App\Message\GetPostDetailBatchMessage;
use App\Repository\DBAL\PostWriteRepository;
use App\Service\PostScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPostDetailBatchMessageHandler
{
    private $client;

    public function __construct(
        WorkerHttpClientFactory $clientFactory,
        private PostWriteRepository $postRepository,
        private LoggerInterface $logger,
    ) {
        // 1 HttpClient per worker
        $this->client = $clientFactory->getClient();
    }

    public function __invoke(GetPostDetailBatchMessage $message): void
    {
        $responses = [];
        $postsData = [];

        // 1️⃣ создаём async-запросы
        foreach ($message->getPostExternalIds() as $postId) {
            $responses[$postId] = $this->client->request(
                'GET',
                sprintf(PostScraperService::POST_DETAIL_API_URL, $postId),
            );
        }

        // 2️⃣ stream() — реальный async
        foreach ($this->client->stream($responses) as $response => $chunk) {
            if (!$chunk->isLast()) {
                continue;
            }

            $postId = array_search($response, $responses, true);

            try {
                if ($response->getStatusCode() !== 200) {
                    $this->logger->warning('Bad status code', [
                        'postId' => $postId,
                        'status' => $response->getStatusCode(),
                    ]);
                    continue;
                }

                $data = $response->toArray();

                $postsData[$postId] = [
                    'external_id' => $data['id'],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'created_at' => $data['createdAt'],
                    'body' => $data['body'],
                ];
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process post' . $e->getMessage(), [
                    'postId' => $postId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($postsData !== []) {
            dump('TO DATABASE');
            $this->postRepository->upsertBatch($postsData);
        }
    }
}
