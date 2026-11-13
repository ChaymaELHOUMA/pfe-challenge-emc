<?php

namespace App\Tests\Service;

use App\Exception\RegleMetierException;
use App\Service\OpenLibraryClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenLibraryClientTest extends TestCase
{
    public function testCasNominalAvecAuteurEtEditeur(): void
    {
        $bookJson = json_encode([
            'title' => 'Le Petit Prince',
            'publishers' => ['Gallimard'],
            'publish_date' => 'April 1943',
            'languages' => [['key' => '/languages/fre']],
            'authors' => [
                ['name' => 'Antoine de Saint-Exupéry'],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookJson, ['http_code' => 200]),
        ]);

        $client = new OpenLibraryClient($mockClient);
        $livre = $client->getLivreParIsbn('9780156012195');

        $this->assertNotNull($livre);
        $this->assertSame('9780156012195', $livre['isbn']);
        $this->assertSame('Le Petit Prince', $livre['titre']);
        $this->assertSame('Gallimard', $livre['editeur']);
        $this->assertSame(1943, $livre['annee']);
        $this->assertSame('fr', $livre['langue']);
        $this->assertSame(['Antoine de Saint-Exupéry'], $livre['auteurs']);
    }

    public function testCasAvecCleAuteur(): void
    {
        $bookJson = json_encode([
            'title' => 'Vol de nuit',
            'publishers' => ['Gallimard'],
            'publish_date' => '1931',
            'authors' => [
                ['key' => '/authors/OL2623447A'],
            ],
        ], JSON_THROW_ON_ERROR);

        $authorJson = json_encode([
            'name' => 'Antoine de Saint-Exupéry',
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookJson, ['http_code' => 200]),
            new MockResponse($authorJson, ['http_code' => 200]),
        ]);

        $client = new OpenLibraryClient($mockClient);
        $livre = $client->getLivreParIsbn('9782070360024');

        $this->assertNotNull($livre);
        $this->assertSame('Vol de nuit', $livre['titre']);
        $this->assertSame(['Antoine de Saint-Exupéry'], $livre['auteurs']);
    }

    public function testCas404RetourneNull(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"error": "notfound"}', ['http_code' => 404]),
        ]);

        $client = new OpenLibraryClient($mockClient);
        $livre = $client->getLivreParIsbn('9780000000000');

        $this->assertNull($livre);
    }

    public function testDonneesPartiellesSansAuteurNiEditeur(): void
    {
        $bookJson = json_encode([
            'title' => 'Ouvrage Anonyme',
        ], JSON_THROW_ON_ERROR);

        $mockClient = new MockHttpClient([
            new MockResponse($bookJson, ['http_code' => 200]),
        ]);

        $client = new OpenLibraryClient($mockClient);
        $livre = $client->getLivreParIsbn('9782070360024');

        $this->assertNotNull($livre);
        $this->assertSame('Ouvrage Anonyme', $livre['titre']);
        $this->assertSame('Éditeur inconnu', $livre['editeur']);
        $this->assertSame(['Auteur inconnu'], $livre['auteurs']);
        $this->assertSame('fr', $livre['langue']);
        $this->assertIsInt($livre['annee']);
    }

    public function testErreurReseauLeveException(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{}', ['http_code' => 500]),
        ]);

        $client = new OpenLibraryClient($mockClient);

        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('erreur HTTP 500');
        $client->getLivreParIsbn('9782070360024');
    }
}
