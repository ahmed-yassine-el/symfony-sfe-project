<?php

// src/Repository/ReservationRepository.php
namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Find reservations for a specific user
     *
     * @param User $user
     * @return array
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->leftJoin('r.room', 'room')
            ->addSelect('e', 'room')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reservations that overlap with the given date range
     *
     * @param \DateTimeInterface $start Start date
     * @param \DateTimeInterface $end End date
     * @param int|null $excludeReservationId Exclude this reservation ID (for updates)
     * @return array
     */
    public function findReservationsInRange(\DateTimeInterface $start, \DateTimeInterface $end, ?int $excludeReservationId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->leftJoin('r.room', 'room')
            ->leftJoin('r.user', 'u')
            ->where('r.startTime < :end AND r.endTime > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeReservationId) {
            $qb->andWhere('r.id != :excludeReservationId')
                ->setParameter('excludeReservationId', $excludeReservationId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if a room is available for a specific time period
     *
     * @param Room $room Room entity
     * @param \DateTimeInterface $start Start time
     * @param \DateTimeInterface $end End time
     * @param int|null $excludeReservationId Exclude this reservation ID (for updates)
     * @return bool
     */
    public function isRoomAvailable(Room $room, \DateTimeInterface $start, \DateTimeInterface $end, ?int $excludeReservationId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.room = :room')
            ->andWhere('r.startTime < :end AND r.endTime > :start')
            ->setParameter('room', $room)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeReservationId) {
            $qb->andWhere('r.id != :excludeReservationId')
                ->setParameter('excludeReservationId', $excludeReservationId);
        }

        return count($qb->getQuery()->getResult()) === 0;
    }

    /**
     * Find reservations for a specific room in a date range
     *
     * @param Room $room Room entity
     * @param \DateTimeInterface $start Start date
     * @param \DateTimeInterface $end End date
     * @return array
     */
    public function findReservationsForRoom(Room $room, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->leftJoin('r.user', 'u')
            ->where('r.room = :room')
            ->andWhere('r.startTime < :end AND r.endTime > :start')
            ->setParameter('room', $room)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all reservations with event and room data for calendar
     *
     * @param \DateTimeInterface $start Start date
     * @param \DateTimeInterface $end End date
     * @return array
     */
    public function findReservationsForCalendar(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->leftJoin('r.room', 'room')
            ->leftJoin('r.user', 'u')
            ->where('r.startTime < :end AND r.endTime > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active reservations (end time is in the future)
     */
    public function findActiveReservations(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->leftJoin('r.room', 'room')
            ->addSelect('e', 'room')
            ->where('r.endTime > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('r.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
    /**
     * Find reservations for a specific room within a date range
     */
    /**
     * Find all reservations with associated user and event data
     * Ordered by start time descending (newest first)
     */
    public function findAllWithUserAndEvent(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.event', 'e')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.room', 'room')
            ->addSelect('e', 'u', 'room')
            ->orderBy('r.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
