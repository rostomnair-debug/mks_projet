<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
