<?php
// src/Entity/Event.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;
// Added cascade: ['remove'] to automatically delete reservations when event is deleted
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Reservation::class, orphanRemoval: true, cascade: ['remove'])]
    private Collection $reservations;


    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }
    public function getReservations(): Collection { return $this->reservations; }
    public function addReservation(Reservation $reservation): self { $reservation->setEvent($this); $this->reservations[] = $reservation; return $this; }
}