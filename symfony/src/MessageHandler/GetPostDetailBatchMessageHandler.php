<?php

namespace App\MessageHandler;

use App\Factory\PostFactory;
use App\Message\GetPostDetailBatchMessage;
use App\Service\PostScraperService;
use App\Service\PostService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class GetPostDetailBatchMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private array $sentRequests = [];

    public function __construct(
        private PostScraperService $postScraper,
        private HttpClientInterface $httpClient,
        private PostFactory $postFactory,
        private PostService $postService,

    )
    {
    }

    public function __invoke(GetPostDetailBatchMessage $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        $this->sendRequests($jobs);
        $this->handleResponses();
    }
     private function getBatchSize(): int
     {
         return 30;
     }

     private function sendRequests(array $jobs): void
     {
         foreach ($jobs as [$message, $ack]) {
             try {
                 $this->sentRequests[$message->getUuid()] = $this->httpClient->request(
                     'GET',
                     $this->postScraper->getPostDetailUrl($message->getUuid())
                 );
                 $ack->ack();
             } catch (\Throwable $e) {
                 // TODO: добавить логирование и вынести туда
                 dump($e->getMessage());
                 $ack->nack($e);
             }
         }
     }

    private function handleResponses(): void
    {
        foreach ($this->httpClient->stream($this->sentRequests) as $response => $chunk) {
            if ($chunk->isLast()) {
                try {
                    $data = $response->toArray();

                    $createPostInputDTO = $this->postFactory->makeCreatePostInputDTO($data);
                    $this->postService->createIfNotExists($createPostInputDTO);
                } catch (\Throwable $e) {
                    // TODO: логирование
                }
            }
        }
    }
}
