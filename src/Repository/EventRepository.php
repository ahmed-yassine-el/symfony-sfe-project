<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Find events that have reservations overlapping with the given date range
     *
     * @param \DateTime $start Start date
     * @param \DateTime $end End date
     * @param int|null $excludeEventId Exclude this event ID (for updates)
     * @return array
     */
    public function findEventsInRange(\DateTime $start, \DateTime $end, ?int $excludeEventId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.reservations', 'r')
            ->where('r.startTime < :end AND r.endTime > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeEventId) {
            $qb->andWhere('e.id != :excludeEventId')
                ->setParameter('excludeEventId', $excludeEventId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all events with their reservations (for admin management)
     *
     * @return array
     */
    public function findAllWithReservations(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.reservations', 'r')
            ->leftJoin('r.room', 'room')
            ->leftJoin('r.user', 'u')
            ->addSelect('r', 'room', 'u')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events with upcoming reservations
     *
     * @param \DateTime|null $fromDate
     * @return array
     */
    public function findEventsWithUpcomingReservations(?\DateTime $fromDate = null): array
    {
        $fromDate = $fromDate ?? new \DateTime('today');

        return $this->createQueryBuilder('e')
            ->leftJoin('e.reservations', 'r')
            ->leftJoin('r.room', 'room')
            ->leftJoin('r.user', 'u')
            ->addSelect('r', 'room', 'u')
            ->where('r.startTime >= :fromDate')
            ->setParameter('fromDate', $fromDate)
            ->orderBy('r.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all unique rooms that have events
     *
     * @return array
     */
    public function findAllRooms(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.reservations', 'r')
            ->leftJoin('r.room', 'room')
            ->select('DISTINCT room.id, room.name')
            ->where('room.id IS NOT NULL')
            ->orderBy('room.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}