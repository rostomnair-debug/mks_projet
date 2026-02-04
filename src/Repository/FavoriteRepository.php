<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * @param Event[] $events
     * @return int[]
     */
    public function findEventIdsForUser(User $user, array $events): array
    {
        if ($events === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.event) AS event_id')
            ->where('f.user = :user')
            ->andWhere('f.event IN (:events)')
            ->setParameter('user', $user)
            ->setParameter('events', $events)
            ->getQuery()
            ->getArrayResult();

        return array_map('intval', array_column($rows, 'event_id'));
    }

    public function isFavorite(User $user, Event $event): bool
    {
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.user = :user')
            ->andWhere('f.event = :event')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * @return Favorite[]
     */
    public function findByUserWithEvents(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.event', 'e')
            ->addSelect('e')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
