<?php

namespace App\Service;

use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Reservation;
use App\Repository\ExemplaireRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class MiseDeCoteService
{
    /** Durée de la mise de côté, en jours calendaires (jusqu'à 23h59:59 du dernier jour). */
    public const DELAI_JOURS = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservations,
        private ExemplaireRepository $exemplaires,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Décide du sort d'un exemplaire qui vient d'être rendu ou libéré : mis de côté pour le premier
     * de la file, sinon remis en rayon. N'écrit rien en base : l'appelant fait le flush.
     */
    public function affecter(Exemplaire $exemplaire): ?Reservation
    {
        $now = $this->clock->now();

        foreach ($this->reservations->findEnAttente($exemplaire->getEdition()) as $reservation) {
            // Un adhérent dont l'abonnement a expiré perd son tour.
            if ($reservation->getAdherent()->getFormuleActive($now) === null) {
                $reservation->setStatut(Reservation::EXPIREE);
                continue;
            }

            $reservation
                ->setStatut(Reservation::DISPONIBLE)
                ->setMiseDeCoteJusqua(
                    $now->modify('+' . self::DELAI_JOURS . ' days')->setTime(23, 59, 59)
                );
            $exemplaire->setStatut('reserve');

            return $reservation;
        }

        $exemplaire->setStatut('disponible');

        return null;
    }

    /**
     * Libère un exemplaire mis de côté (annulation ou expiration) : il est proposé au suivant
     * de la file, ou remis en rayon. N'écrit rien en base.
     */
    public function liberer(Edition $edition): ?Reservation
    {
        $exemplaire = $this->exemplaires->findOneReservePourEdition($edition);

        return $exemplaire === null ? null : $this->affecter($exemplaire);
    }

    /**
     * Expire les mises de côté dont la date limite est dépassée.
     *
     * @return int nombre de réservations expirées
     */
    public function expirerDepassees(): int
    {
        $expirees = $this->reservations->findMisesDeCoteExpirees($this->clock->now());

        foreach ($expirees as $reservation) {
            $reservation->setStatut(Reservation::EXPIREE);
            $this->liberer($reservation->getEdition());
            // Un flush par réservation : la suivante doit voir l'état à jour de la base.
            $this->em->flush();
        }

        return count($expirees);
    }
}
