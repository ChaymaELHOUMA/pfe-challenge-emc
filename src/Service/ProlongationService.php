<?php

namespace App\Service;

use App\Entity\Pret;
use App\Exception\RegleMetierException;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class ProlongationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservations,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Prolonge le prêt d'un document selon la formule en vigueur.
     *
     * @throws RegleMetierException si le prêt est clôturé, en retard, au plafond de prolongations,
     *                              ou si l'édition est réservée
     */
    public function prolonger(Pret $pret): Pret
    {
        if ($pret->getDateRetour() !== null) {
            throw new RegleMetierException('Ce prêt est déjà clôturé.');
        }

        $now = $this->clock->now();

        if ($now->setTime(0, 0) > $pret->getDateEcheance()->setTime(0, 0)) {
            throw new RegleMetierException('Ce prêt est en retard.');
        }

        $formule = $pret->getAdherent()->getFormuleActive($now);
        if ($formule === null) {
            throw new RegleMetierException("L'adhérent n'a pas d'abonnement actif.");
        }

        if ($pret->getNbProlongations() >= $formule->getMaxProlongations()) {
            throw new RegleMetierException('Le nombre maximum de prolongations est atteint.');
        }

        if ($this->reservations->countEnAttentePourEdition($pret->getExemplaire()->getEdition()) > 0) {
            throw new RegleMetierException('Ce document est réservé.');
        }

        $nouvelleEcheance = $pret->getDateEcheance()->modify('+' . $formule->getDureePretJours() . ' days');
        $pret->setDateEcheance($nouvelleEcheance);
        $pret->setNbProlongations($pret->getNbProlongations() + 1);

        $this->em->flush();

        return $pret;
    }
}
