<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function searchPublic(array $filters = []): array
    {
        $qb = $this->createPublicQueryBuilder($filters);

        return $qb->getQuery()->getResult();
    }

    public function searchPublicPaginated(array $filters, int $page, int $limit = 9): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $qb = $this->createPublicQueryBuilder($filters);
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

    public function searchAdminPaginated(array $criteria, string $sortField, string $dir, int $page, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.' . $sortField, $dir);

        if (!empty($criteria['organizer'])) {
            $qb->andWhere('e.organizer = :organizer')
                ->setParameter('organizer', $criteria['organizer']);
        }

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

    private function createPublicQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isPublished = true')
            ->andWhere('e.startAt >= :now')
            ->setParameter('now', new \DateTimeImmutable());

        if (!empty($filters['q'])) {
            $qb->andWhere('e.title LIKE :term OR e.description LIKE :term')
                ->setParameter('term', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('e.category = :category')
                ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['district'])) {
            $qb->andWhere('e.district LIKE :district')
                ->setParameter('district', '%' . $filters['district'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $startDate = $this->safeDate($filters['start_date']);
            if ($startDate) {
                $qb->andWhere('e.startAt >= :startDate')
                    ->setParameter('startDate', $startDate);
            }
        }

        if (!empty($filters['end_date'])) {
            $endDate = $this->safeDate($filters['end_date'], ' 23:59:59');
            if ($endDate) {
                $qb->andWhere('(e.endAt <= :endDate OR (e.endAt IS NULL AND e.startAt <= :endDate))')
                    ->setParameter('endDate', $endDate);
            }
        }

        if (!empty($filters['free'])) {
            $qb->andWhere('e.priceCents = 0');
        } elseif (!empty($filters['price'])) {
            $range = $this->parsePriceRange((string) $filters['price']);
            if ($range) {
                $qb->andWhere('e.priceCents >= :priceMin')
                    ->setParameter('priceMin', $range['min']);
                if ($range['max'] !== null) {
                    $qb->andWhere('e.priceCents <= :priceMax')
                        ->setParameter('priceMax', $range['max']);
                }
            }
        }

        $sort = (string) ($filters['sort'] ?? '');
        switch ($sort) {
            case 'date_desc':
                $qb->orderBy('e.startAt', 'DESC');
                break;
            case 'title_asc':
                $qb->orderBy('e.title', 'ASC');
                break;
            case 'title_desc':
                $qb->orderBy('e.title', 'DESC');
                break;
            case 'venue_asc':
                $qb->orderBy('e.venueName', 'ASC');
                break;
            case 'venue_desc':
                $qb->orderBy('e.venueName', 'DESC');
                break;
            default:
                $qb->orderBy('e.startAt', 'ASC');
        }

        return $qb;
    }

    private function safeDate(string $value, string $suffix = ''): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value . $suffix);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function parsePriceRange(string $value): ?array
    {
        $value = trim($value);
        $map = [
            '0-10' => ['min' => 0, 'max' => 1000],
            '10-20' => ['min' => 1000, 'max' => 2000],
            '20-50' => ['min' => 2000, 'max' => 5000],
            '50+' => ['min' => 5000, 'max' => null],
        ];

        return $map[$value] ?? null;
    }

    public function findOneByExternal(string $source, string $externalId): ?Event
    {
        return $this->findOneBy([
            'externalSource' => $source,
            'externalId' => $externalId,
        ]);
    }

    public function findMapPoints(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isPublished = true')
            ->andWhere('e.startAt >= :now')
            ->andWhere('e.latitude IS NOT NULL')
            ->andWhere('e.longitude IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
