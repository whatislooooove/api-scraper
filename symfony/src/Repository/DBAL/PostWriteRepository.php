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
        if ($posts === []) {
            return;
        }

        $sql = <<<SQL
INSERT INTO post (external_id, title, description, body, created_at)
VALUES %s
ON CONFLICT (external_id) DO UPDATE SET
    title = EXCLUDED.title,
    description = EXCLUDED.description,
    body = EXCLUDED.body,
    created_at = EXCLUDED.created_at
SQL;

        $values = [];
        $params = [];

        $i = 0;

        foreach ($posts as $post) {
            $values[] = sprintf(
                '(:external_id_%d, :title_%d, :description_%d, :body_%d, :created_at_%d)',
                $i, $i, $i, $i, $i
            );

            $params["external_id_$i"] = $post['external_id'];
            $params["title_$i"] = $post['title'];
            $params["description_$i"] = $post['description'];
            $params["body_$i"] = $post['body'];
            $params["created_at_$i"] = $post['created_at'];

            $i++;
        }

        $finalSql = sprintf($sql, implode(",\n", $values));
        $this->conn->executeStatement($finalSql, $params);
    }
}
