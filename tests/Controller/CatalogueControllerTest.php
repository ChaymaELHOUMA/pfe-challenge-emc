<?php

namespace App\Tests\Controller;

use App\Entity\Edition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogueControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    private function getUneEdition(): Edition
    {
        $edition = $this->em->getRepository(Edition::class)->findOneBy([]);
        $this->assertNotNull($edition, 'Au moins une édition doit être présente en base.');

        return $edition;
    }

    private function getAdherentUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.adherent IS NOT NULL')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $this->assertNotNull($user, 'Un utilisateur adhérent doit exister en base.');

        return $user;
    }

    public function testCatalogueAccessiblePubliquement(): void
    {
        $this->client->request('GET', '/catalogue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertSelectorTextContains('h1', 'Catalogue');
    }

    public function testRechercheAvecResultat(): void
    {
        $this->client->request('GET', '/catalogue', ['q' => 'Petit Prince']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Le Petit Prince');
    }

    public function testRechercheSansResultat(): void
    {
        $this->client->request('GET', '/catalogue', ['q' => 'InexistantTerme99999XYZ']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Aucun document ne correspond');
    }

    public function testFiltreParType(): void
    {
        $this->client->request('GET', '/catalogue', ['type' => 'bd']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.catalogue-item');
        $this->assertSelectorTextContains('.catalogue-item', 'BD');
    }

    public function testFiltreParLangue(): void
    {
        $this->client->request('GET', '/catalogue', ['langue' => 'fr']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.catalogue-item');
    }

    public function testFiltreParDisponibilite(): void
    {
        $this->client->request('GET', '/catalogue', ['disponible' => '1']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.badge-success');
    }

    public function testPagination(): void
    {
        $crawler = $this->client->request('GET', '/catalogue', ['page' => 1]);
        $this->assertResponseIsSuccessful();

        // Vérifie qu'il y a 10 éléments par page
        $items = $crawler->filter('.catalogue-item');
        $this->assertLessThanOrEqual(10, $items->count());

        // Navigation vers la page 2 si plusieurs pages existent
        if ($crawler->filter('.pagination')->count() > 0) {
            $this->client->request('GET', '/catalogue', ['page' => 2]);
            $this->assertResponseIsSuccessful();
        }
    }

    public function testAccesFicheDetail(): void
    {
        $edition = $this->getUneEdition();

        $this->client->request('GET', '/catalogue/' . $edition->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $edition->getOeuvre()->getTitre());
        $this->assertSelectorExists('.exemplaire-row');
        $this->assertSelectorTextContains('body', 'Exemplaires physiques');
    }

    public function testBoutonReserverAnonymeRedirigeVersLogin(): void
    {
        $edition = $this->getUneEdition();

        $crawler = $this->client->request('GET', '/catalogue/' . $edition->getId());
        $this->assertResponseIsSuccessful();

        $lien = $crawler->selectLink('Réserver')->link();
        $this->client->click($lien);

        $this->assertResponseRedirects('/login');
    }

    public function testBoutonReserverAdherentConnecte(): void
    {
        $edition = $this->getUneEdition();
        $user = $this->getAdherentUser();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/catalogue/' . $edition->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#btn-reserver');

        // Soumission du formulaire de réservation
        $form = $crawler->selectButton('Réserver')->form();
        $this->client->submit($form);

        // Doit rediriger vers la fiche détail avec un message flash (succès ou règle métier)
        $this->assertResponseRedirects('/catalogue/' . $edition->getId());
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert');
    }
}
