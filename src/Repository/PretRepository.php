<?php

namespace App\Repository;

use App\Entity\Adherent;
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
}
