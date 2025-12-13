<?php

namespace App\Repository\DBAL;

use App\DTO\Input\Post\CreatePostInputDTO;
use Doctrine\DBAL\Connection;

class PostWriteRepository
{
    public function __construct(private Connection $conn)
    {
    }

    public function insertIfNotExists(CreatePostInputDTO $dto): void
    {
        $this->conn->executeStatement(
            '
            INSERT INTO post (external_id, title, description, body, created_at)
            VALUES (:external_id, :title, :description, :body, :created_at)
            ON CONFLICT (external_id) DO NOTHING
            ',
            [
                'external_id' => $dto->externalId,
                'title' => $dto->title,
                'description' => $dto->description,
                'body' => $dto->body,
                'created_at' => $dto->createdAt->format('Y-m-d H:i:s'),
            ]
        );
    }
}
