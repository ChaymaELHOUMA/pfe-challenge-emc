<?php

namespace App\DataFixtures;

use App\Entity\Adherent;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Mot de passe de développement uniquement — ne JAMAIS utiliser en production.
     */
    public const DEV_PASSWORD = 'password123';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getDependencies(): array
    {
        return [AdherentFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Compte Bibliothécaire (accès back-office /admin)
        $bibliothecaire = (new User())
            ->setEmail('bibliothecaire@mediatheque.test')
            ->setRoles(['ROLE_BIBLIOTHECAIRE']);
        $bibliothecaire->setPassword(
            $this->passwordHasher->hashPassword($bibliothecaire, self::DEV_PASSWORD)
        );
        $manager->persist($bibliothecaire);
        $this->addReference('user_bibliothecaire', $bibliothecaire);

        // 2. Un compte User pour chaque adhérent (accès /mon-espace)
        for ($i = 1; $i <= 20; $i++) {
            /** @var Adherent $adherent */
            $adherent = $this->getReference('adherent_' . $i, Adherent::class);

            $user = (new User())
                ->setEmail($adherent->getEmail())
                ->setRoles(['ROLE_ADHERENT'])
                ->setAdherent($adherent);

            $user->setPassword(
                $this->passwordHasher->hashPassword($user, self::DEV_PASSWORD)
            );

            $manager->persist($user);
            $this->addReference('user_adherent_' . $i, $user);
        }

        $manager->flush();
    }
}
