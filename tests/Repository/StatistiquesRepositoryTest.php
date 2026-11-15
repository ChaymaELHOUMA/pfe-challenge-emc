<?php

namespace App\Tests\Repository;

use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Oeuvre;
use App\Entity\Penalite;
use App\Entity\Pret;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Repository\StatistiquesRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StatistiquesRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StatistiquesRepository $repo;
    private Connection $conn;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em   = self::getContainer()->get('doctrine')->getManager();
        $this->conn = $this->em->getConnection();
        $this->repo = self::getContainer()->get(StatistiquesRepository::class);

        // Nettoie les tables concernées (ordre FK)
        $this->conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['penalite', 'pret', 'reservation', 'exemplaire', 'edition', 'oeuvre', 'adherent', 'site'] as $t) {
            $this->conn->executeStatement("DELETE FROM $t");
        }
        $this->conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $application->run(new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--env' => 'test',
        ]), new \Symfony\Component\Console\Output\NullOutput());
        parent::tearDownAfterClass();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────

    private function site(string $nom = 'Site Test'): Site
    {
        $s = (new Site())->setNom($nom)->setAdresse('1 rue Test');
        $this->em->persist($s);
        return $s;
    }

    private function oeuvre(string $titre = 'Livre'): Oeuvre
    {
        $o = (new Oeuvre())->setTitre($titre . ' ' . uniqid())->setTypeOeuvre('livre');
        $this->em->persist($o);
        return $o;
    }

    private function edition(Oeuvre $oeuvre): Edition
    {
        $e = (new Edition())
            ->setOeuvre($oeuvre)
            ->setEditeur('Éditeur')
            ->setAnnee(2020)
            ->setLangue('fr')
            ->setSupport('papier');
        $this->em->persist($e);
        return $e;
    }

    private function exemplaire(Edition $edition, Site $site, string $statut = 'disponible'): Exemplaire
    {
        $x = (new Exemplaire())
            ->setEdition($edition)
            ->setSite($site)
            ->setCodeBarres('BC-' . uniqid())
            ->setEtat('bon')
            ->setStatut($statut);
        $this->em->persist($x);
        return $x;
    }

    private function adherent(): Adherent
    {
        $a = (new Adherent())
            ->setNom('Nom')
            ->setPrenom('Prénom')
            ->setEmail(uniqid('a') . '@test.local');
        $this->em->persist($a);
        return $a;
    }

    private function pret(
        Adherent $adherent,
        Exemplaire $exemplaire,
        \DateTimeImmutable $echeance,
        ?\DateTimeImmutable $retour = null,
    ): Pret {
        $p = (new Pret())
            ->setAdherent($adherent)
            ->setExemplaire($exemplaire)
            ->setDateDebut(new \DateTimeImmutable('-5 days'))
            ->setDateEcheance($echeance)
            ->setNbProlongations(0);
        if ($retour) {
            $p->setDateRetour($retour);
        }
        $this->em->persist($p);
        return $p;
    }

    private function reservation(Edition $edition, Adherent $adherent, string $statut = Reservation::EN_ATTENTE): Reservation
    {
        $r = (new Reservation())
            ->setAdherent($adherent)
            ->setEdition($edition)
            ->setCreeLe(new \DateTimeImmutable())
            ->setStatut($statut);
        $this->em->persist($r);
        return $r;
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function testNbPretsEnCours(): void
    {
        $site = $this->site();
        $a    = $this->adherent();
        $e    = $this->edition($this->oeuvre());
        $x1   = $this->exemplaire($e, $site, 'emprunte');
        $x2   = $this->exemplaire($e, $site, 'emprunte');

        $this->pret($a, $x1, new \DateTimeImmutable('+10 days'));          // en cours
        $this->pret($a, $x2, new \DateTimeImmutable('+5 days'), new \DateTimeImmutable()); // rendu
        $this->em->flush();

        $this->assertSame(1, $this->repo->nbPretsEnCours());
    }

    public function testNbPretsEnRetard(): void
    {
        $site = $this->site();
        $a    = $this->adherent();
        $e    = $this->edition($this->oeuvre());
        $x1   = $this->exemplaire($e, $site, 'emprunte');
        $x2   = $this->exemplaire($e, $site, 'emprunte');

        $this->pret($a, $x1, new \DateTimeImmutable('-3 days'));   // en retard
        $this->pret($a, $x2, new \DateTimeImmutable('+5 days'));   // dans les délais
        $this->em->flush();

        $today = new \DateTimeImmutable('today');
        $this->assertSame(1, $this->repo->nbPretsEnRetard($today));
    }

    public function testPretsEnCoursBySite(): void
    {
        $siteA = $this->site('Médiathèque A');
        $siteB = $this->site('Médiathèque B');
        $a     = $this->adherent();
        $e     = $this->edition($this->oeuvre());

        $this->pret($a, $this->exemplaire($e, $siteA, 'emprunte'), new \DateTimeImmutable('+10 days'));
        $this->pret($a, $this->exemplaire($e, $siteA, 'emprunte'), new \DateTimeImmutable('+10 days'));
        $this->pret($a, $this->exemplaire($e, $siteB, 'emprunte'), new \DateTimeImmutable('+10 days'));
        $this->em->flush();

        $rows = $this->repo->pretsEnCoursBySite();

        $this->assertCount(2, $rows);
        $this->assertSame('Médiathèque A', $rows[0]['site']);
        $this->assertSame(2, $rows[0]['nb']);
        $this->assertSame(1, $rows[1]['nb']);
    }

    public function testTopDixDocuments(): void
    {
        $site = $this->site();
        $a    = $this->adherent();

        $e1 = $this->edition($this->oeuvre('Populaire'));
        $e2 = $this->edition($this->oeuvre('Rare'));

        // 2 emprunts sur e1, 1 sur e2
        $this->pret($a, $this->exemplaire($e1, $site, 'emprunte'), new \DateTimeImmutable('+10 days'));
        $this->pret($a, $this->exemplaire($e1, $site, 'emprunte'), new \DateTimeImmutable('+10 days'), new \DateTimeImmutable());
        $this->pret($a, $this->exemplaire($e2, $site, 'emprunte'), new \DateTimeImmutable('+10 days'));
        $this->em->flush();

        $top = $this->repo->topDixDocuments();

        $this->assertCount(2, $top);
        $this->assertSame(2, $top[0]['nb']);
        $this->assertStringContainsString('Populaire', $top[0]['titre']);
        $this->assertSame(1, $top[1]['nb']);
    }

    public function testTotalPenalitesImpayees(): void
    {
        $site = $this->site();
        $a    = $this->adherent();
        $e    = $this->edition($this->oeuvre());
        $x    = $this->exemplaire($e, $site);
        $pret = $this->pret($a, $x, new \DateTimeImmutable('+5 days'), new \DateTimeImmutable());
        $this->em->flush();

        // 2 pénalités non réglées + 1 réglée
        $now = new \DateTimeImmutable();
        $pen1 = (new Penalite())->setPret($pret)->setMontant('15.00')->setMotif('retard')->setReglee(false)->setCreeLe($now);
        $pen2 = (new Penalite())->setPret($pret)->setMontant('5.00')->setMotif('abime')->setReglee(false)->setCreeLe($now);
        $pen3 = (new Penalite())->setPret($pret)->setMontant('25.00')->setMotif('perdu')->setReglee(true)->setCreeLe($now);
        $this->em->persist($pen1);
        $this->em->persist($pen2);
        $this->em->persist($pen3);
        $this->em->flush();

        $this->assertSame('20.00', $this->repo->totalPenalitesImpayees());
    }

    public function testReservationsEnAttenteByEdition(): void
    {
        $e1 = $this->edition($this->oeuvre('Très demandé'));
        $e2 = $this->edition($this->oeuvre('Peu demandé'));

        $this->reservation($e1, $this->adherent());
        $this->reservation($e1, $this->adherent());
        $this->reservation($e2, $this->adherent());
        // Annulée : ne compte pas
        $this->reservation($e1, $this->adherent(), Reservation::ANNULEE);
        $this->em->flush();

        $rows = $this->repo->reservationsEnAttenteByEdition();

        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows[0]['nb']);
        $this->assertStringContainsString('Très demandé', $rows[0]['titre']);
        $this->assertSame(1, $rows[1]['nb']);
    }

    public function testNbReservationsEnAttente(): void
    {
        $e = $this->edition($this->oeuvre());
        $this->reservation($e, $this->adherent());
        $this->reservation($e, $this->adherent());
        $this->reservation($e, $this->adherent(), Reservation::ANNULEE);
        $this->em->flush();

        $this->assertSame(2, $this->repo->nbReservationsEnAttente());
    }

    public function testTauxRotation(): void
    {
        $site = $this->site();
        $a = $this->adherent();
        $e = $this->edition($this->oeuvre());

        $x1 = $this->exemplaire($e, $site);
        $x2 = $this->exemplaire($e, $site);

        $this->pret($a, $x1, new \DateTimeImmutable('+10 days'));
        $this->pret($a, $x2, new \DateTimeImmutable('+10 days'));
        $this->em->flush();

        // 2 prets pour 2 exemplaires = taux 1.0
        $this->assertEquals(1.0, $this->repo->tauxRotation());
    }

    public function testDocumentsJamaisEmpruntes(): void
    {
        $site = $this->site();
        $a = $this->adherent();

        $e1 = $this->edition($this->oeuvre('Emprunté'));
        $e2 = $this->edition($this->oeuvre('Jamais emprunté'));

        $x1 = $this->exemplaire($e1, $site);
        $x2 = $this->exemplaire($e2, $site);

        // Seul e1 a un pret
        $this->pret($a, $x1, new \DateTimeImmutable('+10 days'));
        $this->em->flush();

        $jamais = $this->repo->documentsJamaisEmpruntes();

        $this->assertCount(1, $jamais);
        $this->assertStringContainsString('Jamais emprunté', $jamais[0]['titre']);
    }
}
