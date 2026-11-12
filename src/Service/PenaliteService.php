<?php

namespace App\Service;

use App\Entity\Formule;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Exception\RegleMetierException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class PenaliteService
{
    public const MONTANT_PERDU = '25.00';
    public const MONTANT_ABIME = '5.00';

    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function appliquerRetard(Pret $pret, Formule $formule): ?Penalite
    {
        $now = $this->clock->now();
        $dateEcheance = $pret->getDateEcheance()->setTime(0, 0);
        $dateRetour = $now->setTime(0, 0);

        if ($dateRetour <= $dateEcheance) {
            return null;
        }

        $joursRetard = $dateEcheance->diff($dateRetour)->days;
        if ($joursRetard <= 0) {
            return null;
        }

        $taux = (float) $formule->getPenaliteParJour();
        $montant = number_format($joursRetard * $taux, 2, '.', '');

        return $this->creer($pret, $montant, 'retard');
    }

    public function declarerPerdu(Pret $pret): Penalite
    {
        $this->verifierPretEnCours($pret);

        $pret->setDateRetour($this->clock->now());
        $pret->getExemplaire()->setStatut('perdu');

        return $this->creer($pret, self::MONTANT_PERDU, 'perdu');
    }

    public function declarerAbime(Pret $pret): Penalite
    {
        $this->verifierPretEnCours($pret);

        $pret->setDateRetour($this->clock->now());
        $pret->getExemplaire()->setEtat('abime');
        $pret->getExemplaire()->setStatut('retire');

        return $this->creer($pret, self::MONTANT_ABIME, 'abime');
    }

    private function verifierPretEnCours(Pret $pret): void
    {
        if ($pret->getDateRetour() !== null) {
            throw new RegleMetierException('Ce prêt est déjà clôturé.');
        }
    }

    private function creer(Pret $pret, string $montant, string $motif): Penalite
    {
        $penalite = (new Penalite())
            ->setPret($pret)
            ->setMontant($montant)
            ->setMotif($motif)
            ->setReglee(false)
            ->setCreeLe($this->clock->now());

        $this->em->persist($penalite);
        $this->em->flush();

        return $penalite;
    }
}
