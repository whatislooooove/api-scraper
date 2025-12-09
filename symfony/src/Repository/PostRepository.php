<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    const int BATCH_SIZE = 100;

    private array $batchBuffer = [];

    public function __construct(private EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function create(Post $post): Post
    {
        $this->em->persist($post);
        $this->batchBuffer[] = $post;

        if (count($this->batchBuffer) >= self::BATCH_SIZE) {
            $this->flushBatch();
        }

        return $post;
    }

    private function flushBatch(): void
    {
        if (count($this->batchBuffer) === 0) {
            return;
        }

        $this->em->flush();
        $this->em->clear();
        $this->batchBuffer = [];
    }

    public function __destruct()
    {
        $this->flushBatch();
    }
}
