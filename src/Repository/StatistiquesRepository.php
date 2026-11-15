<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

/**
 * Requêtes statistiques pour le tableau de bord du back-office.
 * Toutes les requêtes sont paramétrées et ne renvoient que des scalaires ou tableaux simples.
 */
class StatistiquesRepository
{
    public function __construct(private Connection $connection) {}

    /**
     * Nombre de prêts en cours par site, trié par nombre décroissant.
     *
     * @return array<array{site: string, nb: int}>
     */
    public function pretsEnCoursBySite(): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT s.nom AS site, COUNT(p.id) AS nb
             FROM pret p
             JOIN exemplaire x ON x.id = p.exemplaire_id
             JOIN site s       ON s.id = x.site_id
             WHERE p.date_retour IS NULL
             GROUP BY s.id, s.nom
             ORDER BY nb DESC'
        )->fetchAllAssociative();

        return array_map(
            fn(array $r) => ['site' => $r['site'], 'nb' => (int) $r['nb']],
            $rows
        );
    }

    /**
     * Nombre de prêts en retard (date_echeance dépassée, non rendu).
     */
    public function nbPretsEnRetard(\DateTimeImmutable $aujourd_hui): int
    {
        return (int) $this->connection->executeQuery(
            'SELECT COUNT(id) FROM pret WHERE date_retour IS NULL AND date_echeance < :today',
            ['today' => $aujourd_hui->format('Y-m-d')]
        )->fetchOne();
    }

    /**
     * Les 10 éditions les plus empruntées (tous prêts confondus).
     *
     * @return array<array{titre: string, editeur: string, annee: int, nb: int}>
     */
    public function topDixDocuments(): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT o.titre, e.editeur, e.annee, COUNT(p.id) AS nb
             FROM pret p
             JOIN exemplaire x ON x.id = p.exemplaire_id
             JOIN edition e    ON e.id = x.edition_id
             JOIN oeuvre o     ON o.id = e.oeuvre_id
             GROUP BY e.id, o.titre, e.editeur, e.annee
             ORDER BY nb DESC
             LIMIT 10'
        )->fetchAllAssociative();

        return array_map(
            fn(array $r) => [
                'titre'   => $r['titre'],
                'editeur' => $r['editeur'],
                'annee'   => (int) $r['annee'],
                'nb'      => (int) $r['nb'],
            ],
            $rows
        );
    }

    /**
     * Total des pénalités non réglées (en MAD, retourné sous forme de chaîne décimale).
     */
    public function totalPenalitesImpayees(): string
    {
        $total = $this->connection->executeQuery(
            'SELECT COALESCE(SUM(montant), 0) FROM penalite WHERE reglee = 0'
        )->fetchOne();

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Réservations en attente par édition, triées par nombre décroissant (top 10).
     *
     * @return array<array{titre: string, editeur: string, annee: int, nb: int}>
     */
    public function reservationsEnAttenteByEdition(): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT o.titre, e.editeur, e.annee, COUNT(r.id) AS nb
             FROM reservation r
             JOIN edition e ON e.id = r.edition_id
             JOIN oeuvre o  ON o.id = e.oeuvre_id
             WHERE r.statut = :statut
             GROUP BY e.id, o.titre, e.editeur, e.annee
             ORDER BY nb DESC
             LIMIT 10',
            ['statut' => 'en_attente']
        )->fetchAllAssociative();

        return array_map(
            fn(array $r) => [
                'titre'   => $r['titre'],
                'editeur' => $r['editeur'],
                'annee'   => (int) $r['annee'],
                'nb'      => (int) $r['nb'],
            ],
            $rows
        );
    }

    /**
     * Nombre total de prêts en cours.
     */
    public function nbPretsEnCours(): int
    {
        return (int) $this->connection->executeQuery(
            'SELECT COUNT(id) FROM pret WHERE date_retour IS NULL'
        )->fetchOne();
    }

    /**
     * Nombre total de réservations en attente.
     */
    public function nbReservationsEnAttente(): int
    {
        return (int) $this->connection->executeQuery(
            "SELECT COUNT(id) FROM reservation WHERE statut = 'en_attente'"
        )->fetchOne();
    }

    /**
     * Taux de rotation global de la collection :
     * ratio entre le nombre total d'emprunts enregistrés et le volume d'exemplaires en stock.
     */
    public function tauxRotation(): float
    {
        $totalPrets = (int) $this->connection->executeQuery(
            'SELECT COUNT(id) FROM pret'
        )->fetchOne();

        $totalExemplaires = (int) $this->connection->executeQuery(
            'SELECT COUNT(id) FROM exemplaire'
        )->fetchOne();

        if ($totalExemplaires === 0) {
            return 0.0;
        }

        return round($totalPrets / $totalExemplaires, 2);
    }

    /**
     * Documents / éditions n'ayant jamais fait l'objet d'aucun prêt (top 10).
     *
     * @return array<array{titre: string, editeur: string, annee: int, isbn: string}>
     */
    public function documentsJamaisEmpruntes(): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT o.titre, e.editeur, e.annee, e.isbn
             FROM edition e
             JOIN oeuvre o ON o.id = e.oeuvre_id
             WHERE e.id NOT IN (
                 SELECT DISTINCT x.edition_id
                 FROM exemplaire x
                 JOIN pret p ON p.exemplaire_id = x.id
             )
             ORDER BY o.titre ASC
             LIMIT 10'
        )->fetchAllAssociative();

        return array_map(
            fn(array $r) => [
                'titre'   => $r['titre'],
                'editeur' => $r['editeur'],
                'annee'   => (int) $r['annee'],
                'isbn'    => (string) $r['isbn'],
            ],
            $rows
        );
    }
}
