# Journal de décisions

## 14/11/2026 — Démarrage
- Sujet choisi : **4, réseau de médiathèques**.
- Énoncé reçu et travail commencé le **13/11/2026**. L'historique Git est donc court : premier commit le 13/11/2026.
- Priorité : niveaux 1 à 3 solides, puis niveaux 4 et 5 si le temps le permet.
- Pourquoi ce sujet : règles métier claires (prêts, file de réservation, prolongations, pénalités) et API Open Library sans clé.

## 14/11/2026 — Modèle de données
- Œuvre, Édition et Exemplaire sont trois entités distinctes (voir `docs/MODELE.md`).
- La Formule est stockée en base : limites et pénalités paramétrables.
- La Réservation vise une édition, pas un exemplaire.
- Les montants sont en `decimal`, jamais en `float`.
- Les dates sont injectées via `Clock` dans les services pour rendre les règles testables.

## 14/11/2026 — Règles métier (prévues)
- Prolongation refusée si : prêt clôturé, plafond de la formule atteint, prêt en retard, réservation en attente sur l'édition.
- Retard compté en jours calendaires ; le jour de l'échéance n'est pas en retard.
- Perdu : forfait de 25 €. Abîmé : forfait de 5 € et exemplaire retiré. Ces valeurs sont des choix par défaut, à ajuster.

## 14/11/2026 — File de réservation
- Une réservation vise une **édition** ; l'ordre de la file suit la date de création (puis l'id).
- Refusée si : abonnement expiré, doublon actif, édition déjà en prêt chez l'adhérent, maximum de 5 réservations actives, aucun exemplaire en circulation.
- Le plafond de 5 est une constante de départ (la formule ne porte pas ce paramètre). À rendre paramétrable avec plus de temps.
- Annulation possible uniquement tant que la réservation est « en attente » ; celle d'un document déjà mis de côté sera traitée avec la mise de côté.

## 14/11/2026 — Mise de côté
- Au retour d'un document, le premier de la file est servi ; un adhérent dont l'abonnement a expiré perd son tour (sa réservation passe à « expirée ») et on passe au suivant.
- Les exemplaires d'une même édition sont interchangeables : pour libérer un document, on prend n'importe quel exemplaire « réservé » de l'édition.
- L'annulation d'une réservation déjà mise de côté libère le document de la même façon.
- Le service ne flushe pas dans `affecter()` : c'est l'appelant qui enregistre, pour que retour, mise de côté et pénalité forment une seule opération en base.

## 14/11/2026 — Pénalités, perdu et abîmé
- **Retard** : jours calendaires entre l'échéance et le retour, multipliés par la pénalité journalière de la formule en vigueur **au début du prêt**. Le jour de l'échéance n'est pas un retard.
- **Perdu** : forfait de 25 MAD, prêt clôturé, exemplaire au statut « perdu ». Le forfait remplace la pénalité de retard : la perte est traitée comme un cas à part, pas cumulée.
- **Abîmé** : forfait de 5 MAD, prêt clôturé, exemplaire retiré du catalogue. Les forfaits sont des constantes de `PenaliteService`, à rendre paramétrables avec plus de temps.
- On ne peut déclarer perdu ou abîmé qu'un prêt en cours.
- **Limite assumée** : si le dernier exemplaire d'une édition est perdu alors qu'une file existe, les réservations restent en attente sans que rien ne les clôture. La réservation refuse déjà d'accepter une édition sans exemplaire en circulation, mais ne purge pas l'existant.
- Les pénalités n'empêchent pas un nouvel emprunt (voir règles d'emprunt). Décision à reconsidérer avec la médiathèque.

## 14/11/2026 — Prolongation
- Refusée si : prêt clôturé, plafond de prolongations de la formule atteint, prêt en retard, réservation en attente sur l'édition.
- Une prolongation ajoute la durée de la formule à l'**échéance en cours** (pas à la date du jour), pour que l'adhérent ne perde pas les jours restants.
- Choix : la formule est celle en vigueur **aujourd'hui** pour la prolongation, alors que le retard utilise celle du début du prêt. La prolongation est un nouvel acte ; le retard sanctionne le contrat initial.

## 14/11/2026 — Tests
- Règles métier dans des services recevant une horloge injectée (`MockClock`) : tests unitaires sans base de données ni dépendance à la date du jour.
- Les requêtes de la file de réservation (ordre, position, filtres) sont testées sur une vraie base MySQL de test, avec rollback après chaque test.
- Un refus ne doit jamais écrire en base : vérifié par des tests sur `persist` et `flush`.
- Les commandes `app:emprunter`, `app:retourner`, `app:reserver`, `app:declarer`, `app:prolonger` et `app:reservations:expirer` permettent d'exercer les services avant que l'interface existe.

## 13/11/2026 — Authentification et rôles
- Entité `User` séparée de l'entité `Adherent` : un utilisateur du système n'est pas obligatoirement un adhérent (ex. bibliothécaire). Un adhérent possède un compte `User` lié en OneToOne nullable (`adherent_id`).
- Rôles distincts : `ROLE_BIBLIOTHECAIRE` donne accès à l'administration `/admin` (EasyAdmin), `ROLE_ADHERENT` donne accès à l'espace personnel `/mon-espace`. Le catalogue `/catalogue` reste public et accessible sans authentification.
- Sécurité :
  - Authentification par formulaire Symfony `form_login` avec protection CSRF active (`_csrf_token`).
  - Limitation des tentatives d'intrusion : `login_throttling` (5 tentatives max par minute, propulsé par `symfony/rate-limiter`).
  - Mots de passe hachés via `auto` (bcrypt/argon2id). Mot de passe de dev documenté (`password123`).
- Déconnexion gérée nativement par le firewall (`/logout`).

## 13/11/2026 — Catalogue public et recherche
- Route `/catalogue` publique : consultation ouverte à tous sans authentification.
- Recherche multi-critères : recherche générale plein texte (`q`) ciblant le titre, les auteurs (nom/prénom) et l'ISBN. Filtres par type d'œuvre (`livre`, `bd`, `dvd`), par langue et par disponibilité (au moins un exemplaire disponible).
- Pagination : fixée à 10 résultats par page avec conservation des filtres actifs dans les liens de pagination.
- Fiche de détail `/catalogue/{id}` : présente la description complète de l'édition, la liste de ses exemplaires avec localisation (site) et statut (disponible, emprunté, réservé, etc.).
- Réservation en ligne :
  - Pour un adhérent authentifié (`ROLE_ADHERENT`), un bouton « Réserver » permet d'enregistrer la réservation via `ReservationService` (action POST avec jeton CSRF et messages flash de confirmation ou d'erreur métier).
  - Pour un visiteur anonyme, le bouton redirige vers la page `/login`.

## 13/11/2026 — Espace adhérent
- L'espace personnel `/mon-espace` affiche : prêts en cours (échéance, retard, nombre de prolongations), réservations actives avec leur position dans la file ou leur date limite de mise de côté, historique des prêts clôturés (avec pénalités éventuelles), et total des pénalités dues.
- Un `User` sans `Adherent` lié (ex. bibliothécaire) déclenche un 403 clair s'il tente d'accéder à `/mon-espace` : sa session est valide mais il n'a pas de profil adhérent.
- **Prolongation depuis l'espace :** le bouton « Prolonger » n'est affiché que si `ProlongationService::peutEtreProlonge()` retourne `true`. Cela évite des erreurs métier prévisibles, mais les cases limites (réservation créée entre l'affichage et la soumission) sont gérées par la `RegleMetierException` traduite en message flash `danger`.
- **Annulation depuis l'espace :** le bouton « Annuler » est toujours affiché pour les réservations actives (`EN_ATTENTE` et `DISPONIBLE`). L'annulation d'une mise de côté libère l'exemplaire via `ReservationService::annuler()`.
- **Contrôle de propriété :** vérification que le prêt ou la réservation appartient à l'adhérent connecté **avant** la validation CSRF, afin de retourner un 403 non une erreur CSRF.
- **Tests :** `ReservationRepositoryTest` utilise désormais un nettoyage explicite des tables (`DELETE FROM` avec FK désactivée) au lieu de la stratégie `beginTransaction / rollBack`, qui causait des blocages MySQL lors de l'exécution séquentielle après les tests `WebTestCase`.

## 13/11/2026 — Statistiques du back-office
- Le tableau de bord EasyAdmin affiche : prêts en cours (total + répartition par site), prêts en retard, réservations en attente (total + top 10 par édition), top 10 des documents les plus empruntés, total des pénalités impayées en MAD.
- Toutes les requêtes sont regroupées dans un service dédié `StatistiquesRepository` (DBAL brut, pas ORM) injecté dans `DashboardController` : les jointures sont écrites en SQL natif pour rester lisibles et éviter le N+1.
- Choix DBAL plutôt qu'ORM : les statistiques sont des agrégats (COUNT, SUM, GROUP BY) ; DQL offre peu d'avantage ici et le SQL natif est plus direct.
- La date du jour est fournie par `ClockInterface` injecté dans le contrôleur pour permettre les tests unitaires sans mock DB.
- Tests : `StatistiquesRepositoryTest` (KernelTestCase) avec nettoyage explicite des tables avant chaque test ; 7 tests, 16 assertions.

## Questions à trancher

### 1. Deux éditions d'un même roman sont-elles la même œuvre ?
**Décision :** oui. Une œuvre regroupe toutes ses éditions (poche, grand format, traduction). Un adhérent réserve une édition précise, mais le catalogue regroupe par œuvre.
**Limite assumée :** deux œuvres homonymes ne sont pas fusionnées automatiquement.

### 2. Réserver un document uniquement disponible dans l'autre site ?
**Décision :** la réservation est refusée si un exemplaire est disponible dans la médiathèque de rattachement de l'adhérent (il l'emprunte directement). Si l'exemplaire n'est disponible que dans l'autre site, la réservation est acceptée : l'adhérent peut préférer attendre un exemplaire dans sa médiathèque.
**Limite assumée :** le transfert physique d'un exemplaire entre sites n'est pas modélisé. Un adhérent qui veut le document disponible ailleurs doit s'y rendre.

### 3. Combien de temps un document réservé reste-t-il mis de côté ?
**Décision :** 5 jours calendaires, jusqu'à 23h59 du dernier jour (constante `MiseDeCoteService::DELAI_JOURS`). Passé ce délai, la réservation passe à « expirée » et le document est proposé au suivant de la file, ou remis en rayon.
**Pourquoi 5 jours :** assez pour passer à la médiathèque, assez court pour ne pas bloquer la file. Valeur à confirmer avec la médiathèque ; elle n'est pas paramétrable par formule.
**Limites :** pas de notification par email. L'adhérent verra la mise de côté dans son espace (commit 26). L'expiration est déclenchée par `app:reservations:expirer`, à planifier chaque jour ; entre l'échéance et l'exécution, `EmpruntService` refuse déjà l'emprunt grâce au contrôle de date.

### 4. Auteurs multiples, traducteurs, illustrateurs ?
**Décision :** table `Contribution` avec un rôle. Une œuvre peut avoir plusieurs contributions, une personne peut contribuer à plusieurs œuvres.

### 5. Open Library : données incomplètes ou différentes ?
*À trancher si le niveau 4 est réalisé.*
