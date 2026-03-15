<?php
// src/Entity/Room.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
class Room
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;

#[ORM\Column(length: 255)]
private ?string $name = null;

#[ORM\OneToMany(mappedBy: 'room', targetEntity: Reservation::class)]
private Collection $reservations;

public function __construct()
{
$this->reservations = new ArrayCollection();
}

// Getters/Setters
public function getId(): ?int { return $this->id; }
public function getName(): ?string { return $this->name; }
public function setName(string $name): self { $this->name = $name; return $this; }
public function getReservations(): Collection { return $this->reservations; }
public function addReservation(Reservation $reservation): self { $reservation->setRoom($this); $this->reservations[] = $reservation; return $this; }
}