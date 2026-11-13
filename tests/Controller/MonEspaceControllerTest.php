<?php

namespace App\Tests\Controller;

use App\Entity\Abonnement;
use App\Entity\Adherent;
use App\Entity\Edition;
use App\Entity\Exemplaire;
use App\Entity\Formule;
use App\Entity\Oeuvre;
use App\Entity\Pret;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MonEspaceControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    private function createSite(): Site
    {
        $site = (new Site())
            ->setNom('Site ' . uniqid())
            ->setAdresse('Adresse ' . uniqid());
        $this->em->persist($site);

        return $site;
    }

    private function createFormule(): Formule
    {
        $formule = (new Formule())
            ->setNom('Formule ' . uniqid())
            ->setMaxPrets(10)
            ->setDureePretJours(21)
            ->setMaxProlongations(2)
            ->setPenaliteParJour('1.00')
            ->setTarifAnnuel('20.00');
        $this->em->persist($formule);

        return $formule;
    }

    private function createAdherentUser(): array
    {
        $site = $this->createSite();
        $formule = $this->createFormule();

        $adherent = (new Adherent())
            ->setNom('Nom' . uniqid())
            ->setPrenom('Prenom')
            ->setEmail('adherent_' . uniqid() . '@example.test')
            ->setSiteRattachement($site);
        $this->em->persist($adherent);

        // addAbonnement gère la liaison bidirectionnelle
        $abonnement = (new Abonnement())
            ->setFormule($formule)
            ->setDateDebut(new \DateTimeImmutable('-30 days'))
            ->setDateFin(new \DateTimeImmutable('+335 days'));
        $this->em->persist($abonnement);
        $adherent->addAbonnement($abonnement);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new User())
            ->setEmail($adherent->getEmail())
            ->setRoles(['ROLE_ADHERENT'])
            ->setAdherent($adherent);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);

        $this->em->flush();

        return [$user, $adherent];
    }

    private function createExemplaireAvecEdition(): Exemplaire
    {
        $site = $this->createSite();

        $oeuvre = (new Oeuvre())
            ->setTitre('Livre ' . uniqid())
            ->setTypeOeuvre('livre');
        $this->em->persist($oeuvre);

        $edition = (new Edition())
            ->setOeuvre($oeuvre)
            ->setEditeur('Editeur ' . uniqid())
            ->setAnnee(2024)
            ->setLangue('fr')
            ->setSupport('papier');
        $this->em->persist($edition);

        $exemplaire = (new Exemplaire())
            ->setEdition($edition)
            ->setSite($site)
            ->setCodeBarres('MED-' . uniqid())
            ->setEtat('bon')
            ->setStatut('disponible');
        $this->em->persist($exemplaire);

        $this->em->flush();

        return $exemplaire;
    }

    private function createPretEnCours(Adherent $adherent, Exemplaire $exemplaire, \DateTimeImmutable $dateEcheance): Pret
    {
        $pret = (new Pret())
            ->setAdherent($adherent)
            ->setExemplaire($exemplaire)
            ->setDateDebut(new \DateTimeImmutable('-5 days'))
            ->setDateEcheance($dateEcheance)
            ->setNbProlongations(0);
        $exemplaire->setStatut('emprunte');

        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────────

    public function testAccesMonEspaceAnonymeRedirigeVersLogin(): void
    {
        $this->client->request('GET', '/mon-espace');
        $this->assertResponseRedirects('/login');
    }

    public function testAccesMonEspaceConnecte(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon espace personnel');
        $this->assertSelectorTextContains('body', 'Prêts en cours');
        $this->assertSelectorTextContains('body', 'Réservations actives');
        $this->assertSelectorTextContains('body', 'Pénalités dues');
    }

    public function testPretEnCoursAfficheEcheanceEtStatut(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $exemplaire = $this->createExemplaireAvecEdition();
        $this->createPretEnCours($adherent, $exemplaire, new \DateTimeImmutable('+15 days'));

        $this->client->loginUser($user);
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.pret-en-cours-row');
        $this->assertSelectorExists('.badge-success');   // "Dans les délais"
    }

    public function testPretEnRetardAfficheStatutRetard(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $exemplaire = $this->createExemplaireAvecEdition();
        $this->createPretEnCours($adherent, $exemplaire, new \DateTimeImmutable('-3 days'));

        $this->client->loginUser($user);
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.pret-en-cours-row');
        $this->assertSelectorTextContains('.badge-danger', 'En retard');
        // Pas de bouton Prolonger pour un prêt en retard
        $this->assertSelectorNotExists('.btn-prolonger');
    }

    public function testProlongationAccepteeViaFormulaire(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $exemplaire = $this->createExemplaireAvecEdition();
        $pret = $this->createPretEnCours($adherent, $exemplaire, new \DateTimeImmutable('+15 days'));
        $pretId = $pret->getId();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.btn-prolonger');

        // Soumission via le formulaire DOM (token CSRF extrait du HTML)
        $form = $crawler->selectButton('Prolonger')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/mon-espace');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-success');
        $this->assertSelectorTextContains('.alert-success', 'prolongé');

        // Vérifie en DB
        $this->em->clear();
        $pretRefresh = $this->em->find(Pret::class, $pretId);
        $this->assertSame(1, $pretRefresh->getNbProlongations());
    }

    public function testReservationEnAttenteAffichePosition(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $exemplaire = $this->createExemplaireAvecEdition();

        $reservation = (new Reservation())
            ->setAdherent($adherent)
            ->setEdition($exemplaire->getEdition())
            ->setCreeLe(new \DateTimeImmutable())
            ->setStatut(Reservation::EN_ATTENTE);
        $this->em->persist($reservation);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.reservation-row');
        $this->assertSelectorTextContains('.reservation-row', 'En attente');
        $this->assertSelectorTextContains('.reservation-row', '#1');
    }

    public function testReservationDisponibleAfficheDateLimite(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $exemplaire = $this->createExemplaireAvecEdition();
        $exemplaire->setStatut('reserve');

        $reservation = (new Reservation())
            ->setAdherent($adherent)
            ->setEdition($exemplaire->getEdition())
            ->setCreeLe(new \DateTimeImmutable())
            ->setStatut(Reservation::DISPONIBLE)
            ->setMiseDeCoteJusqua(new \DateTimeImmutable('+5 days'));
        $this->em->persist($reservation);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.reservation-row', 'Mis de côté');
        $this->assertSelectorTextContains('.reservation-row', 'À retirer avant');
    }

    public function testAnnulationReservationViaFormulaire(): void
    {
        [$user, $adherent] = $this->createAdherentUser();
        $exemplaire = $this->createExemplaireAvecEdition();

        $reservation = (new Reservation())
            ->setAdherent($adherent)
            ->setEdition($exemplaire->getEdition())
            ->setCreeLe(new \DateTimeImmutable())
            ->setStatut(Reservation::EN_ATTENTE);
        $this->em->persist($reservation);
        $this->em->flush();
        $reservationId = $reservation->getId();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.btn-annuler-reservation');

        // Soumission via le formulaire DOM (token CSRF extrait du HTML)
        $form = $crawler->selectButton('Annuler')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/mon-espace');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-success');
        $this->assertSelectorTextContains('.alert-success', 'annulée');

        $this->em->clear();
        $reservationRefresh = $this->em->find(Reservation::class, $reservationId);
        $this->assertSame(Reservation::ANNULEE, $reservationRefresh->getStatut());
    }

    public function testAdherentANeVoitPasPretsAdherentB(): void
    {
        [$userA, $adherentA] = $this->createAdherentUser();
        [$userB, $adherentB] = $this->createAdherentUser();

        $exemplaire = $this->createExemplaireAvecEdition();
        $this->createPretEnCours($adherentB, $exemplaire, new \DateTimeImmutable('+10 days'));

        $this->client->loginUser($userA);
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
        // Les prêts de B ne doivent pas apparaître dans la vue de A
        $this->assertSelectorNotExists('.pret-en-cours-row');
    }
}
