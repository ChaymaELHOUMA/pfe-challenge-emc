<?php

namespace App\Repository;

use App\Entity\Adherent;
use App\Entity\Penalite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PenaliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Penalite::class);
    }

    /** Total des pénalités non réglées pour un adhérent. */
    public function totalDuesPourAdherent(Adherent $adherent): string
    {
        $total = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0)')
            ->join('p.pret', 'pr')
            ->andWhere('pr.adherent = :a')
            ->andWhere('p.reglee = :r')
            ->setParameter('a', $adherent)
            ->setParameter('r', false)
            ->getQuery()
            ->getSingleScalarResult();

        return number_format((float) $total, 2, '.', '');
    }
}
