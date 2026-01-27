<?php

namespace App\Service;

use App\DTO\Input\Post\CreatePostInputDTO;
use App\Entity\Post;
use App\Factory\PostFactory;
use App\Repository\PostRepository;
use App\Validator\PostValidator;

class PostService
{
    public function __construct(
        private PostRepository $postRepository,
        private PostFactory $postFactory,
        private PostValidator $validator
    )
    {
    }

    public function createIfNotExists(CreatePostInputDTO $postDTO): Post
    {
        $post = $this->postRepository->findOneBy(['externalId' => $postDTO->externalId]);

        if (is_null($post)) {
            $postEntityToCreate = $this->postFactory->makePost($postDTO);
            $this->validator->validate($postEntityToCreate);
            $post = $this->postRepository->create($postEntityToCreate);
        }

        return $post;
    }
}
