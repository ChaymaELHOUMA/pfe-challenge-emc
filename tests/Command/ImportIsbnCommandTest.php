<?php

namespace App\Tests\Command;

use App\Command\ImportIsbnCommand;
use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Service\ImportLivreService;
use App\Service\IsbnNormalizer;
use App\Service\OpenLibraryClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportIsbnCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $this->nettoyerDonneesTest();
    }

    protected function tearDown(): void
    {
        $this->nettoyerDonneesTest();
        $this->em->clear();
        parent::tearDown();
    }

    private function nettoyerDonneesTest(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement("DELETE FROM contribution WHERE oeuvre_id IN (SELECT id FROM oeuvre WHERE titre LIKE '%Test Unique%')");
        $conn->executeStatement("DELETE FROM edition WHERE isbn IN ('9780452284234', '9782070360024', '9780441172719')");
        $conn->executeStatement("DELETE FROM oeuvre WHERE titre LIKE '%Test Unique%'");
        $conn->executeStatement("DELETE FROM personne WHERE nom IN ('DupontTest', 'MartinTest', 'DurandTest')");
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testCommandeImportNominalAfficheDetails(): void
    {
        $bookJson = json_encode([
            'title' => 'Livre Test Unique Commande',
            'publishers' => ['Editeur Test Unique'],
            'publish_date' => '2021',
            'authors' => [
                ['name' => 'Jean DupontTest'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookJson, ['http_code' => 200]),
        ]);

        $service = new ImportLivreService(
            new IsbnNormalizer(),
            new OpenLibraryClient($mockClient),
            $this->em,
            $this->em->getRepository(Oeuvre::class),
            $this->em->getRepository(Edition::class),
            $this->em->getRepository(Personne::class),
            $this->em->getRepository(Contribution::class),
        );

        $command = new ImportIsbnCommand($service);
        $tester = new CommandTester($command);

        $statusCode = $tester->execute(['isbn' => '978-0-452-28423-4']);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Livre importé avec succès', $display);
        $this->assertStringContainsString('Livre Test Unique Commande', $display);
        $this->assertStringContainsString('Jean DupontTest', $display);
        $this->assertStringContainsString('Editeur Test Unique', $display);
        $this->assertStringContainsString('2021', $display);
        $this->assertStringContainsString('9780452284234', $display);
    }

    public function testCommandeEchoueSurIsbnInvalide(): void
    {
        $mockClient = new MockHttpClient([]);
        $service = new ImportLivreService(
            new IsbnNormalizer(),
            new OpenLibraryClient($mockClient),
            $this->em,
            $this->em->getRepository(Oeuvre::class),
            $this->em->getRepository(Edition::class),
            $this->em->getRepository(Personne::class),
            $this->em->getRepository(Contribution::class),
        );

        $command = new ImportIsbnCommand($service);
        $tester = new CommandTester($command);

        $statusCode = $tester->execute(['isbn' => 'invalide']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('doit comporter 10 ou 13 caractères', $tester->getDisplay());
    }

    public function testCommandeEchoueSiLivreNonTrouve(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{}', ['http_code' => 404]),
        ]);

        $service = new ImportLivreService(
            new IsbnNormalizer(),
            new OpenLibraryClient($mockClient),
            $this->em,
            $this->em->getRepository(Oeuvre::class),
            $this->em->getRepository(Edition::class),
            $this->em->getRepository(Personne::class),
            $this->em->getRepository(Contribution::class),
        );

        $command = new ImportIsbnCommand($service);
        $tester = new CommandTester($command);

        $statusCode = $tester->execute(['isbn' => '9782070360024']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('Aucun document trouvé sur Open Library', $tester->getDisplay());
    }
}
