<?php

namespace App\Entity;

use App\Repository\PenaliteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PenaliteRepository::class)]
class Penalite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $montant = null;

    /** retard, perdu ou abime */
    #[ORM\Column(length: 30)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $creeLe = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $reglee = false;

    #[ORM\ManyToOne(inversedBy: 'penalites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pret $pret = null;

    public function getId(): ?int { return $this->id; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $m): static { $this->montant = $m; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(string $m): static { $this->motif = $m; return $this; }

    public function getCreeLe(): ?\DateTimeImmutable { return $this->creeLe; }
    public function setCreeLe(\DateTimeImmutable $d): static { $this->creeLe = $d; return $this; }

    public function isReglee(): bool { return $this->reglee; }
    public function setReglee(bool $r): static { $this->reglee = $r; return $this; }

    public function getPret(): ?Pret { return $this->pret; }
    public function setPret(?Pret $p): static { $this->pret = $p; return $this; }
}
