<?php

namespace App\MessageHandler;

use App\Contracts\ProxyManager;
use App\Factory\PostFactory;
use App\Message\GetPostDetailBatchMessage;
use App\Service\PostScraperService;
use App\Service\PostService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class GetPostDetailBatchMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private array $sentRequests = [];

    public function __construct(
        private PostScraperService  $postScraper,
        private HttpClientInterface $httpClient,
        private PostFactory         $postFactory,
        private PostService         $postService,

        #[Autowire(service: 'limiter.api_proxy')]
        private RateLimiterFactory  $rateLimiterFactory,
        private ProxyManager        $pm,
        private LoggerInterface     $logger
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
        return 20;
    }

    private function sendRequests(array $jobs): void
    {
        foreach ($jobs as [$message, $ack]) {
            try {
                $proxy = $this->getFreeProxy();

                $this->sentRequests[$message->getUuid()] = [
                    'response' => $this->httpClient->request(
                        'GET',
                        $this->postScraper->getPostDetailUrl($message->getUuid()), [
                            'proxy' => $proxy,
                            'timeout' => 10,
                        ]
                    ),
                    'ack' => $ack,
                    'proxy' => $proxy
                ];
            } catch (\Throwable $e) {
                $this->logger->error("On request send ({$message->getUuid()})" . $e->getMessage());
                if (isset($proxy)) {
                    $this->pm->release($proxy);
                }
                $ack->nack($e);
            }
        }
    }

    private function handleResponses(): void
    {
        $responses = array_column($this->sentRequests, 'response');
        foreach ($this->httpClient->stream($responses, 30) as $response => $chunk) {
            try {
                $uuid = $this->findUuidByResponse($response);
                if (!$uuid) {
                    continue;
                }

                if ($chunk->isLast()) {
                    $data = $response->toArray();
                    $createPostInputDTO = $this->postFactory->makeCreatePostInputDTO($data);

                    $this->postService->createIfNotExists($createPostInputDTO);
                    $this->sentRequests[$uuid]['ack']->ack();

                    $this->pm->release($this->sentRequests[$uuid]['proxy']);
                    unset($this->sentRequests[$uuid]);
                }
            } catch (\Throwable $e) {
                $this->logger->error($uuid . ' (proxy ' . $this->sentRequests[$uuid]['proxy'] .  ') error: ' . $e->getMessage());
                $this->sentRequests[$uuid]['ack']->nack($e);

                $this->pm->release($this->sentRequests[$uuid]['proxy']);
                unset($this->sentRequests[$uuid]);
            }
        }
    }

    private function findUuidByResponse($response): ?string
    {
        foreach ($this->sentRequests as $uuid => $requestData) {
            if ($requestData['response'] === $response) {
                return $uuid;
            }
        }

        return null;
    }

    private function getFreeProxy(): string
    {
        while (true) {
            $proxy = $this->pm->acquire();

            if (!$proxy) {
                throw new \RuntimeException('No proxy available');
            }

            $limiter = $this->rateLimiterFactory->create($proxy);

            if ($limiter->consume()->isAccepted()) {
                $this->logger->info('Proxy acquired', [
                    'proxy' => $proxy
                ]);
                return $proxy;
            }
            $this->pm->release($proxy);

            usleep(250_000);
        }
    }

}
