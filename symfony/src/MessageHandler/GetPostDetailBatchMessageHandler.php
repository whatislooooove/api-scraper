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
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
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
        return 5;
    }

    private function sendRequests(array $jobs): void
    {
        foreach ($jobs as [$message, $ack]) {
            try {
                $proxy = $this->getFreeProxy();
                if (!$proxy) {
                    $this->logger->warning('Proxy timeout for message, skipping', ['uuid' => $message->getUuid()]);

                    continue;
                }

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
                $this->logger->error("On request send ({$message->getUuid()}), " . ' ' . $e->getMessage());
                if (isset($proxy)) {
                    $this->pm->release($proxy);
                }
                $this->markMessageAsFailed($message, $ack, $e);
            }
        }
    }

    private function handleResponses(): void
    {
        foreach ($this->sentRequests as $uuid => $data) {
            if (isset($data['failed'])) {
                $this->fail($uuid, $data['error']);
            }
        }

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
                    $this->success($uuid);
                }
            } catch (\InvalidArgumentException $e) {
                $this->logger->error('VALIDATION ERROR: ' . $uuid . ' (proxy ' . $this->sentRequests[$uuid]['proxy'] .  ') error: ' . $e->getMessage());
                $this->fail($uuid, $e, true);

            } catch (\Throwable $e) {
                $this->logger->error($uuid . ' (proxy ' . $this->sentRequests[$uuid]['proxy'] .  ') error: ' . $e->getMessage());
                $this->fail($uuid, $e);
            }
        }

        $this->postService->flushAndClear();
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

    private function getFreeProxy(): ?string
    {
        $start = microtime(true);
        $timeout = 10.0;

        $attempts = 0;

        while (true) {
            $attempts++;

            $proxy = $this->pm->acquire();

            if ($proxy) {
                $limiter = $this->rateLimiterFactory->create($proxy);

                if ($limiter->consume()->isAccepted()) {
                    $this->logger->debug('Proxy acquired', [
                        'proxy' => $proxy,
                        'attempts' => $attempts,
                        'time' => round(microtime(true) - $start, 2)
                    ]);
                    return $proxy;
                }

                $this->pm->release($proxy);
                $this->pm->markAsBad($proxy, 60);
            }

            $elapsed = microtime(true) - $start;
            if ($elapsed > $timeout) {
                $this->logger->warning('Proxy timeout reached', [
                    'attempts' => $attempts,
                    'timeout' => $timeout
                ]);
                return null;
            }

            $sleepMs = min(1000, 50 * pow(1.5, $attempts));
            usleep($sleepMs * 1000);
        }
    }


    private function fail(
        string $uuid,
        \Throwable $e,
        bool $unrecoverable = false
    ): void {
        $proxy = $this->sentRequests[$uuid]['proxy'] ?? null;

        if ($unrecoverable) {
            $this->sentRequests[$uuid]['ack']->nack(
                new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e)
            );
        } else {
            $this->sentRequests[$uuid]['ack']->nack($e);
        }

        if ($proxy) {
            $this->pm->release($proxy);
        }

        unset($this->sentRequests[$uuid]);
    }

    private function success(string $uuid): void
    {
        $this->sentRequests[$uuid]['ack']->ack();

        $this->pm->release($this->sentRequests[$uuid]['proxy']);
        unset($this->sentRequests[$uuid]);
    }

    private function markMessageAsFailed($message, $ack, \Throwable $e): void
    {
        $this->sentRequests[$message->getUuid()] = [
            'response' => null,
            'ack' => $ack,
            'proxy' => null,
            'error' => $e,
            'failed' => true
        ];
    }
}
