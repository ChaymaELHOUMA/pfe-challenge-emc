<?php

namespace App\Entity;

use App\Repository\EditionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EditionRepository::class)]
class Edition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $isbn = null;

    #[ORM\Column(length: 255)]
    private ?string $editeur = null;

    #[ORM\Column]
    private ?int $annee = null;

    #[ORM\Column(length: 50)]
    private ?string $langue = null;

    #[ORM\Column(length: 50)]
    private ?string $support = null;

    #[ORM\ManyToOne(inversedBy: 'editions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Oeuvre $oeuvre = null;

    /**
     * @var Collection<int, Exemplaire>
     */
    #[ORM\OneToMany(targetEntity: Exemplaire::class, mappedBy: 'edition', orphanRemoval: true)]
    private Collection $exemplaires;

    public function __construct()
    {
        $this->exemplaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getEditeur(): ?string
    {
        return $this->editeur;
    }

    public function setEditeur(string $editeur): static
    {
        $this->editeur = $editeur;

        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }

    public function getSupport(): ?string
    {
        return $this->support;
    }

    public function setSupport(string $support): static
    {
        $this->support = $support;

        return $this;
    }

    public function getOeuvre(): ?Oeuvre
    {
        return $this->oeuvre;
    }

    public function setOeuvre(?Oeuvre $oeuvre): static
    {
        $this->oeuvre = $oeuvre;

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
            $exemplaire->setEdition($this);
        }

        return $this;
    }

    public function removeExemplaire(Exemplaire $exemplaire): static
    {
        if ($this->exemplaires->removeElement($exemplaire)) {
            // set the owning side to null (unless already changed)
            if ($exemplaire->getEdition() === $this) {
                $exemplaire->setEdition(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        $titre = $this->oeuvre ? $this->oeuvre->getTitre() : 'Sans titre';
        return sprintf('%s - %s (%d)', $titre, (string) $this->editeur, (int) $this->annee);
    }
}
