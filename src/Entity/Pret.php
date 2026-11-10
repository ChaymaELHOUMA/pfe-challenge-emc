<?php

namespace App\Entity;

use App\Repository\PretRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PretRepository::class)]
class Pret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateRetour = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbProlongations = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Adherent $adherent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exemplaire $exemplaire = null;

    /** @var Collection<int, Penalite> */
    #[ORM\OneToMany(targetEntity: Penalite::class, mappedBy: 'pret')]
    private Collection $penalites;

    public function __construct()
    {
        $this->penalites = new ArrayCollection();
        $this->dateDebut = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('Prêt #%d — %s', $this->id ?? 0, $this->exemplaire);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeImmutable $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getDateRetour(): ?\DateTimeImmutable
    {
        return $this->dateRetour;
    }

    public function setDateRetour(?\DateTimeImmutable $dateRetour): static
    {
        $this->dateRetour = $dateRetour;
        return $this;
    }

    public function getNbProlongations(): int
    {
        return $this->nbProlongations;
    }

    public function setNbProlongations(int $nbProlongations): static
    {
        $this->nbProlongations = $nbProlongations;
        return $this;
    }

    public function getAdherent(): ?Adherent
    {
        return $this->adherent;
    }

    public function setAdherent(?Adherent $adherent): static
    {
        $this->adherent = $adherent;
        return $this;
    }

    public function getExemplaire(): ?Exemplaire
    {
        return $this->exemplaire;
    }

    public function setExemplaire(?Exemplaire $exemplaire): static
    {
        $this->exemplaire = $exemplaire;
        return $this;
    }

    /** @return Collection<int, Penalite> */
    public function getPenalites(): Collection
    {
        return $this->penalites;
    }

    public function estEnCours(): bool
    {
        return $this->dateRetour === null;
    }
}
