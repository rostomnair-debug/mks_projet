<?php

namespace App\Repository;

use App\Entity\ContactRequest;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class ContactRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactRequest::class);
    }

    public function findPaginated(int $page, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

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

    public function findByUserPaginated(User $user, int $page, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.event', 'e')
            ->addSelect('e')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC');

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

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countRespondedSince(User $user, ?DateTimeImmutable $since): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :user')
            ->andWhere('c.respondedAt IS NOT NULL')
            ->setParameter('user', $user);

        if ($since) {
            $qb->andWhere('c.respondedAt > :since')
                ->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
