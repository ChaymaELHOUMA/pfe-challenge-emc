<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    private function createAndPersistUser(string $email, array $roles, string $plainPassword = 'password123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testAnonymeRedirigeVersConnexionPourAdmin(): void
    {
        $this->client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    public function testAnonymeRedirigeVersConnexionPourMonEspace(): void
    {
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseRedirects('/login');
    }

    public function testMauvaisMotDePasseRefuse(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'nonexistent@mediatheque.test',
            '_password' => 'mauvais_mdp',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testAdherentRefuseSurAdmin(): void
    {
        $user = $this->createAndPersistUser('adherent_' . uniqid() . '@test.com', ['ROLE_ADHERENT']);

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testBibliothecaireAccepteSurAdmin(): void
    {
        $user = $this->createAndPersistUser('biblio_' . uniqid() . '@test.com', ['ROLE_BIBLIOTHECAIRE']);

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testAdherentAccepteSurMonEspace(): void
    {
        $user = $this->createAndPersistUser('adherent_' . uniqid() . '@test.com', ['ROLE_ADHERENT']);

        $this->client->loginUser($user);
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
    }
}
