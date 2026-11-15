# Guide d'installation et de prise en main

Ce guide présente les instructions pas à pas pour installer, configurer et exécuter l'application **Réseau de médiathèques** (Sujet 4 — PFE Challenge Education Media Company).

L'application est développée avec **Symfony 7.4**, **PHP 8.3** et **MySQL 8.0**. Deux voies d'installation sont proposées :
1. **Environnement Docker (recommandé, rapide et isolé)**
2. **Environnement local (PHP + MySQL natifs)**

---

## 1. Prérequis

### Voie A — Docker
* **Docker Engine** 20.10+
* **Docker Compose** v2 (`docker compose`)

### Voie B — Développement local
* **PHP** 8.2 ou supérieur (PHP 8.3 recommandé)
* **Extensions PHP requises** : `pdo_mysql`, `intl`, `opcache`, `zip`, `ctype`, `iconv`
* **Composer** 2.x
* **MySQL** 8.0 ou supérieur
* **Symfony CLI** (optionnel mais recommandé) ou serveur intégré PHP

---

## 2. Voie 1 : Installation avec Docker (Recommandée)

### 2.1 Cloner le dépôt
```bash
git clone https://github.com/ChaymaELHOUMA/pfe-challenge-emc.git
cd pfe-challenge-emc
```

### 2.2 Démarrer les conteneurs
Cette commande construit l'image de l'application Symfony et démarre le serveur web ainsi que la base MySQL 8.0 :
```bash
docker compose up -d --build
```

### 2.3 Initialiser la base de données et charger les données de test
Le script d'entrée applique automatiquement les migrations au démarrage. Pour charger le jeu de données complet (adhérents, œuvres, éditions, exemplaires, prêts, utilisateurs) :
```bash
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

### 2.4 Exécuter les tests unitaires et fonctionnels
```bash
docker compose exec app php bin/phpunit
```
> Les 135 tests doivent être verts (`OK (135 tests, 378 assertions)`).

### 2.5 Accéder à l'application
* **Catalogue public** : [http://localhost:8000/catalogue](http://localhost:8000/catalogue)
* **Connexion** : [http://localhost:8000/login](http://localhost:8000/login)
* **Espace adhérent** : [http://localhost:8000/mon-espace](http://localhost:8000/mon-espace)
* **Tableau de bord bibliothécaire (EasyAdmin)** : [http://localhost:8000/admin](http://localhost:8000/admin)

### Commandes utiles avec Docker
```bash
# Consulter les journaux en temps réel
docker compose logs -f app

# Ouvrir un terminal dans le conteneur applicatif
docker compose exec app sh

# Arrêter l'environnement
docker compose down
```

---

## 3. Voie 2 : Installation en local (PHP + MySQL)

### 3.1 Cloner et installer les dépendances
```bash
git clone https://github.com/ChaymaELHOUMA/pfe-challenge-emc.git
cd pfe-challenge-emc
composer install
```

### 3.2 Configuration des variables d'environnement

Créer un fichier `.env.local` à la racine pour configurer votre accès MySQL local :
```env
# .env.local
DATABASE_URL="mysql://root:VOTRE_MOT_DE_PASSE@127.0.0.1:3306/mediatheque?serverVersion=8.0&charset=utf8mb4"
```

Créer également un fichier `.env.test.local` pour la base dédiée aux tests :
```env
# .env.test.local
DATABASE_URL="mysql://root:VOTRE_MOT_DE_PASSE@127.0.0.1:3306/mediatheque?serverVersion=8.0&charset=utf8mb4"
```
*(Doctrine ajoutera automatiquement le suffixe `_test` en environnement de test).*

### 3.3 Initialisation de la base de développement
```bash
# 1. Créer la base de données
php bin/console doctrine:database:create

# 2. Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 3. Charger le jeu de fixtures de démonstration
php bin/console doctrine:fixtures:load --no-interaction
```

### 3.4 Initialisation de la base de test
```bash
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate --no-interaction
php bin/console --env=test doctrine:fixtures:load --no-interaction
```

### 3.5 Lancer les tests automatisés
```bash
php bin/phpunit
```

### 3.6 Démarrer le serveur de développement
```bash
# Avec le serveur intégré PHP :
php -S localhost:8000 -t public

# Ou avec le serveur Symfony CLI :
symfony server:start
```

---

## 4. Comptes de test (Environnement de développement)

> **Sécurité** : Ces comptes et mots de passe sont réservés exclusivement aux environnements de développement et de démonstration. Ne jamais les utiliser en production.

Tous les comptes de test partagent le mot de passe de développement documenté : **`password123`**.

| Rôle | Email | Mot de passe | Espace accessible |
|------|-------|--------------|-------------------|
| **Bibliothécaire** | `bibliothecaire@mediatheque.test` | `password123` | Back-office EasyAdmin (`/admin`) |
| **Adhérent n°1** | `adherent1@example.com` | `password123` | Espace personnel (`/mon-espace`) |
| **Adhérent n°2** | `adherent2@example.com` | `password123` | Espace personnel (`/mon-espace`) |
| **Adhérents 3 à 20** | `adherent3@example.com` ... `adherent20@example.com` | `password123` | Espace personnel (`/mon-espace`) |
| **Visiteur anonyme** | *(aucun compte requis)* | — | Catalogue public (`/catalogue`) |

---

## 5. Commandes console disponibles

L'application fournit plusieurs commandes en ligne de commande pour piloter les fonctionnalités métier :

### Import Open Library
```bash
# Importer un livre individuel par son ISBN (10 ou 13 chiffres) :
php bin/console app:import:isbn 9780156012195

# Importer un lot de livres depuis un fichier CSV :
php bin/console app:import:lot data/import-exemple.csv

# Simuler l'import en lot sans écrire en base (dry-run) :
php bin/console app:import:lot data/import-exemple.csv --dry-run
```

### Gestion de la circulation et de la file
```bash
# Enregistrer un emprunt (ID adhérent + code-barres exemplaire) :
php bin/console app:emprunter 1 MED-00001

# Enregistrer un retour physique :
php bin/console app:retourner MED-00001

# Prolonger un prêt en cours (ID du prêt) :
php bin/console app:prolonger 1

# Réserver une édition (ID adhérent + ID édition) :
php bin/console app:reserver 1 1

# Clôturer un prêt avec déclaration de perte ou dégradation :
php bin/console app:declarer 1 perdu
php bin/console app:declarer 1 abime

# Expirer les réservations dont le délai de mise de côté (5 jours) est dépassé :
php bin/console app:reservations:expirer
```
