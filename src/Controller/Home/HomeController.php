<?php

namespace App\Controller\Home;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    // Public home page - everyone can access
    #[Route('/', name: 'app_home')]
    #[Route('/home', name: 'app_home_alt')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        // Get active reservations (end time is in the future)
        $activeReservations = $reservationRepository->findActiveReservations();

        return $this->render('home/index.html.twig', [
            'activeReservations' => $activeReservations,
        ]);
    }

    #[Route('/api/events', name: 'api_events', methods: ['GET'])]
    public function getActiveEvents(ReservationRepository $reservationRepository): JsonResponse
    {
        $activeReservations = $reservationRepository->findActiveReservations();

        $events = [];
        foreach ($activeReservations as $reservation) {
            $events[] = [
                'id' => $reservation->getId(),
                'event_name' => $reservation->getEvent()->getName(),
                'room_name' => $reservation->getRoom()->getName(),
                'start_date' => $reservation->getStartTime()->format('Y-m-d H:i'),
                'end_date' => $reservation->getEndTime()->format('Y-m-d H:i'),
                'description' => $reservation->getEvent()->getDescription(),
                'organizer' => $reservation->getUser()->getEmail(),
                'duration' => $this->calculateDuration($reservation->getStartTime(), $reservation->getEndTime())
            ];
        }

        return $this->json($events);
    }

    // DEBUG METHOD - remove in production
    #[Route('/debug/reservations', name: 'debug_reservations')]
    public function debugReservations(ReservationRepository $reservationRepository): Response
    {
        // Get ALL reservations (not just active ones)
        $allReservations = $reservationRepository->findAll();

        // Get active reservations
        $activeReservations = $reservationRepository->findActiveReservations();

        $debug = [
            'total_reservations' => count($allReservations),
            'active_reservations' => count($activeReservations),
            'current_time' => (new \DateTime())->format('Y-m-d H:i:s'),
            'all_reservations_details' => [],
            'active_reservations_details' => []
        ];

        // Debug all reservations
        foreach ($allReservations as $reservation) {
            $debug['all_reservations_details'][] = [
                'id' => $reservation->getId(),
                'event_name' => $reservation->getEvent() ? $reservation->getEvent()->getName() : 'NO EVENT',
                'room_name' => $reservation->getRoom() ? $reservation->getRoom()->getName() : 'NO ROOM',
                'user_email' => $reservation->getUser() ? $reservation->getUser()->getEmail() : 'NO USER',
                'start_time' => $reservation->getStartTime() ? $reservation->getStartTime()->format('Y-m-d H:i:s') : 'NULL',
                'end_time' => $reservation->getEndTime() ? $reservation->getEndTime()->format('Y-m-d H:i:s') : 'NULL',
                'is_future' => $reservation->getEndTime() ? ($reservation->getEndTime() > new \DateTime() ? 'YES' : 'NO') : 'NULL'
            ];
        }

        // Debug active reservations
        foreach ($activeReservations as $reservation) {
            $debug['active_reservations_details'][] = [
                'id' => $reservation->getId(),
                'event_name' => $reservation->getEvent()->getName(),
                'room_name' => $reservation->getRoom()->getName(),
                'start_time' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
                'end_time' => $reservation->getEndTime()->format('Y-m-d H:i:s')
            ];
        }

        return $this->json($debug);
    }

    private function calculateDuration(\DateTimeInterface $start, \DateTimeInterface $end): string
    {
        $diff = $start->diff($end);

        if ($diff->days > 0) {
            return $diff->days . 'j';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h';
        } else {
            return $diff->i . 'min';
        }
    }
}