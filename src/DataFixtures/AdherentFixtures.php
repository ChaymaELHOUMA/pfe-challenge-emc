<?php

namespace App\DataFixtures;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Formule;
use App\Entity\Site;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AdherentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [CatalogueFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // --- Formules (montants fictifs, à adapter) ---
        $definitions = [
            // clé => [nom, maxPrets, durée en jours, maxProlongations, pénalité/jour, tarif annuel]
            'decouverte' => ['Découverte', 3, 14, 1, '0.50', '50.00'],
            'standard'   => ['Standard', 5, 21, 1, '0.50', '120.00'],
            'premium'    => ['Premium', 10, 28, 2, '0.30', '250.00'],
        ];

        $formules = [];
        foreach ($definitions as $cle => [$nom, $maxPrets, $duree, $maxProlongations, $penalite, $tarif]) {
            $formule = (new Formule())
                ->setNom($nom)
                ->setMaxPrets($maxPrets)
                ->setDureePretJours($duree)
                ->setMaxProlongations($maxProlongations)
                ->setPenaliteParJour($penalite)
                ->setTarifAnnuel($tarif);
            $manager->persist($formule);
            $this->addReference('formule_' . $cle, $formule);
            $formules[$cle] = $formule;
        }

        // --- Adhérents ---
        $identites = [
            ['Amina', 'Benali'], ['Youssef', 'El Idrissi'], ['Salma', 'Tazi'],
            ['Omar', 'Alaoui'], ['Khadija', 'Bennani'], ['Mehdi', 'Chraibi'],
            ['Sara', 'Lahlou'], ['Hamza', 'Fassi'], ['Nadia', 'Berrada'],
            ['Anas', 'Zniber'], ['Imane', 'Saidi'], ['Reda', 'Kettani'],
            ['Hajar', 'Ouazzani'], ['Ilyas', 'Mansouri'], ['Zineb', 'Hajji'],
            ['Walid', 'Rahmani'], ['Meryem', 'Squalli'], ['Bilal', 'Naciri'],
            ['Loubna', 'Cherkaoui'], ['Karim', 'Benjelloun'],
        ];

        $aujourdhui = new \DateTimeImmutable('today');
        $cles = array_keys($formules);

        foreach ($identites as $i => [$prenom, $nom]) {
            $numero = $i + 1;

            $adherent = (new Adherent())
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setEmail(strtolower($prenom . '.' . str_replace(' ', '', $nom)) . '@example.test')
                ->setDateInscription($aujourdhui->modify('-' . (400 + $i * 15) . ' days'))
                ->setSiteRattachement($this->getReference('site_' . (($i % 2) + 1), Site::class));
            $manager->persist($adherent);
            $this->addReference('adherent_' . $numero, $adherent);

            if ($numero === 1) {
                // Historique : une ancienne formule expirée, puis la formule actuelle
                $this->abonner($manager, $adherent, $formules['decouverte'], $aujourdhui->modify('-400 days'), $aujourdhui->modify('-35 days'));
                $this->abonner($manager, $adherent, $formules['premium'], $aujourdhui->modify('-34 days'), $aujourdhui->modify('+330 days'));
            } elseif ($numero >= 19) {
                // Abonnement expiré : sert à tester le refus d'emprunt
                $this->abonner($manager, $adherent, $formules['standard'], $aujourdhui->modify('-400 days'), $aujourdhui->modify('-40 days'));
            } else {
                $formule = $formules[$cles[$i % 3]];
                $this->abonner($manager, $adherent, $formule, $aujourdhui->modify('-120 days'), $aujourdhui->modify('+245 days'));
            }
        }

        $manager->flush();
    }

    private function abonner(
        ObjectManager $manager,
        Adherent $adherent,
        Formule $formule,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): void {
        $manager->persist(
            (new Abonnement())
                ->setAdherent($adherent)
                ->setFormule($formule)
                ->setDateDebut($debut)
                ->setDateFin($fin)
        );
    }
}
