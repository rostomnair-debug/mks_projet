<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function getReservedCountForUserEvent(User $user, Event $event): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->andWhere('r.user = :user')
            ->andWhere('r.event = :event')
            ->andWhere('r.isCancelled = false')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function findByUserPaginated(User $user, int $page, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->addSelect('e')
            ->leftJoin('e.category', 'c')
            ->addSelect('c')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

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
}
