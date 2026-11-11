<?php

namespace App\Service;

use App\Entity\Adherent;
use App\Entity\Exemplaire;
use App\Entity\Pret;
use App\Entity\Reservation;
use App\Exception\RegleMetierException;
use App\Repository\PretRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class EmpruntService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PretRepository $prets,
        private ReservationRepository $reservations,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws RegleMetierException if a borrowing rule is violated
     */
    public function emprunter(Adherent $adherent, Exemplaire $exemplaire): Pret
    {
        $now = $this->clock->now();

        $formule = $adherent->getFormuleActive($now)
            ?? throw new RegleMetierException("L'adhérent n'a pas d'abonnement en cours.");

        $reservationARetirer = $this->verifierDisponibilite($adherent, $exemplaire, $now);

        if ($this->prets->countEnCoursPourAdherent($adherent) >= $formule->getMaxPrets()) {
            throw new RegleMetierException(sprintf(
                'Limite atteinte : la formule %s autorise %d prêts simultanés.',
                $formule->getNom(),
                $formule->getMaxPrets()
            ));
        }

        $pret = (new Pret())
            ->setAdherent($adherent)
            ->setExemplaire($exemplaire)
            ->setDateDebut($now)
            ->setDateEcheance($now->modify('+' . $formule->getDureePretJours() . ' days'))
            ->setNbProlongations(0);

        $exemplaire->setStatut('emprunte');
        $reservationARetirer?->setStatut(Reservation::RETIREE);

        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    /** Returns the reservation to close if the copy was put aside for this adherent. */
    private function verifierDisponibilite(Adherent $adherent, Exemplaire $exemplaire, \DateTimeImmutable $now): ?Reservation
    {
        switch ($exemplaire->getStatut()) {
            case 'disponible':
                return null;

            case 'reserve':
                $reservation = $this->reservations->findMiseDeCote($adherent, $exemplaire->getEdition());
                if ($reservation === null) {
                    throw new RegleMetierException('Cet exemplaire est mis de côté pour un autre adhérent.');
                }
                if ($reservation->getMiseDeCoteJusqua() !== null && $reservation->getMiseDeCoteJusqua() < $now) {
                    throw new RegleMetierException('La mise de côté de cet exemplaire a expiré.');
                }
                return $reservation;

            default:
                throw new RegleMetierException(sprintf(
                    "Cet exemplaire n'est pas empruntable (statut : %s).",
                    $exemplaire->getStatut()
                ));
        }
    }
}
