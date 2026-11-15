<?php

namespace App\Repository;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Pret;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PretRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pret::class);
    }

    public function countEnCoursPourAdherent(Adherent $adherent): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.adherent = :a')
            ->andWhere('p.dateRetour IS NULL')
            ->setParameter('a', $adherent)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countEnRetardPourAdherent(Adherent $adherent, \DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.adherent = :a')
            ->andWhere('p.dateRetour IS NULL')
            ->andWhere('p.dateEcheance < :now')
            ->setParameter('a', $adherent)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Trouve le prêt en cours pour un exemplaire donné. */
    public function findEnCoursPourExemplaire(Exemplaire $exemplaire): ?Pret
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exemplaire = :e')
            ->andWhere('p.dateRetour IS NULL')
            ->setParameter('e', $exemplaire)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countEnCoursPourAdherentEtEdition(Adherent $adherent, Edition $edition): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.exemplaire', 'x')
            ->andWhere('p.adherent = :a')
            ->andWhere('x.edition = :e')
            ->andWhere('p.dateRetour IS NULL')
            ->setParameter('a', $adherent)
            ->setParameter('e', $edition)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Liste des prêts en cours pour un adhérent, triés par date d'échéance.
     *
     * @return Pret[]
     */
    public function findEnCoursPourAdherent(Adherent $adherent): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.exemplaire', 'x')
            ->addSelect('x')
            ->join('x.edition', 'e')
            ->addSelect('e')
            ->join('e.oeuvre', 'o')
            ->addSelect('o')
            ->andWhere('p.adherent = :a')
            ->andWhere('p.dateRetour IS NULL')
            ->setParameter('a', $adherent)
            ->orderBy('p.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique des prêts clôturés pour un adhérent, triés par date de retour décroissante.
     *
     * @return Pret[]
     */
    public function findHistoriquePourAdherent(Adherent $adherent): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.exemplaire', 'x')
            ->addSelect('x')
            ->join('x.edition', 'e')
            ->addSelect('e')
            ->join('e.oeuvre', 'o')
            ->addSelect('o')
            ->leftJoin('p.penalites', 'pen')
            ->addSelect('pen')
            ->andWhere('p.adherent = :a')
            ->andWhere('p.dateRetour IS NOT NULL')
            ->setParameter('a', $adherent)
            ->orderBy('p.dateRetour', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
