<?php

namespace App\Tests\Service;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Formule;
use App\Entity\Reservation;
use App\Exception\RegleMetierException;
use App\Repository\PretRepository;
use App\Repository\ReservationRepository;
use App\Service\EmpruntService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class EmpruntServiceTest extends TestCase
{
    private const MAINTENANT = '2026-11-25 09:00:00';

    // ---------- Fabriques ----------

    private function adherent(int $maxPrets = 3, int $duree = 21, string $debut = '2026-01-01', string $fin = '2026-12-31'): Adherent
    {
        $formule = (new Formule())
            ->setNom('Test')
            ->setMaxPrets($maxPrets)
            ->setDureePretJours($duree);

        $adherent = new Adherent();
        $adherent->addAbonnement(
            (new Abonnement())
                ->setFormule($formule)
                ->setDateDebut(new \DateTimeImmutable($debut))
                ->setDateFin(new \DateTimeImmutable($fin))
        );

        return $adherent;
    }

    private function exemplaire(string $statut = 'disponible'): Exemplaire
    {
        return (new Exemplaire())
            ->setEdition(new Edition())
            ->setStatut($statut);
    }

    private function service(
        int $pretsEnCours = 0,
        ?Reservation $miseDeCote = null,
        ?EntityManagerInterface $em = null,
    ): EmpruntService {
        $prets = $this->createStub(PretRepository::class);
        $prets->method('countEnCoursPourAdherent')->willReturn($pretsEnCours);

        $reservations = $this->createStub(ReservationRepository::class);
        $reservations->method('findMiseDeCote')->willReturn($miseDeCote);

        return new EmpruntService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $prets,
            $reservations,
            new MockClock(self::MAINTENANT)
        );
    }

    private function miseDeCote(string $jusqua): Reservation
    {
        return (new Reservation())
            ->setStatut(Reservation::DISPONIBLE)
            ->setMiseDeCoteJusqua(new \DateTimeImmutable($jusqua));
    }

    // ---------- Cas nominal ----------

    public function testEmpruntNominal(): void
    {
        $adherent = $this->adherent(maxPrets: 3, duree: 21);
        $exemplaire = $this->exemplaire();

        $pret = $this->service()->emprunter($adherent, $exemplaire);

        $this->assertSame($adherent, $pret->getAdherent());
        $this->assertSame($exemplaire, $pret->getExemplaire());
        $this->assertSame('emprunte', $exemplaire->getStatut());
        $this->assertSame(0, $pret->getNbProlongations());
        $this->assertNull($pret->getDateRetour());
        $this->assertEquals(new \DateTimeImmutable('2026-12-16 09:00:00'), $pret->getDateEcheance());
    }

    public function testLEmpruntEstEnregistreUneSeuleFois(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $this->service(em: $em)->emprunter($this->adherent(), $this->exemplaire());
    }

    // ---------- Abonnement ----------

    public function testRefuseSansAbonnement(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('abonnement');

        $this->service()->emprunter(new Adherent(), $this->exemplaire());
    }

    public function testRefuseSiAbonnementExpire(): void
    {
        $adherent = $this->adherent(debut: '2025-01-01', fin: '2025-12-31');

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('abonnement');

        $this->service()->emprunter($adherent, $this->exemplaire());
    }

    // ---------- Limite de prêts simultanés ----------

    public function testRefuseSiLimiteAtteinte(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Limite atteinte');

        $this->service(pretsEnCours: 3)->emprunter($this->adherent(maxPrets: 3), $this->exemplaire());
    }

    public function testAutoriseJusteSousLaLimite(): void
    {
        $pret = $this->service(pretsEnCours: 2)->emprunter($this->adherent(maxPrets: 3), $this->exemplaire());
        $this->assertNotNull($pret);
    }

    public function testLaLimiteDependDeLaFormule(): void
    {
        // Formule à 1 prêt : un adhérent ayant déjà 1 prêt est bloqué…
        try {
            $this->service(pretsEnCours: 1)->emprunter($this->adherent(maxPrets: 1), $this->exemplaire());
            $this->fail('Le deuxième prêt aurait dû être refusé avec une formule à 1 prêt.');
        } catch (RegleMetierException) {
            $this->addToAssertionCount(1);
        }

        // … alors qu'avec une formule à 10 prêts, le même nombre de prêts passe.
        $pret = $this->service(pretsEnCours: 1)->emprunter($this->adherent(maxPrets: 10), $this->exemplaire());
        $this->assertNotNull($pret);
    }

    // ---------- Disponibilité de l'exemplaire ----------

    public function testRefuseSiExemplaireDejaEmprunte(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage("n'est pas empruntable");

        $this->service()->emprunter($this->adherent(), $this->exemplaire('emprunte'));
    }

    public function testRefuseSiExemplairePerdu(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage("n'est pas empruntable");

        $this->service()->emprunter($this->adherent(), $this->exemplaire('perdu'));
    }

    public function testRefuseSiExemplaireRetire(): void
    {
        $this->expectException(RegleMetierException::class);

        $this->service()->emprunter($this->adherent(), $this->exemplaire('retire'));
    }

    // ---------- Mise de côté ----------

    public function testRefuseSiMisDeCotePourUnAutreAdherent(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('autre adhérent');

        // Aucune réservation « disponible » n'existe pour cet adhérent.
        $this->service(miseDeCote: null)->emprunter($this->adherent(), $this->exemplaire('reserve'));
    }

    public function testLeBeneficiaireDeLaMiseDeCotePeutEmprunter(): void
    {
        $reservation = $this->miseDeCote('2026-11-28 18:00:00');
        $exemplaire = $this->exemplaire('reserve');

        $pret = $this->service(miseDeCote: $reservation)->emprunter($this->adherent(), $exemplaire);

        $this->assertSame('emprunte', $exemplaire->getStatut());
        $this->assertSame(Reservation::RETIREE, $reservation->getStatut());
        $this->assertNotNull($pret);
    }

    public function testRefuseSiLaMiseDeCoteEstExpiree(): void
    {
        $reservation = $this->miseDeCote('2026-11-24 18:00:00');

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('a expiré');

        $this->service(miseDeCote: $reservation)->emprunter($this->adherent(), $this->exemplaire('reserve'));
    }

    // ---------- Aucune écriture en cas de refus ----------

    public function testRienNEstEnregistreQuandLEmpruntEstRefuse(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $exemplaire = $this->exemplaire('emprunte');

        try {
            $this->service(em: $em)->emprunter($this->adherent(), $exemplaire);
            $this->fail('Une RegleMetierException était attendue.');
        } catch (RegleMetierException) {
            $this->assertSame('emprunte', $exemplaire->getStatut());
        }
    }
}
