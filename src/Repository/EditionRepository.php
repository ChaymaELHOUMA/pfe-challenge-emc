<?php

namespace App\Repository;

use App\Entity\Edition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Edition>
 */
class EditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Edition::class);
    }

    /**
     * Recherche d'éditions pour le catalogue public.
     *
     * @return Paginator<Edition>
     */
    public function searchCatalogue(
        ?string $q = null,
        ?string $titre = null,
        ?string $auteur = null,
        ?string $isbn = null,
        ?string $type = null,
        ?string $langue = null,
        bool $disponibleOnly = false,
        int $page = 1,
        int $limit = 10,
    ): Paginator {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.oeuvre', 'o')
            ->addSelect('o')
            ->leftJoin('o.contributions', 'c')
            ->addSelect('c')
            ->leftJoin('c.personne', 'p')
            ->addSelect('p');

        // Recherche globale 'q'
        if ($q !== null && trim($q) !== '') {
            $term = '%' . trim($q) . '%';
            $qb->andWhere('o.titre LIKE :q OR p.nom LIKE :q OR p.prenom LIKE :q OR e.isbn LIKE :q')
                ->setParameter('q', $term);
        }

        // Recherche spécifique par titre
        if ($titre !== null && trim($titre) !== '') {
            $qb->andWhere('o.titre LIKE :titre')
                ->setParameter('titre', '%' . trim($titre) . '%');
        }

        // Recherche spécifique par auteur
        if ($auteur !== null && trim($auteur) !== '') {
            $qb->andWhere('p.nom LIKE :auteur OR p.prenom LIKE :auteur')
                ->setParameter('auteur', '%' . trim($auteur) . '%');
        }

        // Recherche spécifique par ISBN
        if ($isbn !== null && trim($isbn) !== '') {
            $qb->andWhere('e.isbn LIKE :isbn')
                ->setParameter('isbn', '%' . trim($isbn) . '%');
        }

        // Filtre par type d'oeuvre (livre, bd, dvd)
        if ($type !== null && trim($type) !== '') {
            $qb->andWhere('o.typeOeuvre = :type')
                ->setParameter('type', trim($type));
        }

        // Filtre par langue
        if ($langue !== null && trim($langue) !== '') {
            $qb->andWhere('e.langue = :langue')
                ->setParameter('langue', trim($langue));
        }

        // Filtre par disponibilité (au moins un exemplaire disponible)
        if ($disponibleOnly) {
            $qb->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\Exemplaire ex 
                WHERE ex.edition = e AND ex.statut = :dispoStatut
            )')
            ->setParameter('dispoStatut', 'disponible');
        }

        $qb->orderBy('o.titre', 'ASC')
            ->addOrderBy('e.id', 'ASC');

        $query = $qb->getQuery();
        $query->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        return new Paginator($query, fetchJoinCollection: true);
    }

    /**
     * Types d'œuvres distincts présents dans le catalogue.
     *
     * @return string[]
     */
    public function findDistinctTypes(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('DISTINCT o.typeOeuvre')
            ->join('e.oeuvre', 'o')
            ->orderBy('o.typeOeuvre', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'typeOeuvre');
    }

    /**
     * Langues distinctes présentes dans le catalogue.
     *
     * @return string[]
     */
    public function findDistinctLangues(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('DISTINCT e.langue')
            ->orderBy('e.langue', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'langue');
    }
}
