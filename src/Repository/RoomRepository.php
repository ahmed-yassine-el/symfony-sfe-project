<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Find all rooms ordered by name
     *
     * @return Room[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find rooms that are available during a specific time period
     *
     * @param \DateTimeInterface $start Start time
     * @param \DateTimeInterface $end End time
     * @return Room[]
     */
    public function findAvailableRooms(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reservations', 'res')
            ->where('res.id IS NULL OR NOT (res.startTime < :end AND res.endTime > :start)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find rooms with their reservation count for a date range
     *
     * @param \DateTimeInterface $start Start date
     * @param \DateTimeInterface $end End date
     * @return array
     */
    public function findRoomsWithReservationCount(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reservations', 'res', 'WITH', 'res.startTime < :end AND res.endTime > :start')
            ->select('r.id, r.name, COUNT(res.id) as reservationCount')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('r.id, r.name')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the most popular rooms based on reservation count
     *
     * @param int $limit
     * @param \DateTimeInterface|null $since
     * @return array
     */
    public function findMostPopularRooms(int $limit = 10, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.reservations', 'res')
            ->select('r.id, r.name, COUNT(res.id) as reservationCount')
            ->groupBy('r.id, r.name')
            ->orderBy('reservationCount', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('res.startTime >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }
}