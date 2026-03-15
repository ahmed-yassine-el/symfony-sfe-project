<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    #[Route('/admin/all_events', name: 'app_admin_dashboard')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        // Get all reservations with user information
        $allReservations = $reservationRepository->findAllWithUserAndEvent();

        // Get some statistics for the admin dashboard
        $totalReservations = count($allReservations);

        $activeReservations = array_filter($allReservations, function($reservation) {
            $now = new \DateTime();
            return $reservation->getStartTime() <= $now && $reservation->getEndTime() > $now;
        });

        $upcomingReservations = array_filter($allReservations, function($reservation) {
            $now = new \DateTime();
            return $reservation->getStartTime() > $now;
        });

        $completedReservations = array_filter($allReservations, function($reservation) {
            $now = new \DateTime();
            return $reservation->getEndTime() < $now;
        });

        return $this->render('admin/all-reservations.html.twig', [
            'allReservations' => $allReservations,
            'totalReservations' => $totalReservations,
            'activeCount' => count($activeReservations),
            'upcomingCount' => count($upcomingReservations),
            'completedCount' => count($completedReservations),
        ]);
    }

    #[Route('/admin/event/{id}/delete', name: 'app_admin_delete_event', methods: ['POST'])]
    public function deleteEvent(Event $event, EntityManagerInterface $em): Response
    {
        try {
            // Delete associated reservations first (if cascade isn't set up)
            foreach ($event->getReservations() as $reservation) {
                $em->remove($reservation);
            }

            // Delete the event
            $em->remove($event);
            $em->flush();

            $this->addFlash('success', 'Événement et réservation(s) supprimé(s) avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }
}