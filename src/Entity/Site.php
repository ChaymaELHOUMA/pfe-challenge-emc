<?php

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    /**
     * @var Collection<int, Exemplaire>
     */
    #[ORM\OneToMany(targetEntity: Exemplaire::class, mappedBy: 'site')]
    private Collection $exemplaires;

    public function __construct()
    {
        $this->exemplaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    /**
     * @return Collection<int, Exemplaire>
     */
    public function getExemplaires(): Collection
    {
        return $this->exemplaires;
    }

    public function addExemplaire(Exemplaire $exemplaire): static
    {
        if (!$this->exemplaires->contains($exemplaire)) {
            $this->exemplaires->add($exemplaire);
            $exemplaire->setSite($this);
        }

        return $this;
    }

    public function removeExemplaire(Exemplaire $exemplaire): static
    {
        if ($this->exemplaires->removeElement($exemplaire)) {
            if ($exemplaire->getSite() === $this) {
                $exemplaire->setSite(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
