<?php

namespace App\Tests\Service;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Formule;
use App\Entity\Reservation;
use App\Exception\RegleMetierException;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Repository\ReservationRepository;
use App\Service\MiseDeCoteService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class ReservationServiceTest extends TestCase
{
    private const MAINTENANT = '2026-11-25 09:00:00';

    private function adherent(string $debut = '2026-01-01', string $fin = '2026-12-31'): Adherent
    {
        $adherent = new Adherent();
        $adherent->addAbonnement(
            (new Abonnement())
                ->setFormule((new Formule())->setNom('Test'))
                ->setDateDebut(new \DateTimeImmutable($debut))
                ->setDateFin(new \DateTimeImmutable($fin))
        );

        return $adherent;
    }

    private function service(
        ?Reservation $dejaReservee = null,
        int $dejaEmprunte = 0,
        int $reservationsActives = 0,
        int $enCirculation = 2,
        int $disponiblesChezLAdherent = 0,
        ?EntityManagerInterface $em = null,
        ?MiseDeCoteService $miseDeCote = null,
    ): ReservationService {
        $reservations = $this->createStub(ReservationRepository::class);
        $reservations->method('findActive')->willReturn($dejaReservee);
        $reservations->method('countActivesPourAdherent')->willReturn($reservationsActives);

        $prets = $this->createStub(PretRepository::class);
        $prets->method('countEnCoursPourAdherentEtEdition')->willReturn($dejaEmprunte);

        // Le service l'appelle avec 3 statuts (en circulation) puis avec ['disponible'] seul.
        $exemplaires = $this->createStub(ExemplaireRepository::class);
        $exemplaires->method('countParEdition')->willReturnCallback(
            fn ($edition, array $statuts) => count($statuts) === 1 ? $disponiblesChezLAdherent : $enCirculation
        );

        return new ReservationService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $reservations,
            $prets,
            $exemplaires,
            $miseDeCote ?? $this->createStub(MiseDeCoteService::class),
            new MockClock(self::MAINTENANT)
        );
    }

    // ---------- Cas nominal ----------

    public function testReservationNominale(): void
    {
        $adherent = $this->adherent();
        $edition = new Edition();

        $reservation = $this->service()->reserver($adherent, $edition);

        $this->assertSame($adherent, $reservation->getAdherent());
        $this->assertSame($edition, $reservation->getEdition());
        $this->assertSame(Reservation::EN_ATTENTE, $reservation->getStatut());
        $this->assertEquals(new \DateTimeImmutable(self::MAINTENANT), $reservation->getCreeLe());
    }

    public function testLaReservationEstEnregistreeUneSeuleFois(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $this->service(em: $em)->reserver($this->adherent(), new Edition());
    }

    // ---------- Abonnement ----------

    public function testRefuseSansAbonnement(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('abonnement');

        $this->service()->reserver(new Adherent(), new Edition());
    }

    public function testRefuseSiAbonnementExpire(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('abonnement');

        $this->service()->reserver($this->adherent('2025-01-01', '2025-12-31'), new Edition());
    }

    // ---------- Doublons et prêt en cours ----------

    public function testRefuseUnDoublon(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('déjà réservée');

        $this->service(dejaReservee: new Reservation())->reserver($this->adherent(), new Edition());
    }

    public function testRefuseSiLEditionEstDejaEnPret(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('en prêt');

        $this->service(dejaEmprunte: 1)->reserver($this->adherent(), new Edition());
    }

    // ---------- Maximum de réservations ----------

    public function testRefuseAuDelaDuMaximum(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Maximum atteint');

        $this->service(reservationsActives: ReservationService::MAX_RESERVATIONS_ACTIVES)
            ->reserver($this->adherent(), new Edition());
    }

    public function testAutoriseJusteSousLeMaximum(): void
    {
        $reservation = $this->service(reservationsActives: ReservationService::MAX_RESERVATIONS_ACTIVES - 1)
            ->reserver($this->adherent(), new Edition());

        $this->assertSame(Reservation::EN_ATTENTE, $reservation->getStatut());
    }

    // ---------- Disponibilité ----------

    public function testRefuseSiAucunExemplaireEnCirculation(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('en circulation');

        $this->service(enCirculation: 0)->reserver($this->adherent(), new Edition());
    }

    public function testRefuseSiUnExemplaireEstDisponibleChezLAdherent(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('directement');

        $this->service(disponiblesChezLAdherent: 1)->reserver($this->adherent(), new Edition());
    }

    /** Question 2 : disponible uniquement dans l'autre site, la réservation reste possible. */
    public function testAutoriseSiDisponibleSeulementDansLAutreSite(): void
    {
        $reservation = $this->service(enCirculation: 2, disponiblesChezLAdherent: 0)
            ->reserver($this->adherent(), new Edition());

        $this->assertSame(Reservation::EN_ATTENTE, $reservation->getStatut());
    }

    // ---------- Annulation ----------

    public function testAnnulerUneReservationEnAttente(): void
    {
        $reservation = (new Reservation())->setStatut(Reservation::EN_ATTENTE);

        $this->service()->annuler($reservation);

        $this->assertSame(Reservation::ANNULEE, $reservation->getStatut());
    }

    public function testAnnulerRefuseSiLeDocumentEstDejaRetire(): void
    {
        $reservation = (new Reservation())->setStatut(Reservation::RETIREE);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('en attente ou mise de côté');

        $this->service()->annuler($reservation);
    }

    public function testAnnulerUneMiseDeCoteLibereLExemplaire(): void
    {
        $edition = new Edition();
        $reservation = (new Reservation())->setStatut(Reservation::DISPONIBLE)->setEdition($edition);

        $miseDeCote = $this->createMock(MiseDeCoteService::class);
        $miseDeCote->expects($this->once())->method('liberer')->with($edition);

        $this->service(miseDeCote: $miseDeCote)->annuler($reservation);

        $this->assertSame(Reservation::ANNULEE, $reservation->getStatut());
    }

    public function testAnnulerUneReservationEnAttenteNeLiberePasDExemplaire(): void
    {
        $miseDeCote = $this->createMock(MiseDeCoteService::class);
        $miseDeCote->expects($this->never())->method('liberer');

        $reservation = (new Reservation())->setStatut(Reservation::EN_ATTENTE)->setEdition(new Edition());

        $this->service(miseDeCote: $miseDeCote)->annuler($reservation);

        $this->assertSame(Reservation::ANNULEE, $reservation->getStatut());
    }

    // ---------- Aucune écriture en cas de refus ----------

    public function testRienNEstEnregistreQuandLaReservationEstRefusee(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        try {
            $this->service(dejaReservee: new Reservation(), em: $em)->reserver($this->adherent(), new Edition());
            $this->fail('Une RegleMetierException était attendue.');
        } catch (RegleMetierException) {
            $this->addToAssertionCount(1);
        }
    }
}
