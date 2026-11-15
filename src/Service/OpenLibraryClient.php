<?php

namespace App\Service;

use App\Exception\RegleMetierException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenLibraryClient
{
    private const DEFAULT_TIMEOUT = 10.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://openlibrary.org',
    ) {
    }

    /**
     * Récupère les métadonnées d'un livre depuis Open Library par son ISBN.
     *
     * @return array{
     *     isbn: string,
     *     titre: string,
     *     editeur: string,
     *     annee: int,
     *     langue: string,
     *     support: string,
     *     auteurs: string[]
     * }|null Retourne null si le document est introuvable (404).
     *
     * @throws RegleMetierException en cas d'erreur réseau ou de réponse invalide.
     */
    public function getLivreParIsbn(string $isbn): ?array
    {
        $url = sprintf('%s/isbn/%s.json', rtrim($this->baseUrl, '/'), urlencode($isbn));

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'MediathequeApp/1.0 (PFE Challenge)',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 404) {
                return null;
            }

            if ($statusCode !== 200) {
                throw new RegleMetierException(sprintf("Open Library a retourné une erreur HTTP %d pour l'ISBN %s.", $statusCode, $isbn));
            }

            $data = $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new RegleMetierException(sprintf("Impossible de joindre Open Library (erreur réseau/timeout) : %s", $e->getMessage()), 0, $e);
        } catch (HttpExceptionInterface $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw new RegleMetierException(sprintf("Erreur HTTP lors de l'appel à Open Library : %s", $e->getMessage()), 0, $e);
        } catch (\JsonException $e) {
            throw new RegleMetierException(sprintf("Réponse JSON invalide reçue d'Open Library : %s", $e->getMessage()), 0, $e);
        } catch (\Throwable $e) {
            if ($e instanceof RegleMetierException) {
                throw $e;
            }
            throw new RegleMetierException(sprintf("Erreur lors de la récupération des données Open Library : %s", $e->getMessage()), 0, $e);
        }

        return $this->normaliserDonneesLivre($data, $isbn);
    }

    /**
     * Normalise les données brutes d'Open Library avec gestion robuste des champs incomplets.
     *
     * @param array<string, mixed> $data
     * @return array{
     *     isbn: string,
     *     titre: string,
     *     editeur: string,
     *     annee: int,
     *     langue: string,
     *     support: string,
     *     auteurs: string[]
     * }
     */
    private function normaliserDonneesLivre(array $data, string $isbn): array
    {
        // 1. Titre
        $titre = trim((string) ($data['title'] ?? 'Titre inconnu'));
        if ($titre === '') {
            $titre = 'Titre inconnu';
        }

        // 2. Éditeur
        $editeur = 'Éditeur inconnu';
        if (!empty($data['publishers']) && is_array($data['publishers'])) {
            $premierEditeur = reset($data['publishers']);
            if (is_string($premierEditeur) && trim($premierEditeur) !== '') {
                $editeur = trim($premierEditeur);
            } elseif (is_array($premierEditeur) && !empty($premierEditeur['name'])) {
                $editeur = trim((string) $premierEditeur['name']);
            }
        }

        // 3. Année de publication
        $annee = (int) date('Y');
        if (!empty($data['publish_date']) && is_string($data['publish_date'])) {
            if (preg_match('/\b(1[89]\d{2}|20\d{2})\b/', $data['publish_date'], $matches)) {
                $annee = (int) $matches[1];
            }
        }

        // 4. Langue
        $langue = 'fr';
        if (!empty($data['languages']) && is_array($data['languages'])) {
            $premiereLangue = reset($data['languages']);
            $codeLangue = is_array($premiereLangue) ? ($premiereLangue['key'] ?? '') : (string) $premiereLangue;
            $codeLangue = strtolower(basename($codeLangue));
            $langue = match ($codeLangue) {
                'fre', 'fra', 'fr' => 'fr',
                'eng', 'en' => 'en',
                'spa', 'es' => 'es',
                'ger', 'deu', 'de' => 'de',
                'ita', 'it' => 'it',
                'ara', 'ar' => 'ar',
                default => $codeLangue !== '' ? substr($codeLangue, 0, 10) : 'fr',
            };
        }

        // 5. Auteurs
        $auteurs = [];
        if (!empty($data['authors']) && is_array($data['authors'])) {
            foreach ($data['authors'] as $auteurItem) {
                if (!is_array($auteurItem)) {
                    continue;
                }
                if (!empty($auteurItem['name']) && is_string($auteurItem['name'])) {
                    $auteurs[] = trim($auteurItem['name']);
                } elseif (!empty($auteurItem['key']) && is_string($auteurItem['key'])) {
                    $nomAuteur = $this->recupererNomAuteur($auteurItem['key']);
                    if ($nomAuteur !== null && $nomAuteur !== '') {
                        $auteurs[] = $nomAuteur;
                    }
                }
            }
        }

        // Repli sur by_statement si aucun auteur trouvé
        if (empty($auteurs) && !empty($data['by_statement']) && is_string($data['by_statement'])) {
            $nom = preg_replace('/^(by|par)\s+/i', '', trim($data['by_statement']));
            if ($nom !== null && $nom !== '') {
                $auteurs[] = $nom;
            }
        }

        if (empty($auteurs)) {
            $auteurs[] = 'Auteur inconnu';
        }

        // 6. Couverture (Open Library Covers API)
        $couverture = null;
        if (!empty($data['covers']) && is_array($data['covers'])) {
            $coverId = reset($data['covers']);
            if (is_numeric($coverId) && (int) $coverId > 0) {
                $couverture = sprintf('https://covers.openlibrary.org/b/id/%d-M.jpg', (int) $coverId);
            }
        }

        return [
            'isbn' => $isbn,
            'titre' => $titre,
            'editeur' => $editeur,
            'annee' => $annee,
            'langue' => $langue,
            'support' => 'papier',
            'couverture' => $couverture,
            'auteurs' => array_values(array_unique($auteurs)),
        ];
    }

    /**
     * Tente de récupérer le nom d'un auteur via son endpoint /authors/OL....json.
     */
    private function recupererNomAuteur(string $authorKey): ?string
    {
        $url = sprintf('%s%s.json', rtrim($this->baseUrl, '/'), $authorKey);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 5.0,
                'headers' => ['Accept' => 'application/json'],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return isset($data['name']) && is_string($data['name']) ? trim($data['name']) : null;
            }
        } catch (\Throwable) {
            // En cas d'échec sur la récupération détaillée de l'auteur, on n'échoue pas tout l'import
        }

        return null;
    }
}
