<?php

namespace App\Controller\UserDashboard;

use App\Entity\Event;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class EventDeleteController extends AbstractController
{
    #[Route('/dashboard/reservation/{id}/delete', name: 'app_dashboard_delete_reservation', methods: ['POST'])]
    public function deleteReservation(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the current user owns this reservation
        $currentUser = $this->getUser();

        if ($reservation->getUser() !== $currentUser) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer cette réservation.');
            return $this->redirectToRoute('app_dashboard_reservations');
        }

        // Verify CSRF token
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            try {
                $eventName = $reservation->getEvent()->getName();
                $now = new \DateTime();

                // Check if the reservation is currently active (optional security check)
                if ($reservation->getStartTime() <= $now && $reservation->getEndTime() > $now) {
                    $this->addFlash('error', 'Impossible de supprimer une réservation en cours. Veuillez attendre la fin de l\'événement.');
                    return $this->redirectToRoute('app_dashboard_reservations');
                }

                $isReservationInPast = $reservation->getEndTime() < $now;

                // Get the event before deleting the reservation
                $event = $reservation->getEvent();

                // Delete the reservation
                $entityManager->remove($reservation);

                // Check if this was the last reservation for this event
                $remainingReservations = $entityManager->getRepository(Reservation::class)
                    ->createQueryBuilder('r')
                    ->where('r.event = :event')
                    ->andWhere('r.id != :reservationId')
                    ->setParameter('event', $event)
                    ->setParameter('reservationId', $reservation->getId())
                    ->getQuery()
                    ->getResult();

                // If no other reservations exist for this event, delete the event too
                if (empty($remainingReservations)) {
                    $entityManager->remove($event);
                }

                $entityManager->flush();

                // Success message based on reservation status
                if ($isReservationInPast) {
                    $this->addFlash('success',
                        sprintf('La réservation pour l\'événement "%s" a été supprimée avec succès.', $eventName)
                    );
                } else {
                    $this->addFlash('success',
                        sprintf('La réservation pour l\'événement "%s" a été annulée avec succès.', $eventName)
                    );
                }

            } catch (\Exception $e) {
                $this->addFlash('error',
                    'Une erreur est survenue lors de la suppression de la réservation : ' . $e->getMessage()
                );

                // Log the error for debugging
                error_log('Reservation deletion error: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_dashboard_reservations');
    }

    #[Route('/dashboard/event/{id}/delete', name: 'app_dashboard_delete_event', methods: ['POST'])]
    public function deleteEvent(
        Request $request,
        Event $event,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the current user owns any reservation for this event
        $currentUser = $this->getUser();
        $userReservation = null;

        foreach ($event->getReservations() as $reservation) {
            if ($reservation->getUser() === $currentUser) {
                $userReservation = $reservation;
                break;
            }
        }

        // If user doesn't have a reservation for this event, deny access
        if (!$userReservation) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer cet événement.');
            return $this->redirectToRoute('app_dashboard_reservations');
        }

        // Verify CSRF token
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            try {
                $eventName = $event->getName();
                $now = new \DateTime();

                // Check if any reservation for this event is currently active
                $hasActiveReservation = false;
                foreach ($event->getReservations() as $reservation) {
                    if ($reservation->getStartTime() <= $now && $reservation->getEndTime() > $now) {
                        $hasActiveReservation = true;
                        break;
                    }
                }

                if ($hasActiveReservation) {
                    $this->addFlash('error', 'Impossible de supprimer un événement en cours. Veuillez attendre la fin de l\'événement.');
                    return $this->redirectToRoute('app_dashboard_reservations');
                }

                $isEventInPast = $userReservation->getEndTime() < $now;

                // Delete the event (this will cascade to delete associated reservations)
                $entityManager->remove($event);
                $entityManager->flush();

                // Success message based on event status
                if ($isEventInPast) {
                    $this->addFlash('success',
                        sprintf('L\'événement "%s" et toutes ses réservations ont été supprimés avec succès.', $eventName)
                    );
                } else {
                    $this->addFlash('success',
                        sprintf('L\'événement "%s" et toutes ses réservations ont été annulés avec succès.', $eventName)
                    );
                }

            } catch (\Exception $e) {
                $this->addFlash('error',
                    'Une erreur est survenue lors de la suppression de l\'événement : ' . $e->getMessage()
                );

                // Log the error for debugging
                error_log('Event deletion error: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_dashboard_reservations');
    }

    #[Route('/dashboard/reservations/bulk-delete', name: 'app_dashboard_bulk_delete_reservations', methods: ['POST'])]
    public function bulkDeleteReservations(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify CSRF token
        if ($this->isCsrfTokenValid('bulk_delete_reservations', $request->request->get('_token'))) {
            $reservationIds = $request->request->get('reservation_ids', []);
            $currentUser = $this->getUser();

            if (!empty($reservationIds)) {
                try {
                    $deletedCount = 0;
                    $skippedCount = 0;
                    $now = new \DateTime();

                    foreach ($reservationIds as $reservationId) {
                        $reservation = $entityManager->getRepository(Reservation::class)->find($reservationId);

                        if (!$reservation || $reservation->getUser() !== $currentUser) {
                            $skippedCount++;
                            continue;
                        }

                        // Check if reservation is currently running
                        if ($reservation->getStartTime() <= $now && $reservation->getEndTime() > $now) {
                            $skippedCount++;
                            continue;
                        }

                        // Delete the reservation
                        $entityManager->remove($reservation);
                        $deletedCount++;
                    }

                    $entityManager->flush();

                    // Prepare success message
                    $message = '';
                    if ($deletedCount > 0) {
                        $message .= sprintf('%d réservation(s) supprimée(s) avec succès', $deletedCount);
                    }
                    if ($skippedCount > 0) {
                        if ($message) $message .= '. ';
                        $message .= sprintf('%d réservation(s) ignorée(s) (non autorisée ou en cours)', $skippedCount);
                    }

                    if ($deletedCount > 0) {
                        $this->addFlash('success', $message);
                    } else {
                        $this->addFlash('warning', 'Aucune réservation n\'a pu être supprimée.');
                    }

                } catch (\Exception $e) {
                    $this->addFlash('error',
                        'Une erreur est survenue lors de la suppression en masse : ' . $e->getMessage()
                    );

                    // Log the error for debugging
                    error_log('Bulk reservation deletion error: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('warning', 'Aucune réservation sélectionnée pour la suppression.');
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_dashboard_reservations');
    }
}