<?php

namespace App\Tests\Service;

use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Entity\Site;
use App\Exception\RegleMetierException;
use App\Service\ImportLivreService;
use App\Service\ImportLotService;
use App\Service\IsbnNormalizer;
use App\Service\OpenLibraryClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportLotServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private array $fichiersTemp = [];
    private Site $siteTest;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $this->nettoyerDonneesTest();

        // Récupérer ou créer un site de test
        $siteRepo = $this->em->getRepository(Site::class);
        $site = $siteRepo->findOneBy(['nom' => 'Site Lot Test']);
        if ($site === null) {
            $site = (new Site())->setNom('Site Lot Test')->setAdresse('10 rue des Tests');
            $this->em->persist($site);
            $this->em->flush();
        }
        $this->siteTest = $site;
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
        $conn->executeStatement("DELETE FROM contribution WHERE oeuvre_id IN (SELECT id FROM oeuvre WHERE titre LIKE '%Test Lot%')");
        $conn->executeStatement("DELETE FROM edition WHERE isbn IN ('9780306406157', '9780452284234')");
        $conn->executeStatement("DELETE FROM oeuvre WHERE titre LIKE '%Test Lot%'");
        $conn->executeStatement("DELETE FROM personne WHERE nom IN ('LotAuteurUn', 'LotAuteurDeux')");
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function creerCsvTemp(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'test_csv_') . '.csv';
        file_put_contents($chemin, $contenu);
        $this->fichiersTemp[] = $chemin;

        return $chemin;
    }

    private function creerService(MockHttpClient $mockClient): ImportLotService
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

        return new ImportLotService(
            $importLivreService,
            $this->em->getRepository(Site::class),
            $this->em,
        );
    }

    public function testImportLotValideAvecExemplaires(): void
    {
        $book1 = json_encode([
            'title' => 'Livre Test Lot Un',
            'publishers' => ['Editeur Lot'],
            'publish_date' => '2020',
            'authors' => [['name' => 'Paul LotAuteurUn']],
        ], JSON_THROW_ON_ERROR);

        $book2 = json_encode([
            'title' => 'Livre Test Lot Deux',
            'publishers' => ['Editeur Lot'],
            'publish_date' => '2021',
            'authors' => [['name' => 'Anne LotAuteurDeux']],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($book1, ['http_code' => 200]),
            new MockResponse($book2, ['http_code' => 200]),
        ]);

        $csv = "isbn,site,quantite\n";
        $csv .= "978-0-306-40615-7,\"Site Lot Test\",2\n";
        $csv .= "978-0-452-28423-4,\"Site Lot Test\",1\n";

        $fichier = $this->creerCsvTemp($csv);
        $service = $this->creerService($mockClient);

        $rapport = $service->importerFichier($fichier, false);

        $this->assertSame(2, $rapport->totalLignes);
        $this->assertSame(2, $rapport->nbSucces);
        $this->assertSame(0, $rapport->nbEchecs);
        $this->assertFalse($rapport->aDesEchecs());

        // Vérification des exemplaires créés
        $ed1 = $this->em->getRepository(Edition::class)->findOneBy(['isbn' => '9780306406157']);
        $this->assertNotNull($ed1);
        $this->assertCount(2, $ed1->getExemplaires());

        $ed2 = $this->em->getRepository(Edition::class)->findOneBy(['isbn' => '9780452284234']);
        $this->assertNotNull($ed2);
        $this->assertCount(1, $ed2->getExemplaires());
    }

    public function testImportLotAvecErreursPartiellesContinueEtRapporte(): void
    {
        $bookValide = json_encode([
            'title' => 'Livre Test Lot Un',
            'publishers' => ['Editeur Lot'],
            'publish_date' => '2020',
            'authors' => [['name' => 'Paul LotAuteurUn']],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookValide, ['http_code' => 200]),
            new MockResponse('{"error": "notfound"}', ['http_code' => 404]),
        ]);

        $csv = "isbn,site,quantite\n";
        $csv .= "978-0-306-40615-7,\"Site Lot Test\",1\n"; // Ligne 2 : Valide
        $csv .= "978-0-306-40615-9,\"Site Lot Test\",1\n"; // Ligne 3 : Clé invalide
        $csv .= "978-0-000-00000-2,\"Site Lot Test\",1\n"; // Ligne 4 : 404 Open Library
        $csv .= "\n";                                      // Ligne 5 : Vide (ignorée)
        $csv .= "format-invalide,\"Site Lot Test\",1\n";   // Ligne 6 : Longueur invalide

        $fichier = $this->creerCsvTemp($csv);
        $service = $this->creerService($mockClient);

        $rapport = $service->importerFichier($fichier, false);

        $this->assertSame(4, $rapport->totalLignes);
        $this->assertSame(1, $rapport->nbSucces);
        $this->assertSame(3, $rapport->nbEchecs);
        $this->assertTrue($rapport->aDesEchecs());
        $this->assertCount(3, $rapport->echecs);

        $this->assertSame(3, $rapport->echecs[0]['ligne']);
        $this->assertStringContainsString('clé de contrôle', $rapport->echecs[0]['erreur']);

        $this->assertSame(4, $rapport->echecs[1]['ligne']);
        $this->assertStringContainsString('Aucun document trouvé', $rapport->echecs[1]['erreur']);

        $this->assertSame(6, $rapport->echecs[2]['ligne']);
        $this->assertStringContainsString('doit comporter 10 ou 13 caractères', $rapport->echecs[2]['erreur']);
    }

    public function testOptionDryRunNePersisteRien(): void
    {
        $book = json_encode([
            'title' => 'Livre Test Lot Un',
            'publishers' => ['Editeur Lot'],
            'publish_date' => '2020',
            'authors' => [['name' => 'Paul LotAuteurUn']],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($book, ['http_code' => 200]),
        ]);

        $csv = "isbn,site,quantite\n";
        $csv .= "978-0-306-40615-7,\"Site Lot Test\",2\n";

        $fichier = $this->creerCsvTemp($csv);
        $service = $this->creerService($mockClient);

        $rapport = $service->importerFichier($fichier, true); // dryRun = true

        $this->assertTrue($rapport->dryRun);
        $this->assertSame(1, $rapport->nbSucces);
        $this->assertSame(0, $rapport->nbEchecs);

        // Rien n'a été persisté en base !
        $this->em->clear();
        $edition = $this->em->getRepository(Edition::class)->findOneBy(['isbn' => '9780306406157']);
        $this->assertNull($edition);

        $oeuvre = $this->em->getRepository(Oeuvre::class)->findOneBy(['titre' => 'Livre Test Lot Un']);
        $this->assertNull($oeuvre);
    }

    public function testRefuseFichierInexistant(): void
    {
        $mockClient = new MockHttpClient([]);
        $service = $this->creerService($mockClient);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('introuvable ou illisible');
        $service->importerFichier('/fichier/introuvable.csv');
    }

    public function testRefuseFichierSansColonneIsbn(): void
    {
        $mockClient = new MockHttpClient([]);
        $service = $this->creerService($mockClient);

        $csv = "titre,auteur,editeur\nTest,Auteur,Editeur\n";
        $fichier = $this->creerCsvTemp($csv);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage("doit obligatoirement comporter une colonne 'isbn'");
        $service->importerFichier($fichier);
    }
}
