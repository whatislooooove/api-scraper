<?php

namespace App\Service;

use App\DTO\Input\Post\CreatePostInputDTO;
use App\Entity\Post;
use App\Factory\PostFactory;
use App\Repository\PostRepository;

class PostService
{
    public function __construct(private PostRepository $postRepository, private PostFactory $postFactory)
    {
    }

    public function createIfNotExists(CreatePostInputDTO $postDTO): Post
    {
        $post = $this->postRepository->findOneBy(['externalId' => $postDTO->externalId]);
        // TODO: надо проверить
        if (is_null($post)) {
            $postEntityToCreate = $this->postFactory->makePost($postDTO);

            $post = $this->postRepository->addToBatch($postEntityToCreate);
        }

        return $post;
    }
}
