<?php

namespace App\Tests\Repository;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReservationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReservationRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Reservation::class);

        // Nettoie les données entre les tests (sans transaction imbriquée pour éviter les blocages MySQL)
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM reservation');
        $conn->executeStatement('DELETE FROM edition');
        $conn->executeStatement('DELETE FROM oeuvre');
        $conn->executeStatement('DELETE FROM adherent');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    // ---------- Fabriques ----------

    private function edition(): Edition
    {
        $oeuvre = (new Oeuvre())->setTitre('Titre ' . uniqid())->setTypeOeuvre('livre');
        $edition = (new Edition())
            ->setOeuvre($oeuvre)
            ->setEditeur('Éditeur')
            ->setAnnee(2020)
            ->setLangue('fr')
            ->setSupport('papier');
        $this->em->persist($oeuvre);
        $this->em->persist($edition);

        return $edition;
    }

    private function adherent(): Adherent
    {
        $adherent = (new Adherent())
            ->setNom('Nom')
            ->setPrenom('Prénom')
            ->setEmail(uniqid('a') . '@example.test');
        $this->em->persist($adherent);

        return $adherent;
    }

    private function reservation(
        Edition $edition,
        string $creeLe,
        string $statut = Reservation::EN_ATTENTE,
        ?Adherent $adherent = null,
        ?string $jusqua = null,
    ): Reservation {
        $reservation = (new Reservation())
            ->setAdherent($adherent ?? $this->adherent())
            ->setEdition($edition)
            ->setCreeLe(new \DateTimeImmutable($creeLe))
            ->setStatut($statut)
            ->setMiseDeCoteJusqua($jusqua ? new \DateTimeImmutable($jusqua) : null);
        $this->em->persist($reservation);

        return $reservation;
    }

    // ---------- Tests ----------

    public function testLaFileEstOrdonneeParDateEtParIdEnCasDEgalite(): void
    {
        $edition = $this->edition();
        $tard = $this->reservation($edition, '2026-11-20 11:00:00');
        $premier = $this->reservation($edition, '2026-11-20 10:00:00');
        $egalite = $this->reservation($edition, '2026-11-20 10:00:00'); // même instant, id plus grand
        $this->em->flush();

        $file = $this->repo->findEnAttente($edition);

        $this->assertSame([$premier, $egalite, $tard], $file);
    }

    public function testPositionDansLaFile(): void
    {
        $edition = $this->edition();
        $r1 = $this->reservation($edition, '2026-11-20 10:00:00');
        $r2 = $this->reservation($edition, '2026-11-20 10:00:00');
        $r3 = $this->reservation($edition, '2026-11-20 11:00:00');
        $this->em->flush();

        $this->assertSame(1, $this->repo->positionDansLaFile($r1));
        $this->assertSame(2, $this->repo->positionDansLaFile($r2));
        $this->assertSame(3, $this->repo->positionDansLaFile($r3));
    }

    public function testLesReservationsTermineesNeComptentPasDansLaFile(): void
    {
        $edition = $this->edition();
        // Plus ancienne que les autres, mais annulée : elle ne doit pas décaler les positions.
        $this->reservation($edition, '2026-11-19 09:00:00', Reservation::ANNULEE);
        $this->reservation($edition, '2026-11-19 10:00:00', Reservation::EXPIREE);
        $this->reservation($edition, '2026-11-19 11:00:00', Reservation::RETIREE);
        $this->reservation($edition, '2026-11-19 12:00:00', Reservation::DISPONIBLE, null, '2026-11-30 23:59:59');
        $premier = $this->reservation($edition, '2026-11-20 10:00:00');
        $this->em->flush();

        $this->assertSame(1, $this->repo->positionDansLaFile($premier));
        $this->assertSame([$premier], $this->repo->findEnAttente($edition));
        $this->assertSame(1, $this->repo->countEnAttentePourEdition($edition));
    }

    public function testLesFilesDeDeuxEditionsSontIndependantes(): void
    {
        $e1 = $this->edition();
        $e2 = $this->edition();
        $this->reservation($e1, '2026-11-20 10:00:00');
        $this->reservation($e1, '2026-11-20 11:00:00');
        $r = $this->reservation($e2, '2026-11-20 12:00:00');
        $this->em->flush();

        $this->assertSame(2, $this->repo->countEnAttentePourEdition($e1));
        $this->assertSame(1, $this->repo->positionDansLaFile($r));
    }

    public function testFindActiveRetrouveEnAttenteEtMiseDeCoteMaisPasLesTerminees(): void
    {
        $edition = $this->edition();
        $adherent = $this->adherent();
        $this->reservation($edition, '2026-11-20 10:00:00', Reservation::ANNULEE, $adherent);
        $this->em->flush();

        $this->assertNull($this->repo->findActive($adherent, $edition));

        $active = $this->reservation($edition, '2026-11-21 10:00:00', Reservation::EN_ATTENTE, $adherent);
        $this->em->flush();

        $this->assertSame($active, $this->repo->findActive($adherent, $edition));

        $active->setStatut(Reservation::DISPONIBLE);
        $this->em->flush();

        $this->assertSame($active, $this->repo->findActive($adherent, $edition));
    }

    public function testCountActivesPourAdherent(): void
    {
        $adherent = $this->adherent();
        $this->reservation($this->edition(), '2026-11-20 10:00:00', Reservation::EN_ATTENTE, $adherent);
        $this->reservation($this->edition(), '2026-11-20 10:00:00', Reservation::DISPONIBLE, $adherent, '2026-11-30 23:59:59');
        $this->reservation($this->edition(), '2026-11-20 10:00:00', Reservation::ANNULEE, $adherent);
        $this->reservation($this->edition(), '2026-11-20 10:00:00', Reservation::RETIREE, $adherent);
        $this->em->flush();

        $this->assertSame(2, $this->repo->countActivesPourAdherent($adherent));
    }

    public function testFindMisesDeCoteExpirees(): void
    {
        $edition = $this->edition();
        $expiree = $this->reservation($edition, '2026-11-10 10:00:00', Reservation::DISPONIBLE, null, '2026-11-24 23:59:59');
        $this->reservation($edition, '2026-11-10 11:00:00', Reservation::DISPONIBLE, null, '2026-11-30 23:59:59'); // encore valable
        $this->reservation($edition, '2026-11-10 12:00:00', Reservation::EN_ATTENTE, null, '2026-11-01 00:00:00');  // pas mise de côté
        $this->em->flush();

        $resultat = $this->repo->findMisesDeCoteExpirees(new \DateTimeImmutable('2026-11-25 09:00:00'));

        $this->assertSame([$expiree], $resultat);
    }
}
