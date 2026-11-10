<?php

namespace App\DataFixtures;

use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Entity\Site;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CatalogueFixtures extends Fixture
{
    /** @var array<string, Personne> */
    private array $personnes = [];

    public function load(ObjectManager $manager): void
    {
        // --- Sites ---
        $sites = [];
        foreach ([
            ['Médiathèque Centrale', '12 avenue Hassan II, Agadir'],
            ['Médiathèque Annexe Founty', '5 rue des Orangers, Agadir'],
        ] as $i => [$nom, $adresse]) {
            $site = (new Site())
                ->setNom($nom)
                ->setAdresse($adresse);
            $manager->persist($site);
            $this->addReference('site_' . ($i + 1), $site);
            $sites[] = $site;
        }

        // catalogue data
        $catalogue = [
            ['Le Petit Prince', 'livre', 'Saint-Exupéry', 'Antoine'],
            ["L'Étranger", 'livre', 'Camus', 'Albert'],
            ['La Peste', 'livre', 'Camus', 'Albert'],
            ['Les Misérables', 'livre', 'Hugo', 'Victor'],
            ['Notre-Dame de Paris', 'livre', 'Hugo', 'Victor'],
            ['Germinal', 'livre', 'Zola', 'Émile'],
            ['Madame Bovary', 'livre', 'Flaubert', 'Gustave'],
            ['Le Rouge et le Noir', 'livre', 'Stendhal', null],
            ['Le Comte de Monte-Cristo', 'livre', 'Dumas', 'Alexandre'],
            ['Les Trois Mousquetaires', 'livre', 'Dumas', 'Alexandre'],
            ['Candide', 'livre', 'Voltaire', null],
            ['Bel-Ami', 'livre', 'Maupassant', 'Guy de'],
            ['Le Père Goriot', 'livre', 'Balzac', 'Honoré de'],
            ['Les Fleurs du mal', 'livre', 'Baudelaire', 'Charles'],
            ['Vingt mille lieues sous les mers', 'livre', 'Verne', 'Jules'],
            ['Le Tour du monde en quatre-vingts jours', 'livre', 'Verne', 'Jules'],
            ["Alice au pays des merveilles", 'livre', 'Carroll', 'Lewis', [['traducteur', 'Parisot', 'Henri']]],
            ['Crime et Châtiment', 'livre', 'Dostoïevski', 'Fiodor', [['traducteur', 'Markowicz', 'André']]],
            ['1984', 'livre', 'Orwell', 'George', [['traducteur', 'Audiberti', 'Amélie']]],
            ['Le Meilleur des mondes', 'livre', 'Huxley', 'Aldous', [['traducteur', 'Jaloux', 'Jules']]],
            ['Fahrenheit 451', 'livre', 'Bradbury', 'Ray', [['traducteur', 'Henri', 'Jacques']]],
            ['Persépolis', 'bd', 'Satrapi', 'Marjane'],
            ['Tintin au Tibet', 'bd', 'Hergé', null],
            ['Astérix le Gaulois', 'bd', 'Goscinny', 'René', [['illustrateur', 'Uderzo', 'Albert']]],
            ['Maus', 'bd', 'Spiegelman', 'Art'],
            ["L'Arabe du futur", 'bd', 'Sattouf', 'Riad'],
            ['Spirou et Fantasio', 'bd', 'Franquin', 'André'],
            ['Le Fabuleux Destin d\'Amélie Poulain', 'dvd', 'Jeunet', 'Jean-Pierre'],
            ['La Haine', 'dvd', 'Kassovitz', 'Mathieu'],
            ['Intouchables', 'dvd', 'Nakache', 'Olivier'],
        ];

        $numEdition = 0;
        $numExemplaire = 0;

        foreach ($catalogue as $i => $ligne) {
            [$titre, $type, $nom, $prenom] = $ligne;
            $extras = $ligne[4] ?? [];

            $oeuvre = (new Oeuvre())
                ->setTitre($titre)
                ->setTypeOeuvre($type);
            $manager->persist($oeuvre);

            // Contribution principale
            $role = $type === 'dvd' ? 'realisateur' : 'auteur';
            $this->contribuer($manager, $oeuvre, $role, $nom, $prenom);
            foreach ($extras as [$r, $n, $p]) {
                $this->contribuer($manager, $oeuvre, $r, $n, $p);
            }

            // Éditions : une seule, et une deuxième (poche) pour un titre sur quatre
            $editions = [[$type === 'dvd' ? 'Studio Distribution' : 'Gallimard', 2005 + ($i % 15)]];
            if ($type === 'livre' && $i % 4 === 0) {
                $editions[] = ['Folio', 2018];
            }

            foreach ($editions as [$editeur, $annee]) {
                $numEdition++;
                $edition = (new Edition())
                    ->setOeuvre($oeuvre)
                    ->setIsbn($type === 'dvd' ? null : $this->isbn13($numEdition))
                    ->setEditeur($editeur)
                    ->setAnnee($annee)
                    ->setLangue('fr')
                    ->setSupport($type === 'dvd' ? 'dvd' : 'papier');
                $manager->persist($edition);
                $this->addReference('edition_' . $numEdition, $edition);

                // 2 exemplaires (un par site), 3 pour un titre sur trois
                $nbExemplaires = $numEdition % 3 === 0 ? 3 : 2;
                for ($k = 0; $k < $nbExemplaires; $k++) {
                    $numExemplaire++;
                    $exemplaire = (new Exemplaire())
                        ->setEdition($edition)
                        ->setSite($sites[$k % 2])
                        ->setCodeBarres(sprintf('MED-%05d', $numExemplaire))
                        ->setEtat(['neuf', 'bon', 'use'][$numExemplaire % 3])
                        ->setStatut('disponible');
                    $manager->persist($exemplaire);
                    $this->addReference('exemplaire_' . $numExemplaire, $exemplaire);
                }
            }
        }

        $manager->flush();
    }

    private function contribuer(ObjectManager $manager, Oeuvre $oeuvre, string $role, string $nom, ?string $prenom): void
    {
        $cle = $nom . '|' . $prenom;
        if (!isset($this->personnes[$cle])) {
            $personne = (new Personne())
                ->setNom($nom)
                ->setPrenom($prenom);
            $manager->persist($personne);
            $this->personnes[$cle] = $personne;
        }

        $manager->persist(
            (new Contribution())
                ->setOeuvre($oeuvre)
                ->setPersonne($this->personnes[$cle])
                ->setRole($role)
        );
    }

    /** ISBN-13 fictif mais avec une clé de contrôle valide. */
    private function isbn13(int $n): string
    {
        $base = '97829' . str_pad((string) $n, 7, '0', STR_PAD_LEFT);
        $somme = 0;
        foreach (str_split($base) as $i => $chiffre) {
            $somme += (int) $chiffre * ($i % 2 === 0 ? 1 : 3);
        }
        return $base . ((10 - $somme % 10) % 10);
    }
}
