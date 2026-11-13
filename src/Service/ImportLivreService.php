<?php

namespace App\Service;

use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Exception\RegleMetierException;
use App\Repository\ContributionRepository;
use App\Repository\EditionRepository;
use App\Repository\OeuvreRepository;
use App\Repository\PersonneRepository;
use Doctrine\ORM\EntityManagerInterface;

class ImportLivreService
{
    public function __construct(
        private IsbnNormalizer $isbnNormalizer,
        private OpenLibraryClient $openLibraryClient,
        private EntityManagerInterface $em,
        private OeuvreRepository $oeuvreRepository,
        private EditionRepository $editionRepository,
        private PersonneRepository $personneRepository,
        private ContributionRepository $contributionRepository,
    ) {
    }

    /**
     * Importe ou met à jour de façon idempotente un livre depuis Open Library par son ISBN.
     *
     * @return Edition L'édition importée ou mise à jour.
     * @throws RegleMetierException si l'ISBN est invalide ou si le livre est introuvable.
     */
    public function importerParIsbn(string $rawIsbn): Edition
    {
        $isbn = $this->isbnNormalizer->normaliser($rawIsbn);

        $donnees = $this->openLibraryClient->getLivreParIsbn($isbn);
        if ($donnees === null) {
            throw new RegleMetierException(sprintf("Aucun document trouvé sur Open Library pour l'ISBN '%s'.", $isbn));
        }

        // 1. Récupération ou création de l'Édition et de l'Œuvre
        $edition = $this->editionRepository->findOneBy(['isbn' => $isbn]);
        if ($edition !== null) {
            $oeuvre = $edition->getOeuvre();
            $oeuvre->setTitre($donnees['titre']);
            $edition->setEditeur($donnees['editeur'])
                ->setAnnee($donnees['annee'])
                ->setLangue($donnees['langue'])
                ->setSupport($donnees['support']);
        } else {
            $oeuvre = $this->oeuvreRepository->findOneBy(['titre' => $donnees['titre']]);
            if ($oeuvre === null) {
                $oeuvre = (new Oeuvre())
                    ->setTitre($donnees['titre'])
                    ->setTypeOeuvre('livre');
                $this->em->persist($oeuvre);
            }

            $edition = (new Edition())
                ->setIsbn($isbn)
                ->setEditeur($donnees['editeur'])
                ->setAnnee($donnees['annee'])
                ->setLangue($donnees['langue'])
                ->setSupport($donnees['support'])
                ->setOeuvre($oeuvre);
            $oeuvre->addEdition($edition);
            $this->em->persist($edition);
        }

        // 2. Gestion des auteurs et contributions (idempotence)
        foreach ($donnees['auteurs'] as $nomComplet) {
            [$prenom, $nom] = $this->extrairePrenomEtNom($nomComplet);

            $personne = $this->personneRepository->findOneBy([
                'nom' => $nom,
                'prenom' => $prenom,
            ]);

            if ($personne === null) {
                $personne = (new Personne())
                    ->setNom($nom)
                    ->setPrenom($prenom);
                $this->em->persist($personne);
            }

            $contribution = $this->contributionRepository->findOneBy([
                'oeuvre' => $oeuvre,
                'personne' => $personne,
                'role' => 'auteur',
            ]);

            if ($contribution === null) {
                $contribution = (new Contribution())
                    ->setOeuvre($oeuvre)
                    ->setPersonne($personne)
                    ->setRole('auteur');
                $oeuvre->addContribution($contribution);
                $this->em->persist($contribution);
            }
        }

        $this->em->flush();

        return $edition;
    }

    /**
     * Découpe une chaîne représentant un nom complet en [prénom, nom].
     *
     * @return array{0: ?string, 1: string}
     */
    private function extrairePrenomEtNom(string $nomComplet): array
    {
        $nomComplet = trim($nomComplet);
        $morceaux = preg_split('/\s+/', $nomComplet);

        if (count($morceaux) <= 1) {
            return [null, $nomComplet !== '' ? $nomComplet : 'Inconnu'];
        }

        $nom = array_pop($morceaux);
        $prenom = implode(' ', $morceaux);

        return [$prenom, $nom];
    }
}
