<?php

namespace App\Tests\Service;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Formule;
use App\Entity\Reservation;
use App\Repository\ExemplaireRepository;
use App\Repository\ReservationRepository;
use App\Service\MiseDeCoteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class MiseDeCoteServiceTest extends TestCase
{
    private const MAINTENANT = '2026-11-25 09:00:00';

    private function adherent(bool $abonnementActif = true): Adherent
    {
        $adherent = new Adherent();

        if ($abonnementActif) {
            $adherent->addAbonnement(
                (new Abonnement())
                    ->setFormule((new Formule())->setNom('Test'))
                    ->setDateDebut(new \DateTimeImmutable('2026-01-01'))
                    ->setDateFin(new \DateTimeImmutable('2026-12-31'))
            );
        }

        return $adherent;
    }

    private function enAttente(Adherent $adherent): Reservation
    {
        return (new Reservation())
            ->setAdherent($adherent)
            ->setEdition(new Edition())
            ->setStatut(Reservation::EN_ATTENTE);
    }

    private function exemplaire(string $statut): Exemplaire
    {
        return (new Exemplaire())->setEdition(new Edition())->setStatut($statut);
    }

    private function service(
        array $file = [],
        array $expirees = [],
        ?Exemplaire $reserve = null,
        ?EntityManagerInterface $em = null,
    ): MiseDeCoteService {
        $reservations = $this->createStub(ReservationRepository::class);
        $reservations->method('findEnAttente')->willReturn($file);
        $reservations->method('findMisesDeCoteExpirees')->willReturn($expirees);

        $exemplaires = $this->createStub(ExemplaireRepository::class);
        $exemplaires->method('findOneReservePourEdition')->willReturn($reserve);

        return new MiseDeCoteService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $reservations,
            $exemplaires,
            new MockClock(self::MAINTENANT)
        );
    }

    // ---------- affecter ----------

    public function testSansFileLExemplaireRedevientDisponible(): void
    {
        $exemplaire = $this->exemplaire('emprunte');

        $resultat = $this->service(file: [])->affecter($exemplaire);

        $this->assertNull($resultat);
        $this->assertSame('disponible', $exemplaire->getStatut());
    }

    public function testLePremierDeLaFileRecoitLeDocument(): void
    {
        $premier = $this->enAttente($this->adherent());
        $second = $this->enAttente($this->adherent());
        $exemplaire = $this->exemplaire('emprunte');

        $resultat = $this->service(file: [$premier, $second])->affecter($exemplaire);

        $this->assertSame($premier, $resultat);
        $this->assertSame(Reservation::DISPONIBLE, $premier->getStatut());
        $this->assertSame(Reservation::EN_ATTENTE, $second->getStatut());
        $this->assertSame('reserve', $exemplaire->getStatut());
    }

    public function testLaDateLimiteEstCinqJoursPlusTardEnFinDeJournee(): void
    {
        $reservation = $this->enAttente($this->adherent());

        $this->service(file: [$reservation])->affecter($this->exemplaire('emprunte'));

        $this->assertEquals(
            new \DateTimeImmutable('2026-11-30 23:59:59'),
            $reservation->getMiseDeCoteJusqua()
        );
    }

    public function testUnAdherentSansAbonnementPerdSonTour(): void
    {
        $ecarte = $this->enAttente($this->adherent(abonnementActif: false));
        $suivant = $this->enAttente($this->adherent());
        $exemplaire = $this->exemplaire('emprunte');

        $resultat = $this->service(file: [$ecarte, $suivant])->affecter($exemplaire);

        $this->assertSame(Reservation::EXPIREE, $ecarte->getStatut());
        $this->assertSame($suivant, $resultat);
        $this->assertSame(Reservation::DISPONIBLE, $suivant->getStatut());
        $this->assertSame('reserve', $exemplaire->getStatut());
    }

    public function testSiToutLeMondeEstEcarteLExemplaireRedevientDisponible(): void
    {
        $ecarte = $this->enAttente($this->adherent(abonnementActif: false));
        $exemplaire = $this->exemplaire('emprunte');

        $resultat = $this->service(file: [$ecarte])->affecter($exemplaire);

        $this->assertNull($resultat);
        $this->assertSame(Reservation::EXPIREE, $ecarte->getStatut());
        $this->assertSame('disponible', $exemplaire->getStatut());
    }

    // ---------- liberer ----------

    public function testLibererSansExemplaireMisDeCoteNeFaitRien(): void
    {
        $this->assertNull($this->service(reserve: null)->liberer(new Edition()));
    }

    public function testLibererProposeLExemplaireAuSuivant(): void
    {
        $suivant = $this->enAttente($this->adherent());
        $exemplaire = $this->exemplaire('reserve');

        $resultat = $this->service(file: [$suivant], reserve: $exemplaire)->liberer(new Edition());

        $this->assertSame($suivant, $resultat);
        $this->assertSame('reserve', $exemplaire->getStatut());
    }

    public function testLibererRemetEnRayonSiPersonneNAttend(): void
    {
        $exemplaire = $this->exemplaire('reserve');

        $this->service(file: [], reserve: $exemplaire)->liberer(new Edition());

        $this->assertSame('disponible', $exemplaire->getStatut());
    }

    // ---------- expirerDepassees ----------

    private function miseDeCoteDepassee(): Reservation
    {
        return (new Reservation())
            ->setAdherent($this->adherent())
            ->setEdition(new Edition())
            ->setStatut(Reservation::DISPONIBLE)
            ->setMiseDeCoteJusqua(new \DateTimeImmutable('2026-11-24 23:59:59'));
    }

    public function testUneMiseDeCoteExpireeRemetLExemplaireEnRayon(): void
    {
        $reservation = $this->miseDeCoteDepassee();
        $exemplaire = $this->exemplaire('reserve');

        $nb = $this->service(expirees: [$reservation], reserve: $exemplaire)->expirerDepassees();

        $this->assertSame(1, $nb);
        $this->assertSame(Reservation::EXPIREE, $reservation->getStatut());
        $this->assertSame('disponible', $exemplaire->getStatut());
    }

    public function testUneMiseDeCoteExpireePasseLExemplaireAuSuivant(): void
    {
        $reservation = $this->miseDeCoteDepassee();
        $suivant = $this->enAttente($this->adherent());
        $exemplaire = $this->exemplaire('reserve');

        $this->service(file: [$suivant], expirees: [$reservation], reserve: $exemplaire)->expirerDepassees();

        $this->assertSame(Reservation::EXPIREE, $reservation->getStatut());
        $this->assertSame(Reservation::DISPONIBLE, $suivant->getStatut());
        $this->assertSame('reserve', $exemplaire->getStatut());
    }

    public function testRienAExpirer(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $this->assertSame(0, $this->service(expirees: [], em: $em)->expirerDepassees());
    }

    public function testChaqueExpirationEstEnregistree(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('flush');

        $this->service(
            expirees: [$this->miseDeCoteDepassee(), $this->miseDeCoteDepassee()],
            reserve: $this->exemplaire('reserve'),
            em: $em
        )->expirerDepassees();
    }
}
