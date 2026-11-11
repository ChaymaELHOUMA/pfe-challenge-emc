<?php

namespace App\Repository;

use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exemplaire>
 */
class ExemplaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exemplaire::class);
    }

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

    /** Un exemplaire de l'édition actuellement mis de côté (les exemplaires sont interchangeables). */
    public function findOneReservePourEdition(Edition $edition): ?Exemplaire
    {
        return $this->createQueryBuilder('x')
            ->andWhere('x.edition = :e')
            ->andWhere('x.statut = :s')
            ->setParameter('e', $edition)
            ->setParameter('s', 'reserve')
            ->orderBy('x.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
