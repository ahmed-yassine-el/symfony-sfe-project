<?php

namespace App\Controller\UserDashboard;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Form\EventTypeForm;
use App\Repository\ReservationRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();

        // Get user's own reservations
        $userReservations = $reservationRepository->findByUser($user);

        // Get all active reservations for overview
        $activeReservations = $reservationRepository->findActiveReservations();

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'userReservations' => $userReservations,
            'activeReservations' => $activeReservations,
        ]);
    }

    #[Route('/dashboard/profile', name: 'app_dashboard_profile')]
    public function profile(): Response
    {
        return $this->render('dashboard/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/dashboard/my-reservations', name: 'app_dashboard_reservations')]
    public function myReservations(ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        $reservations = $reservationRepository->findByUser($user);

        return $this->render('dashboard/my-reservations.html.twig', [
            'userReservations' => $reservations,
        ]);
    }

    #[Route('/dashboard/create-event', name: 'app_dashboard_create_event')]
    public function createEvent(
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepository,
        RoomRepository $roomRepository
    ): Response {
        $event = new Event();
        $form = $this->createForm(EventTypeForm::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Debug: Let's see what's actually in the request
            $allPostData = $request->request->all();
            error_log('=== USER FORM SUBMISSION DEBUG ===');
            error_log('All POST data: ' . print_r($allPostData, true));
            error_log('start_datetime: ' . ($request->request->get('start_datetime') ?? 'NOT SET'));
            error_log('end_datetime: ' . ($request->request->get('end_datetime') ?? 'NOT SET'));

            if (!$form->isValid()) {
                // Get form errors for debugging
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $this->addFlash('danger', 'Form contains errors: ' . implode(', ', $errors));

            } else {
                // Get the datetime values from the request
                $startDateTimeString = $request->request->get('start_datetime');
                $endDateTimeString = $request->request->get('end_datetime');

                // Try multiple ways to get the room ID since it's mapped=>false
                $formData = $request->request->all('event');
                $roomId = null;

                // Method 1: From form data array
                if (isset($formData['room'])) {
                    $roomId = $formData['room'];
                }

                // Method 2: Direct from request
                if (!$roomId) {
                    $roomId = $request->request->get('room');
                }

                // Method 3: From the form object itself
                if (!$roomId && $form->has('room')) {
                    $roomData = $form->get('room')->getData();
                    if ($roomData instanceof \App\Entity\Room) {
                        $roomId = $roomData->getId();
                    } elseif (is_numeric($roomData)) {
                        $roomId = $roomData;
                    }
                }

                // Debug output to see what we're getting
                error_log('Start DateTime: ' . ($startDateTimeString ?? 'NULL'));
                error_log('End DateTime: ' . ($endDateTimeString ?? 'NULL'));
                error_log('Room ID: ' . ($roomId ?? 'NULL'));
                error_log('Form Data: ' . print_r($formData, true));

                // Validate that we have the required data
                if (!$startDateTimeString || !$endDateTimeString || !$roomId) {
                    $this->addFlash('danger', 'Veuillez remplir tous les champs obligatoires incluant la date, l\'heure et la salle.');
                    return $this->render('dashboard/create-user.html.twig', [
                        'form' => $form->createView(),
                        'rooms' => $roomRepository->findAllOrderedByName(),
                        'user' => $this->getUser(), // Add this line
                    ]);
                }

                try {
                    // Parse the datetime strings
                    $startTime = new \DateTime($startDateTimeString);
                    $endTime = new \DateTime($endDateTimeString);
                    $room = $roomRepository->find($roomId);

                    if (!$room) {
                        $this->addFlash('danger', 'La salle sélectionnée n\'existe pas.');
                        return $this->render('dashboard/create-user.html.twig', [
                            'form' => $form->createView(),
                            'rooms' => $roomRepository->findAllOrderedByName(),
                            'user' => $this->getUser(), // Add this line
                        ]);
                    }

                    // Validate start time is before end time
                    if ($startTime >= $endTime) {
                        $this->addFlash('danger', 'L\'heure de début doit être antérieure à l\'heure de fin.');
                        return $this->render('dashboard/create-user.html.twig', [
                            'form' => $form->createView(),
                            'rooms' => $roomRepository->findAllOrderedByName(),
                            'user' => $this->getUser(), // Add this line
                        ]);
                    }

                    // Check if the room is available for the requested time
                    $isAvailable = $reservationRepository->isRoomAvailable($room, $startTime, $endTime);

                    if (!$isAvailable) {
                        $this->addFlash('danger', 'Cette salle n\'est pas disponible pour la période sélectionnée. Veuillez vérifier le calendrier et choisir une autre heure.');
                        return $this->render('dashboard/create-user.html.twig', [
                            'form' => $form->createView(),
                            'rooms' => $roomRepository->findAllOrderedByName(),
                            'user' => $this->getUser(), // Add this line
                        ]);
                    }

                    // Handle image upload
                    $imageFile = $form->get('imageFile')->getData();
                    if ($imageFile) {
                        $newFilename = uniqid().'.'.$imageFile->guessExtension();
                        $imageFile->move(
                            $this->getParameter('kernel.project_dir').'/public/uploads',
                            $newFilename
                        );
                        $event->setImage($newFilename);
                    }

                    // Save the event first
                    $em->persist($event);
                    $em->flush(); // Flush to get the event ID

                    // Create the reservation
                    $reservation = new Reservation();
                    $reservation->setEvent($event);
                    $reservation->setRoom($room);
                    $reservation->setStartTime($startTime);
                    $reservation->setEndTime($endTime);

                    // Set the current user
                    $reservation->setUser($this->getUser());

                    $em->persist($reservation);
                    $em->flush();

                    $this->addFlash('success', 'Événement et réservation créés avec succès !');
                    return $this->redirectToRoute('app_dashboard_create_event');

                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de la sauvegarde de l\'événement : ' . $e->getMessage());
                }
            }
        }

        return $this->render('dashboard/create-event.html.twig', [
            'form' => $form->createView(),
            'rooms' => $roomRepository->findAllOrderedByName(),
            'user' => $this->getUser(), // Add this line
        ]);
    }

    #[Route('/dashboard/event/reservations', name: 'app_dashboard_event_reservations', methods: ['GET'])]
    public function getReservations(Request $request, ReservationRepository $reservationRepository): JsonResponse
    {
        try {
            $start = new \DateTime($request->query->get('start'));
            $end = new \DateTime($request->query->get('end'));
            $roomId = $request->query->get('room_id');

            $reservations = $reservationRepository->findReservationsForCalendar($start, $end);

            $formattedReservations = [];
            foreach ($reservations as $reservation) {
                // If room filter is set, only include reservations for that room
                if ($roomId && $reservation->getRoom()->getId() != $roomId) {
                    continue;
                }

                $formattedReservations[] = [
                    'id' => $reservation->getId(),
                    'title' => $reservation->getEvent()->getName(),
                    'start' => $reservation->getStartTime()->format('Y-m-d\TH:i:s'),
                    'end' => $reservation->getEndTime()->format('Y-m-d\TH:i:s'),
                    'room' => $reservation->getRoom()->getName(),
                    'roomId' => $reservation->getRoom()->getId(),
                    'description' => $reservation->getEvent()->getDescription(),
                    'eventId' => $reservation->getEvent()->getId(),
                ];
            }

            return new JsonResponse($formattedReservations);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }
    }

    #[Route('/dashboard/event/check-availability', name: 'app_dashboard_check_availability', methods: ['POST'])]
    public function checkAvailability(
        Request $request,
        ReservationRepository $reservationRepository,
        RoomRepository $roomRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        try {
            $startTime = new \DateTime($data['start_time']);
            $endTime = new \DateTime($data['end_time']);
            $roomId = $data['room_id'];

            $room = $roomRepository->find($roomId);
            if (!$room) {
                return new JsonResponse(['available' => false, 'message' => 'Salle non trouvée']);
            }

            // Additional validation
            if ($startTime >= $endTime) {
                return new JsonResponse(['available' => false, 'message' => 'L\'heure de début doit être antérieure à l\'heure de fin']);
            }

            // Check if start time is in the past
            $now = new \DateTime();
            if ($startTime < $now) {
                return new JsonResponse(['available' => false, 'message' => 'Impossible de créer des réservations dans le passé']);
            }

            $isAvailable = $reservationRepository->isRoomAvailable($room, $startTime, $endTime);

            if (!$isAvailable) {
                // Get conflicting reservations for more detailed message
                $conflictingReservations = $reservationRepository->findReservationsForRoom($room, $startTime, $endTime);
                $conflictDetails = [];

                foreach ($conflictingReservations as $conflict) {
                    $conflictDetails[] = sprintf(
                        '"%s" (%s - %s)',
                        $conflict->getEvent()->getName(),
                        $conflict->getStartTime()->format('j M, G:i'),
                        $conflict->getEndTime()->format('j M, G:i')
                    );
                }

                $message = sprintf(
                    'La salle %s n\'est pas disponible pour cette période. Conflits avec : %s',
                    $room->getName(),
                    implode(', ', $conflictDetails)
                );
            } else {
                $message = sprintf(
                    'La salle %s est disponible du %s au %s',
                    $room->getName(),
                    $startTime->format('j M Y, G:i'),
                    $endTime->format('j M Y, G:i')
                );
            }

            return new JsonResponse([
                'available' => $isAvailable,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['available' => false, 'message' => 'Format de date/heure invalide']);
        }
    }
    #[Route('/dashboard/calendar', name: 'app_dashboard_calendar')]
    public function calendar(RoomRepository $roomRepository): Response
    {
        return $this->render('dashboard/calendar.html.twig', [
            'user' => $this->getUser(),
            'rooms' => $roomRepository->findAllOrderedByName(),
        ]);
    }
}