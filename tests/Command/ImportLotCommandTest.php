<?php

namespace App\Tests\Command;

use App\Command\ImportLotCommand;
use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Entity\Site;
use App\Service\ImportLivreService;
use App\Service\ImportLotService;
use App\Service\IsbnNormalizer;
use App\Service\OpenLibraryClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportLotCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private array $fichiersTemp = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $this->nettoyerDonneesTest();
    }

    protected function tearDown(): void
    {
        foreach ($this->fichiersTemp as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        $this->nettoyerDonneesTest();
        $this->em->clear();
        parent::tearDown();
    }

    private function nettoyerDonneesTest(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement("DELETE FROM exemplaire WHERE code_barres LIKE 'EX-%'");
        $conn->executeStatement("DELETE FROM contribution WHERE oeuvre_id IN (SELECT id FROM oeuvre WHERE titre LIKE '%Test Lot Cmd%')");
        $conn->executeStatement("DELETE FROM edition WHERE isbn IN ('9780306406157', '9780452284234')");
        $conn->executeStatement("DELETE FROM oeuvre WHERE titre LIKE '%Test Lot Cmd%'");
        $conn->executeStatement("DELETE FROM personne WHERE nom IN ('LotCmdAuteur')");
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function creerCsvTemp(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'test_cmd_csv_') . '.csv';
        file_put_contents($chemin, $contenu);
        $this->fichiersTemp[] = $chemin;

        return $chemin;
    }

    private function creerCommandTester(MockHttpClient $mockClient): CommandTester
    {
        $importLivreService = new ImportLivreService(
            new IsbnNormalizer(),
            new OpenLibraryClient($mockClient),
            $this->em,
            $this->em->getRepository(Oeuvre::class),
            $this->em->getRepository(Edition::class),
            $this->em->getRepository(Personne::class),
            $this->em->getRepository(Contribution::class),
        );

        $importLotService = new ImportLotService(
            $importLivreService,
            $this->em->getRepository(Site::class),
            $this->em,
        );

        $command = new ImportLotCommand($importLotService);

        return new CommandTester($command);
    }

    public function testExecutionNominaleAfficheRapportEtSucces(): void
    {
        $book = json_encode([
            'title' => 'Livre Test Lot Cmd Un',
            'publishers' => ['Editeur Cmd'],
            'publish_date' => '2022',
            'authors' => [['name' => 'Jean LotCmdAuteur']],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($book, ['http_code' => 200]),
        ]);

        $csv = "isbn\n978-0-306-40615-7\n";
        $fichier = $this->creerCsvTemp($csv);

        $tester = $this->creerCommandTester($mockClient);
        $statusCode = $tester->execute(['fichier' => $fichier]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("Rapport d'exécution", $display);
        $this->assertStringContainsString('Total lignes', $display);
        $this->assertStringContainsString('Succès', $display);
        $this->assertStringContainsString('Importation terminée : 1 document(s) importé(s) avec succès', $display);
    }

    public function testExecutionAvecErreursPartiellesAfficheDetail(): void
    {
        $book = json_encode([
            'title' => 'Livre Test Lot Cmd Un',
            'publishers' => ['Editeur Cmd'],
            'publish_date' => '2022',
            'authors' => [['name' => 'Jean LotCmdAuteur']],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($book, ['http_code' => 200]),
        ]);

        $csv = "isbn\n978-0-306-40615-7\n978-invalide\n";
        $fichier = $this->creerCsvTemp($csv);

        $tester = $this->creerCommandTester($mockClient);
        $statusCode = $tester->execute(['fichier' => $fichier]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Détail des erreurs rencontrées', $display);
        $this->assertStringContainsString('978-invalide', $display);
        $this->assertStringContainsString('1 document(s) importé(s) avec succès (1 erreur(s))', $display);
    }

    public function testExecutionModeDryRunAfficheAvertissement(): void
    {
        $book = json_encode([
            'title' => 'Livre Test Lot Cmd Un',
            'publishers' => ['Editeur Cmd'],
            'publish_date' => '2022',
            'authors' => [['name' => 'Jean LotCmdAuteur']],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($book, ['http_code' => 200]),
        ]);

        $csv = "isbn\n978-0-306-40615-7\n";
        $fichier = $this->creerCsvTemp($csv);

        $tester = $this->creerCommandTester($mockClient);
        $statusCode = $tester->execute([
            'fichier' => $fichier,
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Mode simulation activé (--dry-run)', $display);
        $this->assertStringContainsString('Simulation terminée', $display);

        // Vérifier que rien n'a été inséré
        $this->em->clear();
        $edition = $this->em->getRepository(Edition::class)->findOneBy(['isbn' => '9780306406157']);
        $this->assertNull($edition);
    }

    public function testEchecSiFichierInexistant(): void
    {
        $mockClient = new MockHttpClient([]);
        $tester = $this->creerCommandTester($mockClient);

        $statusCode = $tester->execute(['fichier' => 'fichier_inexistant.csv']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('introuvable ou illisible', $tester->getDisplay());
    }
}
