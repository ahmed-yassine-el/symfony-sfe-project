<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventControllerApi extends AbstractController
{
    #[Route('/api/events', name: 'api_events')]
public function index(EventRepository $eventRepository): \Symfony\Component\HttpFoundation\JsonResponse
    {
    $events = $eventRepository->findAll();
    $data = [];

    foreach ($events as $event) {
        $data[] = [
            'title' => $event->getName() . ' (' . $event->getRoom() . ')',
            'start' => $event->getStartTime()->format('Y-m-d\TH:i:s'),
            'end' => $event->getEndTime()->format('Y-m-d\TH:i:s'),
        ];
    }

    return $this->json($data);
}
}
