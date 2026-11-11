<?php

namespace App\Service;

use App\Entity\Formule;
use App\Entity\Penalite;
use App\Entity\Pret;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class PenaliteService
{
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

        $penalite = (new Penalite())
            ->setPret($pret)
            ->setMontant($montant)
            ->setMotif('retard')
            ->setReglee(false)
            ->setCreeLe($now);

        $this->em->persist($penalite);
        $this->em->flush();

        return $penalite;
    }
}
