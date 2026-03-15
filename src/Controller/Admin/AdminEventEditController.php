<?php

namespace App\Controller\Admin;

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

#[Route('/admin/event')]
#[IsGranted('ROLE_ADMIN')]
class AdminEventEditController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_admin_edit_event', methods: ['GET', 'POST'])]
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
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Get the first reservation (admin can edit any event)
        $reservation = $event->getReservations()->first();

        if (!$reservation) {
            $this->addFlash('error', 'Aucune réservation associée à cet événement.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $form = $this->createForm(EventTypeForm::class, $event);

        // Populate the unmapped fields with current reservation data
        $form->get('room')->setData($reservation->getRoom());

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
                        return $this->render('admin/edit-event.html.twig', [
                            'event' => $event,
                            'form' => $form->createView(),
                            'reservation' => $reservation,
                        ]);
                    }

                    // Check for room conflicts using the NEW values from the form
                    $conflictingReservations = $reservationRepository->findReservationsInRange(
                        $newStartTime,
                        $newEndTime,
                        $reservation->getId() // Exclude current reservation
                    );

                    // Filter conflicts to only those in the same room
                    $roomConflicts = array_filter($conflictingReservations, function($res) use ($newRoom) {
                        return $res->getRoom()->getId() === $newRoom->getId();
                    });

                    if (!empty($roomConflicts)) {
                        $this->addFlash('error', 'Conflit de salle détecté! Ce créneau horaire est déjà réservé.');
                        return $this->render('admin/edit-event.html.twig', [
                            'event' => $event,
                            'form' => $form->createView(),
                            'reservation' => $reservation,
                            'conflictingReservations' => $roomConflicts,
                        ]);
                    }

                    // Update the reservation with new values
                    $reservation->setStartTime($newStartTime);
                    $reservation->setEndTime($newEndTime);
                    $reservation->setRoom($newRoom);

                    // Explicitly persist the reservation
                    $entityManager->persist($reservation);

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Format de date invalide.');
                    return $this->render('admin/edit-event.html.twig', [
                        'event' => $event,
                        'form' => $form->createView(),
                        'reservation' => $reservation,
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
                return $this->redirectToRoute('app_admin_dashboard');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour: ' . $e->getMessage());
            }
        }

        return $this->render('admin/edit-event.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/show', name: 'app_admin_show_event', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $eventRepository = $entityManager->getRepository(Event::class);
        $event = $eventRepository->find($id);

        if (!$event) {
            $this->addFlash('error', 'Événement non trouvé.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $reservation = $event->getReservations()->first();

        return $this->render('admin/show-event.html.twig', [
            'event' => $event,
            'reservation' => $reservation,
        ]);
    }
}