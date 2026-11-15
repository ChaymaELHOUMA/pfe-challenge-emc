# Réseau de Médiathèques — PFE Challenge

[![Tests](https://img.shields.io/badge/PHPUnit-140%20tests%20%7C%20387%20assertions-success.svg)](tests/)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-blue.svg)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://www.php.net)
[![Database](https://img.shields.io/badge/MySQL-8.0-orange.svg)](https://www.mysql.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED.svg)](compose.yaml)

Application de gestion d'un réseau multi-sites de médiathèques, réalisée dans le cadre du **PFE Challenge d'Education Media Company (Sujet n°4)**.

Ce projet implémente l'ensemble des exigences fonctionnelles et techniques, couvrant les **niveaux 1 à 5** : modélisation du fonds documentaire, règles métier strictes de circulation (prêts, retours, prolongations, pénalités), file d'attente automatisée FIFO, espace public avec catalogue et recherche, espace adhérent sécurisé, back-office d'administration avec statistiques décisionnelles, intégration de l'API externe Open Library (unitaire et par lot), et conteneurisation Docker complète.

---

## Sommaire
* [Architecture et Stack Technique](#architecture-et-stack-technique)
* [Documentation du Projet](#documentation-du-projet)
* [Statut des Réalisations (Niveaux 1 à 5)](#statut-des-réalisations-niveaux-1-à-5)
* [Démarrage Rapide](#démarrage-rapide)
* [Comptes de Démonstration](#comptes-de-démonstration)
* [Suite de Tests Automatisés](#suite-de-tests-automatisés)
* [Commandes Console Métier](#commandes-console-métier)
* [Choix Techniques et Limites Assumées](#choix-techniques-et-limites-assumées)

---

## Architecture et Stack Technique

* **Framework Web :** Symfony 7.4 (PHP 8.3+)
* **Base de données relationnelle :** MySQL 8.0 (InnoDB, encodage UTF-8 multibyte `utf8mb4`)
* **ORM & Migrations :** Doctrine ORM 3.x, Doctrine Migrations 3.x
* **Back-Office :** EasyAdmin Bundle 5.x
* **Moteur de Template :** Twig 3.x
* **Client HTTP :** Symfony HttpClient (sans clé d'API, requêtes vers Open Library)
* **Sécurité & Authentification :** Symfony Security (form_login, CSRF, login_throttling via rate-limiter, hachage bcrypt/argon2id)
* **Conteneurisation :** Docker Engine, Docker Compose v2 (image `php:8.3-cli-alpine`)

### Principes d'architecture logicielle appliqués
* **Séparation stricte des responsabilités :** Contrôleurs fins déléguant systématiquement les règles métier à des services purs injectables.
* **Exceptions métier dédiées :** Levée d'exceptions explicites `RegleMetierException` (sous-classe de `\DomainException`), interceptées pour afficher des messages clairs à l'utilisateur.
* **Temporalité découplée (PSR-20) :** Injection de `ClockInterface` dans tous les calculs de dates et d'échéances pour garantir la testabilité sans dépendance à l'heure système.
* **Intégrité financière :** Tous les montants financiers (tarifs, forfaits, pénalités) sont stockés sous forme de chaînes décimales `DECIMAL(10,2)` sans manipulation de flottants.

---

## Documentation du Projet

La documentation technique détaillée est organisée dans le dossier [`docs/`](docs/) :

| Document | Description |
|----------|-------------|
| [**`docs/INSTALL.md`**](docs/INSTALL.md) | **Guide d'installation pas à pas** : voies Docker et locale (PHP/MySQL), prérequis, configuration de l'environnement, commandes d'initialisation et d'amorçage des fixtures. |
| [**`docs/MODELE.md`**](docs/MODELE.md) | **Modèle de données conceptuel et physique** : dictionnaire des entités, relations, cardinalités, types de champs et contraintes d'intégrité. |
| [**`docs/DECISIONS.md`**](docs/DECISIONS.md) | **Journal d'architecture et réponses aux questions** : journal chronologique daté, justification des choix, réponses aux 5 questions à trancher et bilan des limites assumées. |

---

## Statut des Réalisations (Niveaux 1 à 5)

| Niveau | Intitulé | Statut | Fonctionnalités clés développées |
|:------:|:---------|:------:|:---------------------------------|
| **Niveau 1** | Modèle de données & Back-office | Complet | • Entités : `Site`, `Personne`, `Oeuvre`, `Contribution`, `Edition`, `Exemplaire`, `Formule`, `Adherent`, `Abonnement`, `Pret`, `Reservation`, `Penalite`, `User`<br>• Fixtures complètes avec mot de passe de test documenté<br>• Administration EasyAdmin complète (`/admin`) |
| **Niveau 2** | Règles métier & Circulation | Complet | • `EmpruntService` (contrôle validité abonnement, quotas formule, disponibilité exemplaire)<br>• `RetourService` (calcul automatique des retours et pénalités journalières)<br>• `ReservationService` (file d'attente FIFO par édition, plafond de 5)<br>• `MiseDeCoteService` (mise de côté 5 jours au retour, détection expiration)<br>• `ProlongationService` (conditionnée à l'absence de retard, au quota et à la file)<br>• `PenaliteService` (gestion du retard, forfaits perte et dégradation)<br>• Commandes console pour chaque acte métier |
| **Niveau 3** | Espace public, Adhérent & Stats | Complet | • Sécurité : `form_login`, `login_throttling` (5 essais/min), rôles `ROLE_BIBLIOTHECAIRE` et `ROLE_ADHERENT`<br>• Catalogue public (`/catalogue`) : recherche multi-critères, filtres (type, langue, disponibilité), pagination sans N+1<br>• Fiche document (`/catalogue/{id}`) avec disponibilité par site et bouton de réservation direct<br>• Espace personnel adhérent (`/mon-espace`) : consultation des prêts, alertes retard, position en file d'attente, date de retrait, solde des pénalités, actions POST sécurisées CSRF (prolonger, annuler) et contrôle d'accès propriétaire (403 si tentative d'altération du prêt d'un tiers)<br>• Tableau de bord administrateur : statistiques d'activité en temps réel (prêts en cours par site, retards, top 10 des documents, pénalités impayées, réservations par édition) via `StatistiquesRepository` |
| **Niveau 4** | Import Open Library par ISBN | Complet | • `IsbnNormalizer` : nettoyage, validation des clés de contrôle (ISBN-10 modulo 11, ISBN-13 modulo 10) et conversion systématique en ISBN-13<br>• `OpenLibraryClient` : client HttpClient avec timeout (10s), gestion des 404, capture d'auteurs tolérante (`/authors/...` ou `by_statement`) et extraction d'année par regex<br>• `ImportLivreService` : import idempotent évitant les doublons (`Oeuvre`, `Edition`, `Personne`, `Contribution`)<br>• Commande console `app:import:isbn {isbn}` |
| **Niveau 5** | Import en lot & Docker | Complet | • Commande console `app:import:lot {fichier.csv}` : lecture résiliente de fichiers CSV (continue en cas de rejet par ligne), création optionnelle des exemplaires physiques rattachés<br>• Option `--dry-run` : simulation intégrale sans persistance via transaction DBAL annulée<br>• Rapport d'exécution synthétique : total, succès, échecs détaillés par ligne, temps total<br>• Fichier d'exemple `data/import-exemple.csv` panaché (valides, invalides, inconnus)<br>• Environnement Docker complet (`Dockerfile`, `compose.yaml`, healthcheck, script d'entrée avec migrations automatiques) |

---

## Démarrage Rapide

### Option A — Avec Docker (2 minutes)
```bash
# 1. Cloner le projet
git clone https://github.com/ChaymaELHOUMA/pfe-challenge-emc.git
cd pfe-challenge-emc

# 2. Démarrer les conteneurs (app + base de données MySQL 8)
docker compose up -d --build

# 3. Charger le jeu de données de test
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction

# 4. Ouvrir l'application dans votre navigateur :
# http://localhost:8000
```

### Option B — En local (PHP + MySQL)
```bash
# 1. Installer les dépendances Composer
composer install

# 2. Configurer votre connexion MySQL dans .env.local
echo 'DATABASE_URL="mysql://root:password@127.0.0.1:3306/mediatheque?serverVersion=8.0&charset=utf8mb4"' > .env.local

# 3. Créer la base, migrer et charger les fixtures
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction

# 4. Démarrer le serveur web local
php -S localhost:8000 -t public
```

---

## Comptes de Démonstration

Tous les comptes créés par les fixtures partagent le mot de passe de développement unique : **`password123`**.

| Profil | Identifiant (Email) | Mot de passe | Rôle | URL d'accès |
|--------|---------------------|--------------|------|-------------|
| **Bibliothécaire** | `bibliothecaire@mediatheque.test` | `password123` | `ROLE_BIBLIOTHECAIRE` | [http://localhost:8000/admin](http://localhost:8000/admin) |
| **Adhérent n°1** | `adherent1@example.com` | `password123` | `ROLE_ADHERENT` | [http://localhost:8000/mon-espace](http://localhost:8000/mon-espace) |
| **Adhérents 2 à 20** | `adherent2@example.com` ... `adherent20@example.com` | `password123` | `ROLE_ADHERENT` | [http://localhost:8000/mon-espace](http://localhost:8000/mon-espace) |
| **Public anonyme** | *(aucun)* | — | *(visiteur)* | [http://localhost:8000/catalogue](http://localhost:8000/catalogue) |

---

## Suite de Tests Automatisés

Le projet intègre une suite de tests automatisés couvrant les tests unitaires purs, les tests d'intégration avec base de données MySQL, les tests de commandes console et les tests fonctionnels Web (`WebTestCase`) :

```bash
# Lancer la suite de tests complète :
php bin/phpunit
```

### Synthèse des tests (140 tests, 387 assertions, 100% vert) :
* **Tests de Services Métier :**
  * `EmpruntServiceTest` : 16 tests (droits d'emprunt, formules, quotas, blocage retard/pénalités, vérification de non-persistance)
  * `RetourServiceTest` : 11 tests (pénalités de retard, affectation automatique au premier réservataire)
  * `ReservationServiceTest` : 16 tests (file FIFO, quotas, règle locale vs distant, annulation d'attente vs mise de côté)
  * `MiseDeCoteServiceTest` : 12 tests (délai de 5 jours, expiration, libération de document, transfert au suivant)
  * `ProlongationServiceTest` : 7 tests (conditionnement retard, quotas formule, présence de réservation)
  * `PenaliteServiceTest` : 7 tests (forfaits perte 25 MAD, dégradation 5 MAD, calcul retard)
  * `IsbnNormalizerTest` : 8 tests (conversion ISBN-10 vers 13, gestion clé 'X', contrôle modulo 10/11)
  * `OpenLibraryClientTest` : 6 tests (MockHttpClient, 404, données partielles, récupération couverture, erreurs réseau)
  * `ImportLivreServiceTest` : 4 tests (idempotence sans doublon, création complète de l'arbre d'entités)
  * `ImportLotServiceTest` : 5 tests (création d'exemplaires rattachés, continuité sur erreur, dry-run sans persistance)
* **Tests de Repositories SQL / DBAL :**
  * `ReservationRepositoryTest` : 7 tests (ordre chronologique et par ID, calcul de position `#1`, `#2`)
  * `StatistiquesRepositoryTest` : 9 tests (taux de rotation, documents jamais empruntés, retards, top 10, total pénalités)
* **Tests de Contrôleurs Web (WebTestCase) :**
  * `SecurityControllerTest` : 6 tests (redirection anonyme, isolation `/admin`, isolation `/mon-espace`, throttling)
  * `CatalogueControllerTest` : 10 tests (recherche globale, filtres combinés, pagination, réservation connectée)
  * `MonEspaceControllerTest` : 9 tests (affichage des échéances, boutons conditionnels, protection CSRF, isolation hermétique entre adhérents)
* **Tests de Commandes Console :**
  * `ImportIsbnCommandTest` : 3 tests (affichage tableau, gestion des erreurs)
  * `ImportLotCommandTest` : 4 tests (rapport synthétique, erreurs détaillées, simulation `--dry-run`)

---

## Commandes Console Métier

```bash
# Importer un livre individuel par son ISBN :
php bin/console app:import:isbn 9780156012195

# Importer un lot de livres depuis un fichier CSV :
php bin/console app:import:lot data/import-exemple.csv

# Simuler l'import en lot sans écrire en base :
php bin/console app:import:lot data/import-exemple.csv --dry-run

# Expirer les réservations dont la mise de côté a dépassé 5 jours :
php bin/console app:reservations:expirer

# Enregistrer un emprunt ou un retour physique :
php bin/console app:emprunter 1 MED-00001
php bin/console app:retourner MED-00001
```

---

## Choix Techniques et Limites Assumées

Pour une analyse exhaustive des choix et des limites assumées, consultez [`docs/DECISIONS.md`](docs/DECISIONS.md). Les principaux arbitrages sont les suivants :
1. **Pas de passerelle de paiement en ligne :** Les pénalités sont calculées au centime près en MAD et affichées dans l'espace adhérent et le back-office, mais leur règlement effectif s'effectue au guichet physique.
2. **Notifications web sans SMTP externe :** L'adhérent consulte la date limite de mise de côté (5 jours) sur son espace personnel. Aucun serveur SMTP ou passerelle SMS tierce n'est requis pour exécuter le projet.
3. **Pas de navette logistique inter-sites :** La réservation est autorisée si l'exemplaire n'est disponible que sur un autre site, mais le déplacement physique du document n'est pas modélisé.
4. **Appariement exact des titres Open Library :** Deux éditions portant des variantes typographiques de titre forment deux entités `Oeuvre` distinctes, réconciliables manuellement au besoin.
