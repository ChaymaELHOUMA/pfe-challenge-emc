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

        // catalogue data (les 30 premiers restent strictement inchangés pour préserver les références)
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

            // --- Littérature marocaine et maghrébine ---
            ['La Nuit sacrée', 'livre', 'Ben Jelloun', 'Tahar'],
            ["L'Enfant de sable", 'livre', 'Ben Jelloun', 'Tahar'],
            ['Le Passé simple', 'livre', 'Chraïbi', 'Driss'],
            ['Chanson douce', 'livre', 'Slimani', 'Leïla'],
            ["Dans le jardin de l'ogre", 'livre', 'Slimani', 'Leïla'],
            ['Nedjma', 'livre', 'Yacine', 'Kateb'],

            // --- Littérature mondiale et classiques enrichis ---
            ['Don Quichotte', 'livre', 'Cervantès', 'Miguel de', [['traducteur', 'Viardot', 'Louis']]],
            ["L'Odyssée", 'livre', 'Homère', null, [['traducteur', 'Bérard', 'Victor']]],
            ["L'Iliade", 'livre', 'Homère', null, [['traducteur', 'Mazon', 'Paul']]],
            ['Le Procès', 'livre', 'Kafka', 'Franz', [['traducteur', 'Vialatte', 'Alexandre']]],
            ['La Métamorphose', 'livre', 'Kafka', 'Franz', [['traducteur', 'Vialatte', 'Alexandre']]],
            ['Cent ans de solitude', 'livre', 'García Márquez', 'Gabriel', [['traducteur', 'Durand', 'Claude']]],
            ['Le Vieil Homme et la Mer', 'livre', 'Hemingway', 'Ernest', [['traducteur', 'Dutourd', 'Jean']]],
            ['Gatsby le Magnifique', 'livre', 'Fitzgerald', 'F. Scott', [['traducteur', 'Brieux', 'Victor']]],
            ['Sur la route', 'livre', 'Kerouac', 'Jack'],
            ['Le Nom de la rose', 'livre', 'Eco', 'Umberto', [['traducteur', 'Bouzaher', 'Jean-Noël']]],
            ["L'Insoutenable Légèreté de l'être", 'livre', 'Kundera', 'Milan'],
            ['En attendant Godot', 'livre', 'Beckett', 'Samuel'],
            ['Huis clos', 'livre', 'Sartre', 'Jean-Paul'],
            ['La Nausée', 'livre', 'Sartre', 'Jean-Paul'],
            ['Le Deuxième Sexe', 'livre', 'Beauvoir', 'Simone de'],
            ['La Gloire de mon père', 'livre', 'Pagnol', 'Marcel'],
            ['Le Château de ma mère', 'livre', 'Pagnol', 'Marcel'],
            ["L'Écume des jours", 'livre', 'Vian', 'Boris'],
            ['Paroles', 'livre', 'Prévert', 'Jacques'],
            ['Alcools', 'livre', 'Apollinaire', 'Guillaume'],
            ['Voyage au bout de la nuit', 'livre', 'Céline', 'Louis-Ferdinand'],
            ['La Condition humaine', 'livre', 'Malraux', 'André'],
            ['Le Grand Meaulnes', 'livre', 'Alain-Fournier', null],
            ['Dune', 'livre', 'Herbert', 'Frank', [['traducteur', 'Démuth', 'Michel']]],
            ['Fondation', 'livre', 'Asimov', 'Isaac', [['traducteur', 'Rosenthal', 'Jean']]],
            ['Les Robots', 'livre', 'Asimov', 'Isaac'],
            ['Neuromancien', 'livre', 'Gibson', 'William'],
            ['Ubik', 'livre', 'Dick', 'Philip K.'],
            ['Chroniques martiennes', 'livre', 'Bradbury', 'Ray'],
            ['Shining', 'livre', 'King', 'Stephen'],
            ['Le Seigneur des anneaux', 'livre', 'Tolkien', 'J.R.R.', [['traducteur', 'Ledoux', 'Francis']]],
            ['Le Hobbit', 'livre', 'Tolkien', 'J.R.R.', [['traducteur', 'Ledoux', 'Francis']]],
            ['Harry Potter à l’école des sorciers', 'livre', 'Rowling', 'J.K.', [['traducteur', 'Ménard', 'Jean-François']]],
            ['Harry Potter et la Chambre des secrets', 'livre', 'Rowling', 'J.K.', [['traducteur', 'Ménard', 'Jean-François']]],

            // --- Bandes dessinées & Mangas ---
            ['Blacksad : Quelque part entre les ombres', 'bd', 'Canales', 'Juan Díaz', [['illustrateur', 'Guarnido', 'Juanjo']]],
            ['Largo Winch : L’Héritier', 'bd', 'Van Hamme', 'Jean', [['illustrateur', 'Francq', 'Philippe']]],
            ['XIII : Le Jour du soleil noir', 'bd', 'Van Hamme', 'Jean', [['illustrateur', 'Vance', 'William']]],
            ['Thorgal : La Magicienne trahie', 'bd', 'Van Hamme', 'Jean', [['illustrateur', 'Rosiński', 'Grzegorz']]],
            ['Les Passagers du vent', 'bd', 'Bourgeon', 'François'],
            ['Gaston Lagaffe : Gare aux gaffes', 'bd', 'Franquin', 'André'],
            ['Lucky Luke : La Diligence', 'bd', 'Goscinny', 'René', [['illustrateur', 'Morris', null]]],
            ['Blake et Mortimer : Le Secret de l’Espadon', 'bd', 'Jacobs', 'Edgar P.'],
            ['Corto Maltese : La Ballade de la mer salée', 'bd', 'Pratt', 'Hugo'],
            ['Le Chat du rabbin', 'bd', 'Sfar', 'Joann'],
            ['One Piece - Tome 1', 'bd', 'Oda', 'Eiichiro'],
            ['Naruto - Tome 1', 'bd', 'Kishimoto', 'Masashi'],
            ['Death Note - Tome 1', 'bd', 'Ohba', 'Tsugumi', [['illustrateur', 'Obata', 'Takeshi']]],
            ['L’Attaque des Titans - Tome 1', 'bd', 'Isayama', 'Hajime'],
            ['Dragon Ball - Tome 1', 'bd', 'Toriyama', 'Akira'],
            ['Akira - Tome 1', 'bd', 'Otomo', 'Katsuhiro'],
            ['Monster - Tome 1', 'bd', 'Urasawa', 'Naoki'],

            // --- DVD & Cinéma ---
            ['Cinema Paradiso', 'dvd', 'Tornatore', 'Giuseppe'],
            ['Le Voyage de Chihiro', 'dvd', 'Miyazaki', 'Hayao'],
            ['Mon Voisin Totoro', 'dvd', 'Miyazaki', 'Hayao'],
            ['Princesse Mononoké', 'dvd', 'Miyazaki', 'Hayao'],
            ['Parasite', 'dvd', 'Bong', 'Joon-ho'],
            ['Pulp Fiction', 'dvd', 'Tarantino', 'Quentin'],
            ['Inception', 'dvd', 'Nolan', 'Christopher'],
            ['Interstellar', 'dvd', 'Nolan', 'Christopher'],
            ['La Cité de la peur', 'dvd', 'Berbérian', 'Alain'],
            ['Les Tontons flingueurs', 'dvd', 'Lautner', 'Georges'],
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
                    ->setSupport($type === 'dvd' ? 'dvd' : 'papier')
                    ->setCouvertureUrl(sprintf('https://covers.openlibrary.org/b/id/%d-M.jpg', 8000000 + $numEdition * 37));
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
