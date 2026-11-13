<?php

namespace App\Tests\Service;

use App\Entity\Contribution;
use App\Entity\Edition;
use App\Entity\Oeuvre;
use App\Entity\Personne;
use App\Exception\RegleMetierException;
use App\Service\ImportLivreService;
use App\Service\IsbnNormalizer;
use App\Service\OpenLibraryClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportLivreServiceTest extends KernelTestCase
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

    private function creerService(MockHttpClient $mockHttpClient): ImportLivreService
    {
        return new ImportLivreService(
            new IsbnNormalizer(),
            new OpenLibraryClient($mockHttpClient),
            $this->em,
            $this->em->getRepository(Oeuvre::class),
            $this->em->getRepository(Edition::class),
            $this->em->getRepository(Personne::class),
            $this->em->getRepository(Contribution::class),
        );
    }

    public function testImportNominalCreeOeuvreEditionPersonneContribution(): void
    {
        $bookJson = json_encode([
            'title' => 'Roman Test Unique Un',
            'publishers' => ['Editions Alpha Unique'],
            'publish_date' => '2019',
            'authors' => [
                ['name' => 'Pierre MartinTest'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookJson, ['http_code' => 200]),
        ]);

        $service = $this->creerService($mockClient);
        $edition = $service->importerParIsbn('978-2-07-036002-4');

        $this->assertSame('9782070360024', $edition->getIsbn());
        $this->assertSame('Editions Alpha Unique', $edition->getEditeur());
        $this->assertSame(2019, $edition->getAnnee());

        $oeuvre = $edition->getOeuvre();
        $this->assertNotNull($oeuvre);
        $this->assertSame('Roman Test Unique Un', $oeuvre->getTitre());
        $this->assertSame('livre', $oeuvre->getTypeOeuvre());

        $contributions = $oeuvre->getContributions();
        $this->assertCount(1, $contributions);

        $contribution = $contributions->first();
        $this->assertSame('auteur', $contribution->getRole());

        $personne = $contribution->getPersonne();
        $this->assertNotNull($personne);
        $this->assertSame('MartinTest', $personne->getNom());
        $this->assertSame('Pierre', $personne->getPrenom());
    }

    public function testIdempotenceImportNeCreePasDeDoublons(): void
    {
        $bookJson = json_encode([
            'title' => 'Roman Test Unique Deux',
            'publishers' => ['Editions Beta Unique'],
            'publish_date' => '2020',
            'authors' => [
                ['name' => 'Marie DurandTest'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookJson, ['http_code' => 200]),
            new MockResponse($bookJson, ['http_code' => 200]),
        ]);

        $service = $this->creerService($mockClient);

        // Premier import
        $edition1 = $service->importerParIsbn('978-0-441-17271-9');

        // Deuxième import identique
        $edition2 = $service->importerParIsbn('9780441172719');

        $this->assertSame($edition1->getId(), $edition2->getId());

        // Vérification en base qu'il n'y a pas de doublon
        $editions = $this->em->getRepository(Edition::class)->findBy(['isbn' => '9780441172719']);
        $this->assertCount(1, $editions);

        $oeuvres = $this->em->getRepository(Oeuvre::class)->findBy(['titre' => 'Roman Test Unique Deux']);
        $this->assertCount(1, $oeuvres);

        $personnes = $this->em->getRepository(Personne::class)->findBy(['nom' => 'DurandTest', 'prenom' => 'Marie']);
        $this->assertCount(1, $personnes);

        $contributions = $this->em->getRepository(Contribution::class)->findBy(['oeuvre' => $oeuvres[0]]);
        $this->assertCount(1, $contributions);
    }

    public function testImportEchoueSiLivreNonTrouve(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"error": "notfound"}', ['http_code' => 404]),
        ]);

        $service = $this->creerService($mockClient);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('Aucun document trouvé sur Open Library');
        $service->importerParIsbn('9782070360024');
    }

    public function testImportEchoueSiIsbnInvalide(): void
    {
        $mockClient = new MockHttpClient([]);
        $service = $this->creerService($mockClient);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('doit comporter 10 ou 13 caractères');
        $service->importerParIsbn('invalid-isbn');
    }
}
