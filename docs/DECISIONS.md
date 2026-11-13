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
