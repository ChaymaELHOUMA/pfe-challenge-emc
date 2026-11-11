<?php

namespace App\Repository;

use App\Entity\Exemplaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exemplaire>
 */
use App\Entity\Edition;
use App\Entity\Site;

    /**
     * @param string[] $statuts
     */
    public function countParEdition(Edition $edition, array $statuts, ?Site $site = null): int
    {
        $qb = $this->createQueryBuilder('x')
            ->select('COUNT(x.id)')
            ->andWhere('x.edition = :e')
            ->andWhere('x.statut IN (:s)')
            ->setParameter('e', $edition)
            ->setParameter('s', $statuts);

        if ($site !== null) {
            $qb->andWhere('x.site = :site')->setParameter('site', $site);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exemplaire::class);
    }
}
