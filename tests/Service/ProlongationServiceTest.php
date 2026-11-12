<?php

namespace App\Tests\Service;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Formule;
use App\Entity\Pret;
use App\Exception\RegleMetierException;
use App\Repository\ReservationRepository;
use App\Service\ProlongationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class ProlongationServiceTest extends TestCase
{
    private const MAINTENANT = '2026-11-25 09:00:00';

    private function formule(int $duree = 21, int $maxProlongations = 2): Formule
    {
        return (new Formule())
            ->setNom('Test')
            ->setDureePretJours($duree)
            ->setMaxProlongations($maxProlongations);
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

    private function pret(
        string $echeance = '2026-11-30 10:00:00',
        int $nbProlongations = 0,
        ?string $retour = null,
        ?Formule $formule = null
    ): Pret {
        $adherent = $this->adherent($formule ?? $this->formule());
        $exemplaire = (new Exemplaire())
            ->setEdition(new Edition())
            ->setStatut('emprunte');

        $pret = (new Pret())
            ->setAdherent($adherent)
            ->setExemplaire($exemplaire)
            ->setDateDebut(new \DateTimeImmutable('2026-11-01 10:00:00'))
            ->setDateEcheance(new \DateTimeImmutable($echeance))
            ->setNbProlongations($nbProlongations);

        if ($retour !== null) {
            $pret->setDateRetour(new \DateTimeImmutable($retour));
        }

        return $pret;
    }

    private function service(
        int $enAttente = 0,
        ?EntityManagerInterface $em = null,
        string $now = self::MAINTENANT
    ): ProlongationService {
        $repo = $this->createStub(ReservationRepository::class);
        $repo->method('countEnAttentePourEdition')->willReturn($enAttente);

        return new ProlongationService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $repo,
            new MockClock($now)
        );
    }

    public function testProlongationAcceptee(): void
    {
        $pret = $this->pret('2026-11-30 10:00:00', 0);
        $service = $this->service();

        $service->prolonger($pret);

        $this->assertSame(1, $pret->getNbProlongations());
        // 2026-11-30 + 21 jours = 2026-12-21
        $this->assertEquals(new \DateTimeImmutable('2026-12-21 10:00:00'), $pret->getDateEcheance());
    }

    public function testRefuseSiPretEnRetard(): void
    {
        $pret = $this->pret('2026-11-20 10:00:00'); // échéance dépassée par rapport à MAINTENANT (25/11)
        $service = $this->service();

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('en retard');

        $service->prolonger($pret);
    }

    public function testRefuseSiPretDejaClos(): void
    {
        $pret = $this->pret('2026-11-30 10:00:00', 0, '2026-11-24 10:00:00');
        $service = $this->service();

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('déjà clôturé');

        $service->prolonger($pret);
    }

    public function testRefuseSiPlafondProlongationsAtteint(): void
    {
        $pret = $this->pret('2026-11-30 10:00:00', 2); // max 2 déjà atteint
        $service = $this->service();

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('maximum');

        $service->prolonger($pret);
    }

    public function testRefuseSiDocumentReserve(): void
    {
        $pret = $this->pret('2026-11-30 10:00:00', 0);
        $service = $this->service(enAttente: 1);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('réservé');

        $service->prolonger($pret);
    }

    public function testRienNEstEnregistreQuandLaProlongationEstRefusee(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $repo = $this->createStub(ReservationRepository::class);
        $repo->method('countEnAttentePourEdition')->willReturn(2);

        $service = new ProlongationService($em, $repo, new MockClock('2026-11-25 09:00:00'));

        try {
            $service->prolonger($this->pret());
            $this->fail('Une RegleMetierException était attendue.');
        } catch (RegleMetierException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testLaProlongationEstEnregistreeUneSeuleFois(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $repo = $this->createStub(ReservationRepository::class);
        $repo->method('countEnAttentePourEdition')->willReturn(0);

        (new ProlongationService($em, $repo, new MockClock('2026-11-25 09:00:00')))->prolonger($this->pret());
    }
}
