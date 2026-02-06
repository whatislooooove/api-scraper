<?php

namespace App\Service;

use App\DTO\Input\Post\CreatePostInputDTO;
use App\Entity\Post;
use App\Factory\PostFactory;
use App\Repository\PostRepository;
use App\Validator\PostValidator;

class PostService
{
    private const int BATCH_SIZE = 5;
    private int $counter = 0;

    public function __construct(
        private PostRepository $postRepository,
        private PostFactory $postFactory,
        private PostValidator $validator
    ) {
    }

    public function createIfNotExists(CreatePostInputDTO $postDTO): Post
    {
        $post = $this->postRepository->findOneBy([
            'externalId' => $postDTO->externalId
        ]);

        if (!$post) {
            $postEntityToCreate = $this->postFactory->makePost($postDTO);
            $this->validator->validate($postEntityToCreate);

            $this->postRepository->save($postEntityToCreate);
            $post = $postEntityToCreate;

            $this->counter++;

            if ($this->counter % self::BATCH_SIZE === 0) {
                $this->flushAndClear();
            }
        }

        return $post;
    }

    public function flushAndClear(): void
    {
        $this->postRepository->flush();
        $this->postRepository->clear();
    }
}
