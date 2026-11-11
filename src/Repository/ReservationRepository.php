<?php

namespace App\Repository;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function countEnAttentePourEdition(Edition $edition): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut = :s')
            ->setParameter('e', $edition)
            ->setParameter('s', Reservation::EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Réservation dont le document est mis de côté pour cet adhérent sur cette édition. */
    public function findMiseDeCote(Adherent $adherent, Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.adherent = :a')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut = :s')
            ->setParameter('a', $adherent)
            ->setParameter('e', $edition)
            ->setParameter('s', Reservation::DISPONIBLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Réservation active (en attente ou mise de côté) de cet adhérent pour cette édition. */
    public function findActive(Adherent $adherent, Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.adherent = :a')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut IN (:s)')
            ->setParameter('a', $adherent)
            ->setParameter('e', $edition)
            ->setParameter('s', [Reservation::EN_ATTENTE, Reservation::DISPONIBLE])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Nombre de réservations actives (EN_ATTENTE ou DISPONIBLE) pour un adhérent.
     */
    public function countActivesPourAdherent(Adherent $adherent): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.adherent = :a')
            ->andWhere('r.statut IN (:s)')
            ->setParameter('a', $adherent)
            ->setParameter('s', [Reservation::EN_ATTENTE, Reservation::DISPONIBLE])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Position d'une réservation EN_ATTENTE dans la file d'attente d'une édition.
     *
     * 1 = premier, 2 = deuxième, …
     */
    public function positionDansLaFile(Reservation $reservation): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut = :s')
            ->andWhere('r.creeLe <= :date')
            ->setParameter('e', $reservation->getEdition())
            ->setParameter('s', Reservation::EN_ATTENTE)
            ->setParameter('date', $reservation->getCreeLe())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * File d'attente d'une édition, dans l'ordre (le plus ancien d'abord).
     *
     * @return Reservation[]
     */
    public function findEnAttente(Edition $edition): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut = :s')
            ->setParameter('e', $edition)
            ->setParameter('s', Reservation::EN_ATTENTE)
            ->orderBy('r.creeLe', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mises de côté dont la date limite est dépassée.
     *
     * @return Reservation[]
     */
    public function findMisesDeCoteExpirees(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statut = :s')
            ->andWhere('r.miseDeCoteJusqua < :now')
            ->setParameter('s', Reservation::DISPONIBLE)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->orderBy('r.miseDeCoteJusqua', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
