<?php

namespace App\Service;

use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Exception\RegleMetierException;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;

class ImportLotService
{
    public function __construct(
        private ImportLivreService $importLivreService,
        private SiteRepository $siteRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Importe un lot de livres à partir d'un fichier CSV.
     *
     * @throws RegleMetierException si le fichier est introuvable ou si le format est invalide.
     */
    public function importerFichier(string $cheminFichier, bool $dryRun = false): ImportLotRapport
    {
        if (!file_exists($cheminFichier) || !is_readable($cheminFichier)) {
            throw new RegleMetierException(sprintf("Le fichier CSV '%s' est introuvable ou illisible.", $cheminFichier));
        }

        $handle = fopen($cheminFichier, 'r');
        if ($handle === false) {
            throw new RegleMetierException(sprintf("Impossible d'ouvrir le fichier CSV '%s'.", $cheminFichier));
        }

        $rapport = new ImportLotRapport();
        $rapport->dryRun = $dryRun;
        $debut = microtime(true);

        $conn = $this->em->getConnection();
        if ($dryRun) {
            $conn->beginTransaction();
        }

        try {
            // 1. Détection du séparateur et lecture des en-têtes
            $premiereLigne = fgets($handle);
            if ($premiereLigne === false || trim($premiereLigne) === '') {
                throw new RegleMetierException('Le fichier CSV est vide.');
            }

            $separateur = str_contains($premiereLigne, ';') ? ';' : ',';
            $enTetes = str_getcsv($premiereLigne, $separateur);
            $enTetesNormalisees = array_map(fn($h) => strtolower(trim((string) $h)), $enTetes);

            $isbnIndex = array_search('isbn', $enTetesNormalisees, true);
            if ($isbnIndex === false) {
                throw new RegleMetierException("Le fichier CSV doit obligatoirement comporter une colonne 'isbn'.");
            }

            $siteIndex = array_search('site', $enTetesNormalisees, true);
            $quantiteIndex = array_search('quantite', $enTetesNormalisees, true);
            if ($quantiteIndex === false) {
                $quantiteIndex = array_search('quantité', $enTetesNormalisees, true);
            }

            // 2. Traitement ligne par ligne avec résilience
            $ligneNum = 1;
            while (($row = fgetcsv($handle, 0, $separateur)) !== false) {
                $ligneNum++;

                // Ignorer les lignes vides
                if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                $rapport->totalLignes++;
                $rawIsbn = trim((string) ($row[$isbnIndex] ?? ''));

                if ($rawIsbn === '') {
                    $rapport->nbEchecs++;
                    $rapport->echecs[] = [
                        'ligne' => $ligneNum,
                        'isbn' => '(vide)',
                        'erreur' => 'ISBN manquant sur la ligne.',
                    ];
                    continue;
                }

                try {
                    $edition = $this->importLivreService->importerParIsbn($rawIsbn);

                    // Création optionnelle d'exemplaires si les colonnes sont fournies
                    if ($siteIndex !== false && $quantiteIndex !== false) {
                        $siteNom = trim((string) ($row[$siteIndex] ?? ''));
                        $quantite = (int) ($row[$quantiteIndex] ?? 0);
                        if ($siteNom !== '' && $quantite > 0) {
                            $this->creerExemplaires($edition, $siteNom, $quantite);
                        }
                    }

                    $rapport->nbSucces++;
                } catch (\Throwable $e) {
                    $rapport->nbEchecs++;
                    $rapport->echecs[] = [
                        'ligne' => $ligneNum,
                        'isbn' => $rawIsbn,
                        'erreur' => $e->getMessage(),
                    ];
                }
            }
        } finally {
            fclose($handle);

            if ($dryRun && $conn->isTransactionActive()) {
                $conn->rollBack();
            }
        }

        $rapport->dureeSecondes = round(microtime(true) - $debut, 3);

        return $rapport;
    }

    private function creerExemplaires(Edition $edition, string $siteNom, int $quantite): void
    {
        $site = $this->siteRepository->findOneBy(['nom' => $siteNom]);
        if ($site === null && is_numeric($siteNom)) {
            $site = $this->siteRepository->find((int) $siteNom);
        }

        if ($site === null) {
            throw new RegleMetierException(sprintf("Le site '%s' est introuvable.", $siteNom));
        }

        for ($i = 0; $i < $quantite; $i++) {
            $codeBarres = sprintf('EX-%s-%d', strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)), $i + 1);
            $exemplaire = (new Exemplaire())
                ->setEdition($edition)
                ->setSite($site)
                ->setCodeBarres($codeBarres)
                ->setEtat('bon')
                ->setStatut('disponible');

            $edition->addExemplaire($exemplaire);
            $this->em->persist($exemplaire);
        }

        $this->em->flush();
    }
}
