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
    public function __construct(private EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function create(Post $post): Post
    {
        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }
}
