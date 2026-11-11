<?php

namespace App\Tests\Service;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Exemplaire;
use App\Entity\Formule;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Exception\RegleMetierException;
use App\Service\PenaliteService;
use App\Service\RetourService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class RetourServiceTest extends TestCase
{
    private const MAINTENANT = '2026-11-25 09:00:00';

    private function formule(string $penaliteParJour): Formule
    {
        return (new Formule())
            ->setNom('Test')
            ->setPenaliteParJour($penaliteParJour);
    }

    private function adherent(Formule $formule, string $debut = '2026-01-01', string $fin = '2026-12-31'): Adherent
    {
        $adherent = new Adherent();
        $adherent->addAbonnement(
            (new Abonnement())
                ->setFormule($formule)
                ->setDateDebut(new \DateTimeImmutable($debut))
                ->setDateFin(new \DateTimeImmutable($fin))
        );
        return $adherent;
    }

    private function pret(Adherent $adherent, string $echeance, string $debut = '2026-11-01 10:00:00'): Pret
    {
        return (new Pret())
            ->setAdherent($adherent)
            ->setExemplaire((new Exemplaire())->setStatut('emprunte'))
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateEcheance(new \DateTimeImmutable($echeance));
    }

    private function service(?EntityManagerInterface $em = null): RetourService
    {
        $em ??= $this->createStub(EntityManagerInterface::class);
        $clock = new MockClock(self::MAINTENANT);
        return new RetourService($em, new PenaliteService($em, $clock), $clock);
    }

    public function testRetourAPtemps(): void
    {
        $pret = $this->pret($this->adherent($this->formule('0.50')), '2026-12-01 10:00:00');
        $penalite = $this->service()->retourner($pret);
        $this->assertNull($penalite);
        $this->assertEquals(new \DateTimeImmutable(self::MAINTENANT), $pret->getDateRetour());
        $this->assertSame('disponible', $pret->getExemplaire()->getStatut());
    }

    public function testPasDePenaliteLeJourDeLEcheance(): void
    {
        $pret = $this->pret($this->adherent($this->formule('0.50')), '2026-11-25 08:00:00');
        $this->assertNull($this->service()->retourner($pret));
    }

    public function testRetourEnRetardGenereUnePenalite(): void
    {
        $pret = $this->pret($this->adherent($this->formule('0.50')), '2026-11-20 10:00:00');
        $penalite = $this->service()->retourner($pret);
        $this->assertInstanceOf(Penalite::class, $penalite);
        $this->assertSame('2.50', $penalite->getMontant());
        $this->assertSame('retard', $penalite->getMotif());
        $this->assertFalse($penalite->isReglee());
        $this->assertSame($pret, $penalite->getPret());
        $this->assertSame('disponible', $pret->getExemplaire()->getStatut());
    }

    public function testLeTauxDependDeLaFormule(): void
    {
        $pret = $this->pret($this->adherent($this->formule('0.30')), '2026-11-20 10:00:00');
        $this->assertSame('1.50', $this->service()->retourner($pret)->getMontant());
    }

    public function testLaFormuleDuDebutDuPretFaitFoi(): void
    {
        $adherent = new Adherent();
        foreach ([
            ['0.50', '2026-01-01', '2026-11-10'],
            ['0.10', '2026-11-11', '2026-12-31'],
        ] as [$taux, $debut, $fin]) {
            $adherent->addAbonnement(
                (new Abonnement())
                    ->setFormule($this->formule($taux))
                    ->setDateDebut(new \DateTimeImmutable($debut))
                    ->setDateFin(new \DateTimeImmutable($fin))
            );
        }
        $pret = $this->pret($adherent, '2026-11-20 10:00:00', '2026-11-05 10:00:00');
        $this->assertSame('2.50', $this->service()->retourner($pret)->getMontant());
    }

    public function testRefuseUnPretDejaClos(): void
    {
        $pret = $this->pret($this->adherent($this->formule('0.50')), '2026-12-01 10:00:00')
            ->setDateRetour(new \DateTimeImmutable('2026-11-24 10:00:00'));
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('déjà clôturé');
        $this->service()->retourner($pret);
    }

    public function testUnRetourNEstJamaisBloqueSansFormule(): void
    {
        $pret = $this->pret(new Adherent(), '2026-11-20 10:00:00');
        $penalite = $this->service()->retourner($pret);
        $this->assertNull($penalite);
        $this->assertNotNull($pret->getDateRetour());
        $this->assertSame('disponible', $pret->getExemplaire()->getStatut());
    }

    public function testLesChangementsSontEnregistresUneFois(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $pret = $this->pret($this->adherent($this->formule('0.50')), '2026-12-01 10:00:00');
        $this->service($em)->retourner($pret);
    }

    public function testRienNEstEnregistreSiLePretEstDejaClos(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $pret = $this->pret($this->adherent($this->formule('0.50')), '2026-12-01 10:00:00')
            ->setDateRetour(new \DateTimeImmutable('2026-11-24 10:00:00'));
        try {
            $this->service($em)->retourner($pret);
            $this->fail('Une RegleMetierException était attendue.');
        } catch (RegleMetierException) {
            $this->addToAssertionCount(1);
        }
    }
}
