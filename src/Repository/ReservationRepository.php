<?php

namespace App\Repository;

use App\Entity\Edition;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /** Premier adhérent de la file (le plus ancien) pour une édition. */
    public function premierEnAttente(Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut = :s')
            ->setParameter('e', $edition)
            ->setParameter('s', Reservation::EN_ATTENTE)
            ->orderBy('r.creeLe', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
}

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

    /** Premier adhérent de la file (le plus ancien) pour une édition. */
    public function premierEnAttente(Edition $edition): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.edition = :e')
            ->andWhere('r.statut = :s')
            ->setParameter('e', $edition)
            ->setParameter('s', Reservation::EN_ATTENTE)
            ->orderBy('r.creeLe', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
