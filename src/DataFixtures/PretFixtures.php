<?php

namespace App\DataFixtures;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Reservation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PretFixtures extends Fixture implements DependentFixtureInterface
{
    private \DateTimeImmutable $aujourdhui;

    public function getDependencies(): array
    {
        return [CatalogueFixtures::class, AdherentFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->aujourdhui = new \DateTimeImmutable('today');

        // [adhérent, exemplaire, début (jours avant aujourd'hui), durée du prêt (jours), retour (jours avant aujourd'hui) ou null, nb prolongations]
        $enCours = [
            [2, 1, 5, 21, null, 0],
            [3, 2, 5, 28, null, 0],
            [1, 5, 3, 28, null, 0],
            [1, 8, 10, 28, null, 1],
            [4, 11, 2, 14, null, 0],
            [5, 14, 7, 21, null, 0],
            [6, 17, 1, 28, null, 0],
            [8, 20, 12, 21, null, 0],
        ];

        $enRetard = [
            [7, 23, 30, 14, null, 0],
            [9, 26, 45, 28, null, 1],
            [10, 29, 25, 14, null, 0],
            [11, 32, 28, 21, null, 0],
            [12, 35, 50, 21, null, 1],
        ];

        foreach (array_merge($enCours, $enRetard) as $ligne) {
            $pret = $this->creerPret(...$ligne);
            $manager->persist($pret);
            $pret->getExemplaire()->setStatut('emprunte');
        }

        $rendus = [
            [13, 40, 100, 21, 80, 0], [14, 41, 90, 21, 75, 0], [15, 42, 85, 28, 60, 0],
            [16, 43, 70, 21, 55, 0], [17, 44, 60, 21, 45, 0], [18, 45, 50, 14, 40, 0],
            [19, 46, 200, 21, 185, 0], [20, 47, 190, 21, 170, 0], [2, 48, 80, 21, 65, 1],
            [3, 49, 75, 28, 50, 0], [5, 50, 65, 21, 50, 0], [6, 51, 55, 28, 40, 0],
        ];
        foreach ($rendus as $ligne) {
            $manager->persist($this->creerPret(...$ligne));
        }

        $rendusEnRetard = [
            [4, 52, 120, 14, 100, 0],
            [8, 53, 110, 21, 85, 0],
            [9, 54, 95, 28, 60, 0],
        ];
        foreach ($rendusEnRetard as $i => $ligne) {
            $pret = $this->creerPret(...$ligne);
            $manager->persist($pret);
            $jours = (int) $pret->getDateEcheance()->setTime(0, 0)->diff($pret->getDateRetour()->setTime(0, 0))->days;
            $montant = number_format($jours * (float) $this->tauxPourAdherent($ligne[0]), 2, '.', '');
            $manager->persist($this->creerPenalite($pret, $montant, 'retard', $i === 0));
        }

        // 5. Document perdu
        $perdu = $this->creerPret(13, 55, 150, 21, 120, 0);
        $manager->persist($perdu);
        $perdu->getExemplaire()->setStatut('perdu');
        $manager->persist($this->creerPenalite($perdu, '25.00', 'perdu', false));

        // 6. Document abîmé
        $abime = $this->creerPret(14, 56, 100, 21, 90, 0);
        $manager->persist($abime);
        $abime->getExemplaire()->setEtat('abime');
        $abime->getExemplaire()->setStatut('retire');
        $manager->persist($this->creerPenalite($abime, '5.00', 'abime', false));

        // 7. Réservations
        $manager->persist($this->creerReservation(5, 'edition_1', 4, Reservation::EN_ATTENTE));
        $manager->persist($this->creerReservation(6, 'edition_1', 3, Reservation::EN_ATTENTE));
        $manager->persist($this->creerReservation(8, 'edition_1', 1, Reservation::EN_ATTENTE));

        $this->getReference('exemplaire_3', Exemplaire::class)->setStatut('reserve');
        $mise = $this->creerReservation(9, 'edition_2', 6, Reservation::DISPONIBLE);
        $mise->setMiseDeCoteJusqua($this->aujourdhui->modify('+3 days')->setTime(18, 0));
        $manager->persist($mise);

        $manager->persist($this->creerReservation(11, 'edition_5', 20, Reservation::ANNULEE));
        $manager->persist($this->creerReservation(12, 'edition_7', 30, Reservation::EXPIREE));

        $manager->flush();
    }

    private function creerPret(int $adherent, int $exemplaire, int $debut, int $duree, ?int $retour, int $nbProlongations): Pret
    {
        $dateDebut = $this->aujourdhui->modify("-{$debut} days")->setTime(10, 0);

        $pret = (new Pret())
            ->setAdherent($this->getReference('adherent_' . $adherent, Adherent::class))
            ->setExemplaire($this->getReference('exemplaire_' . $exemplaire, Exemplaire::class))
            ->setDateDebut($dateDebut)
            ->setDateEcheance($dateDebut->modify("+{$duree} days"))
            ->setNbProlongations($nbProlongations);

        if ($retour !== null) {
            $pret->setDateRetour($this->aujourdhui->modify("-{$retour} days")->setTime(15, 0));
        }

        return $pret;
    }

    private function creerPenalite(Pret $pret, string $montant, string $motif, bool $reglee): Penalite
    {
        return (new Penalite())
            ->setPret($pret)
            ->setMontant($montant)
            ->setMotif($motif)
            ->setCreeLe($pret->getDateRetour())
            ->setReglee($reglee);
    }

    private function creerReservation(int $adherent, string $edition, int $joursAvant, string $statut): Reservation
    {
        return (new Reservation())
            ->setAdherent($this->getReference('adherent_' . $adherent, Adherent::class))
            ->setEdition($this->getReference($edition, Edition::class))
            ->setCreeLe($this->aujourdhui->modify("-{$joursAvant} days")->setTime(11, 0))
            ->setStatut($statut);
    }

    /** Pénalité par jour de la formule de l'adhérent, telle que définie dans AdherentFixtures. */
    private function tauxPourAdherent(int $numero): string
    {
        if ($numero === 1) {
            return '0.30'; // Premium
        }
        if ($numero >= 19) {
            return '0.50'; // Standard (abonnement expiré)
        }
        return ['0.50', '0.50', '0.30'][($numero - 1) % 3]; // Découverte, Standard, Premium
    }
}
