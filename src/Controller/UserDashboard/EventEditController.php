<?php


namespace App\Controller\UserDashboard;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Form\EventTypeForm;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/event')]
#[IsGranted('ROLE_USER')]
class EventEditController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_dashboard_edit_event', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ReservationRepository $reservationRepository
    ): Response {
        // Get the event repository and manually fetch the event
        $eventRepository = $entityManager->getRepository(Event::class);
        $event = $eventRepository->find($id);

        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_dashboard_reservations');
        }

        // Check if user owns this event (through reservation)
        $userReservation = null;
        foreach ($event->getReservations() as $reservation) {
            if ($reservation->getUser() === $this->getUser()) {
                $userReservation = $reservation;
                break;
            }
        }

        if (!$userReservation) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cet événement.');
            return $this->redirectToRoute('app_dashboard_reservations');
        }

        $form = $this->createForm(EventTypeForm::class, $event);

        // Populate the unmapped fields with current reservation data
        if ($userReservation) {
            $form->get('room')->setData($userReservation->getRoom());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the new values from the form
            $startTime = $request->request->get('startTime');
            $endTime = $request->request->get('endTime');
            $newRoom = $form->get('room')->getData();

            if ($startTime && $endTime && $newRoom) {
                try {
                    $newStartTime = new \DateTime($startTime);
                    $newEndTime = new \DateTime($endTime);

                    // Validate that end time is after start time
                    if ($newEndTime <= $newStartTime) {
                        $this->addFlash('error', 'L\'heure de fin doit être postérieure à l\'heure de début.');
                        return $this->render('dashboard/edit-event.html.twig', [
                            'event' => $event,
                            'form' => $form->createView(),
                            'reservation' => $userReservation,
                        ]);
                    }

                    // Check for room conflicts using the NEW values from the form
                    $conflictingReservations = $reservationRepository->findReservationsInRange(
                        $newStartTime,
                        $newEndTime,
                        $userReservation->getId() // Exclude current reservation
                    );

                    // Filter conflicts to only those in the same room
                    $roomConflicts = array_filter($conflictingReservations, function($res) use ($newRoom) {
                        return $res->getRoom()->getId() === $newRoom->getId();
                    });

                    if (!empty($roomConflicts)) {
                        $this->addFlash('error', 'Conflit de salle détecté! Ce créneau horaire est déjà réservé.');
                        return $this->render('dashboard/edit-event.html.twig', [
                            'event' => $event,
                            'form' => $form->createView(),
                            'reservation' => $userReservation,
                            'conflictingReservations' => $roomConflicts,
                        ]);
                    }

                    // Update the reservation with new values
                    $userReservation->setStartTime($newStartTime);
                    $userReservation->setEndTime($newEndTime);
                    $userReservation->setRoom($newRoom);

                    // Explicitly persist the reservation
                    $entityManager->persist($userReservation);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Format de date invalide.');
                    return $this->render('dashboard/edit-event.html.twig', [
                        'event' => $event,
                        'form' => $form->createView(),
                        'reservation' => $userReservation,
                    ]);
                }
            }

            // Handle image upload if present
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $event->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                }
            }

            // Persist the event (for name, description changes)
            $entityManager->persist($event);

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Événement mis à jour avec succès!');
                return $this->redirectToRoute('app_dashboard_reservations');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour: ' . $e->getMessage());
            }
        }

        return $this->render('dashboard/edit-event.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'reservation' => $userReservation,
        ]);
    }

    #[Route('/{id}/show', name: 'app_dashboard_show_event', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $eventRepository = $entityManager->getRepository(Event::class);
        $event = $eventRepository->find($id);

        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_dashboard_reservations');
        }

        // Check if user owns this event (through reservation)
        $userReservation = null;
        foreach ($event->getReservations() as $reservation) {
            if ($reservation->getUser() === $this->getUser()) {
                $userReservation = $reservation;
                break;
            }
        }

        if (!$userReservation) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à voir cet événement.');
            return $this->redirectToRoute('app_dashboard_reservations');
        }

        return $this->render('dashboard/event_edit/show.html.twig', [
            'event' => $event,
            'reservation' => $userReservation,
        ]);
    }
}