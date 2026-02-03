<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findPaginated(int $page, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($query);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    public function findWithAvailableEvents(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.events', 'e')
            ->andWhere('e.isPublished = true')
            ->andWhere('e.startAt >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->distinct()
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
