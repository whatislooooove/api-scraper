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

    public function upsertBatch(array $posts): void
    {
        if (count($posts) === 0) {
            return;
        }

        $sql = $this->buildUpsertSql(count($posts));
        $params = $this->buildParameters($posts);

        $this->conn->executeStatement($sql, $params);
    }

    private function buildUpsertSql(int $count): string
    {
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = sprintf(
                '(:external_id_%d, :title_%d, :description_%d, :body_%d, :created_at_%d)',
                $i, $i, $i, $i, $i
            );
        }

        return sprintf(
            'INSERT INTO post (external_id, title, description, body, created_at)
             VALUES %s
             ON CONFLICT (external_id) DO UPDATE SET
                 title = EXCLUDED.title,
                 description = EXCLUDED.description,
                 body = EXCLUDED.body,
                 created_at = EXCLUDED.created_at',
            implode(', ', $values)
        );
    }

    private function buildParameters(array $chunk): array
    {
        $params = [];
        $counter = 0;

        foreach ($chunk as $i => $post) {
            $params["external_id_$counter"] = $post['external_id'];
            $params["title_$counter"] = $post['title'];
            $params["description_$counter"] = $post['description'];
            $params["body_$counter"] = $post['body'];
            $params["created_at_$counter"] = $post['created_at'];

            $counter++;
        }

        return $params;
    }
}
