<?php

namespace App\Entity;

use App\Repository\OeuvreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OeuvreRepository::class)]
class Oeuvre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 100)]
    private ?string $typeOeuvre = null;

    /**
     * @var Collection<int, Contribution>
     */
    #[ORM\OneToMany(targetEntity: Contribution::class, mappedBy: 'oeuvre', orphanRemoval: true)]
    private Collection $contributions;

    /**
     * @var Collection<int, Edition>
     */
    #[ORM\OneToMany(targetEntity: Edition::class, mappedBy: 'oeuvre', orphanRemoval: true)]
    private Collection $editions;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
        $this->editions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getTypeOeuvre(): ?string
    {
        return $this->typeOeuvre;
    }

    public function setTypeOeuvre(string $typeOeuvre): static
    {
        $this->typeOeuvre = $typeOeuvre;

        return $this;
    }

    /**
     * @return Collection<int, Contribution>
     */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }

    public function addContribution(Contribution $contribution): static
    {
        if (!$this->contributions->contains($contribution)) {
            $this->contributions->add($contribution);
            $contribution->setOeuvre($this);
        }

        return $this;
    }

    public function removeContribution(Contribution $contribution): static
    {
        if ($this->contributions->removeElement($contribution)) {
            if ($contribution->getOeuvre() === $this) {
                $contribution->setOeuvre(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Edition>
     */
    public function getEditions(): Collection
    {
        return $this->editions;
    }

    public function addEdition(Edition $edition): static
    {
        if (!$this->editions->contains($edition)) {
            $this->editions->add($edition);
            $edition->setOeuvre($this);
        }

        return $this;
    }

    public function removeEdition(Edition $edition): static
    {
        if ($this->editions->removeElement($edition)) {
            if ($edition->getOeuvre() === $this) {
                $edition->setOeuvre(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->titre;
    }
}
