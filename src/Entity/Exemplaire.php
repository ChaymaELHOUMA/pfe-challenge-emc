<?php

namespace App\Entity;

use App\Repository\ExemplaireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExemplaireRepository::class)]
class Exemplaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $codeBarres = null;

    #[ORM\Column(length: 50)]
    private ?string $etat = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'exemplaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Edition $edition = null;

    #[ORM\ManyToOne(inversedBy: 'exemplaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $site = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeBarres(): ?string
    {
        return $this->codeBarres;
    }

    public function setCodeBarres(string $codeBarres): static
    {
        $this->codeBarres = $codeBarres;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getEdition(): ?Edition
    {
        return $this->edition;
    }

    public function setEdition(?Edition $edition): static
    {
        $this->edition = $edition;

        return $this;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s [%s]', (string) $this->codeBarres, (string) $this->statut);
    }
}
