<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    public const EN_ATTENTE = 'en_attente';
    public const DISPONIBLE = 'disponible';
    public const RETIREE = 'retiree';
    public const ANNULEE = 'annulee';
    public const EXPIREE = 'expiree';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $creeLe = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::EN_ATTENTE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $miseDeCoteJusqua = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Adherent $adherent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Edition $edition = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCreeLe(): ?\DateTimeImmutable { return $this->creeLe; }
    public function setCreeLe(\DateTimeImmutable $d): static { $this->creeLe = $d; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): static { $this->statut = $s; return $this; }

    public function getMiseDeCoteJusqua(): ?\DateTimeImmutable { return $this->miseDeCoteJusqua; }
    public function setMiseDeCoteJusqua(?\DateTimeImmutable $d): static { $this->miseDeCoteJusqua = $d; return $this; }

    public function getAdherent(): ?Adherent { return $this->adherent; }
    public function setAdherent(?Adherent $a): static { $this->adherent = $a; return $this; }

    public function getEdition(): ?Edition { return $this->edition; }
    public function setEdition(?Edition $e): static { $this->edition = $e; return $this; }
}
