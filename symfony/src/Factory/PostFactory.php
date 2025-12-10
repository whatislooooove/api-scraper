<?php

namespace App\Factory;

use App\DTO\Input\Post\CreatePostInputDTO;
use App\Entity\Post;

class PostFactory
{
    public function makePost(CreatePostInputDTO $postDTO): Post
    {
        $post = new Post();
        $post->setExternalId($postDTO->externalId);
        $post->setTitle($postDTO->title);
        $post->setDescription($postDTO->description);
        $post->setCreatedAt($postDTO->createdAt);
        $post->setBody($postDTO->body);

        return $post;
    }

    public function makeCreatePostInputDTO(array $data): CreatePostInputDTO
    {
        $post = new CreatePostInputDTO();

        $post->externalId = $data['id'];
        $post->title = $data['title'];
        $post->body = $data['body'];
        $post->description = $data['description'];
        $post->createdAt = new \DateTimeImmutable($data['createdAt']);

        return $post;
    }
}
