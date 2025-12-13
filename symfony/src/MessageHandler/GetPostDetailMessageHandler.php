<?php

namespace App\MessageHandler;

use App\Factory\PostFactory;
use App\Message\GetPostDetailMessage;
use App\Service\PostScraperService;
use App\Service\PostService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPostDetailMessageHandler
{
    public function __construct(
        private PostScraperService $postScraper,
        private PostFactory $postFactory,
        private PostService $postService,
    )
    {
    }

    public function __invoke(GetPostDetailMessage $message): void
    {
        $post = $this->postScraper->getPostDetail($message->getUuid());

        $createPostInputDTO = $this->postFactory->makeCreatePostInputDTO($post);
        $this->postService->createIfNotExists($createPostInputDTO);
    }
}
