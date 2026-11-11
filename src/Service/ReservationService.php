<?php

namespace App\Service;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Reservation;
use App\Exception\RegleMetierException;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

class ReservationService
{
    public const MAX_RESERVATIONS_ACTIVES = 5;

    /** Statuts d'un exemplaire encore en circulation (ni perdu, ni retiré). */
    private const STATUTS_EN_CIRCULATION = ['disponible', 'emprunte', 'reserve'];

    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservations,
        private PretRepository $prets,
        private ExemplaireRepository $exemplaires,
        private MiseDeCoteService $miseDeCote,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws RegleMetierException si une règle de réservation n'est pas respectée
     */
    public function reserver(Adherent $adherent, Edition $edition): Reservation
    {
        $now = $this->clock->now();

        if ($adherent->getFormuleActive($now) === null) {
            throw new RegleMetierException("L'adhérent n'a pas d'abonnement en cours.");
        }

        if ($this->reservations->findActive($adherent, $edition) !== null) {
            throw new RegleMetierException('Cette édition est déjà réservée par cet adhérent.');
        }

        if ($this->prets->countEnCoursPourAdherentEtEdition($adherent, $edition) > 0) {
            throw new RegleMetierException('Cet adhérent a déjà cette édition en prêt.');
        }

        if ($this->reservations->countActivesPourAdherent($adherent) >= self::MAX_RESERVATIONS_ACTIVES) {
            throw new RegleMetierException(sprintf(
                'Maximum atteint : %d réservations actives autorisées.',
                self::MAX_RESERVATIONS_ACTIVES
            ));
        }

        if ($this->exemplaires->countParEdition($edition, self::STATUTS_EN_CIRCULATION) === 0) {
            throw new RegleMetierException("Aucun exemplaire de cette édition n'est en circulation.");
        }

        // Disponible chez l'adhérent : on l'invite à l'emprunter. Disponible seulement dans
        // l'autre site : la réservation reste possible (voir docs/DECISIONS.md, question 2).
        if ($this->exemplaires->countParEdition($edition, ['disponible'], $adherent->getSiteRattachement()) > 0) {
            throw new RegleMetierException(
                "Un exemplaire est disponible dans la médiathèque de l'adhérent : il peut l'emprunter directement."
            );
        }

        $reservation = (new Reservation())
            ->setAdherent($adherent)
            ->setEdition($edition)
            ->setCreeLe($now)
            ->setStatut(Reservation::EN_ATTENTE);

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    /**
     * Annule une réservation en attente ou mise de côté.
     * Si le document était mis de côté, il est proposé au suivant de la file ou remis en rayon.
     *
     * @throws RegleMetierException
     */
    public function annuler(Reservation $reservation): void
    {
        $statut = $reservation->getStatut();

        if (!in_array($statut, [Reservation::EN_ATTENTE, Reservation::DISPONIBLE], true)) {
            throw new RegleMetierException('Seule une réservation en attente ou mise de côté peut être annulée.');
        }

        $reservation->setStatut(Reservation::ANNULEE);

        if ($statut === Reservation::DISPONIBLE) {
            $this->miseDeCote->liberer($reservation->getEdition());
        }

        $this->em->flush();
    }

    public function positionDansLaFile(Reservation $reservation): int
    {
        return $this->reservations->positionDansLaFile($reservation);
    }
}
