<?php

namespace App\Service;

use App\Message\GetPostDetailBatchMessage;
use App\Repository\PostRepository;
use Symfony\Component\Messenger\MessageBusInterface;

class PostsUpdateService
{
    const int MAX_PAGES_TO_UPDATE = 100;

    private int $updatedCount = 0;

    public function __construct(
        private PostRepository $postRepository,
        private PostScraperService $postScraperService,
        private MessageBusInterface $bus
    )
    {
    }

    public function update(): int
    {
        $latestCreatedAt = $this->postRepository->findLatest()?->getCreatedAt();

        for ($i = 1; $i <= self::MAX_PAGES_TO_UPDATE && !is_null($latestCreatedAt); $i++) {
            foreach ($this->postScraperService->getPostsListFromPage($i) as $rawPost) {
                $curObjectDate = new \DateTimeImmutable($rawPost['createdAt']);
                if ($curObjectDate <= $latestCreatedAt) {
                    return $this->updatedCount;
                }

                $this->bus->dispatch(
                    new GetPostDetailBatchMessage($rawPost['id'])
                );
                $this->updatedCount++;
            }
        }

        return $this->updatedCount;
    }
}
