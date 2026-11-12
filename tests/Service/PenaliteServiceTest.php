<?php

namespace App\Tests\Service;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Formule;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Exception\RegleMetierException;
use App\Service\PenaliteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class PenaliteServiceTest extends TestCase
{
    private function formule(string $taux = '0.50'): Formule
    {
        return (new Formule())
            ->setNom('Test')
            ->setPenaliteParJour($taux);
    }

    private function adherent(Formule $formule): Adherent
    {
        $adherent = new Adherent();
        $adherent->addAbonnement(
            (new Abonnement())
                ->setFormule($formule)
                ->setDateDebut(new \DateTimeImmutable('2026-01-01'))
                ->setDateFin(new \DateTimeImmutable('2026-12-31'))
        );
        return $adherent;
    }

    private function pret(string $echeance, ?string $retour = null, ?Formule $formule = null): Pret
    {
        $adherent = $this->adherent($formule ?? $this->formule());
        $exemplaire = (new Exemplaire())
            ->setEdition(new Edition())
            ->setStatut('emprunte')
            ->setEtat('bon');

        $pret = (new Pret())
            ->setAdherent($adherent)
            ->setExemplaire($exemplaire)
            ->setDateDebut(new \DateTimeImmutable('2026-11-01 10:00:00'))
            ->setDateEcheance(new \DateTimeImmutable($echeance));

        if ($retour !== null) {
            $pret->setDateRetour(new \DateTimeImmutable($retour));
        }

        return $pret;
    }

    private function service(string $now = '2026-11-25 09:00:00', ?EntityManagerInterface $em = null): PenaliteService
    {
        return new PenaliteService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            new MockClock($now)
        );
    }

    public function testAucunePenaliteSiRenduATemps(): void
    {
        $s = $this->service('2026-11-25 09:00:00');
        $pret = $this->pret('2026-11-30 10:00:00');

        $penalite = $s->appliquerRetard($pret, $this->formule('0.50'));

        $this->assertNull($penalite);
    }

    public function testPasDePenaliteLeJourDeLEcheance(): void
    {
        $s = $this->service('2026-11-25 09:00:00');
        $pret = $this->pret('2026-11-25 08:00:00');

        $penalite = $s->appliquerRetard($pret, $this->formule('0.50'));

        $this->assertNull($penalite);
    }

    public function testCalculPenaliteDeRetard(): void
    {
        $s = $this->service('2026-11-25 09:00:00');
        // Échéance le 20/11, retour le 25/11 : 5 jours de retard à 0.50 = 2.50
        $pret = $this->pret('2026-11-20 10:00:00');

        $penalite = $s->appliquerRetard($pret, $this->formule('0.50'));

        $this->assertInstanceOf(Penalite::class, $penalite);
        $this->assertSame('2.50', $penalite->getMontant());
        $this->assertSame('retard', $penalite->getMotif());
        $this->assertFalse($penalite->isReglee());
    }

    public function testDeclarerPerdu(): void
    {
        $s = $this->service('2026-11-25 09:00:00');
        $pret = $this->pret('2026-11-30 10:00:00');

        $penalite = $s->declarerPerdu($pret);

        $this->assertSame('perdu', $pret->getExemplaire()->getStatut());
        $this->assertNotNull($pret->getDateRetour());
        $this->assertSame(PenaliteService::MONTANT_PERDU, $penalite->getMontant());
        $this->assertSame('perdu', $penalite->getMotif());
    }

    public function testDeclarerAbimeRetireLExemplaire(): void
    {
        $s = $this->service('2026-11-25 09:00:00');
        $pret = $this->pret('2026-11-30 10:00:00');
        $penalite = $s->declarerAbime($pret);

        $this->assertSame('abime', $pret->getExemplaire()->getEtat());
        $this->assertSame('retire', $pret->getExemplaire()->getStatut());
        $this->assertNotNull($pret->getDateRetour());
        $this->assertSame(PenaliteService::MONTANT_ABIME, $penalite->getMontant());
        $this->assertSame('abime', $penalite->getMotif());
    }

    public function testDeclarerPerduRefuseSiLePretEstDejaClos(): void
    {
        $s = $this->service('2026-11-25 09:00:00');

        $this->expectException(RegleMetierException::class);
        $s->declarerPerdu($this->pret('2026-11-20 10:00:00', '2026-11-19 10:00:00'));
    }

    public function testDeclarerAbimeRefuseSiLePretEstDejaClos(): void
    {
        $s = $this->service('2026-11-25 09:00:00');

        $this->expectException(RegleMetierException::class);
        $s->declarerAbime($this->pret('2026-11-20 10:00:00', '2026-11-19 10:00:00'));
    }
}
