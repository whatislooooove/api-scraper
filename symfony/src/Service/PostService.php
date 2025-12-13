<?php

namespace App\Service;

use App\DTO\Input\Post\CreatePostInputDTO;
use App\Repository\DBAL\PostWriteRepository;

class PostService
{
    public function __construct(private PostWriteRepository $postRepository)
    {
    }

    public function createIfNotExists(CreatePostInputDTO $postDTO): void
    {
        $this->postRepository->insertIfNotExists($postDTO);
    }
}
