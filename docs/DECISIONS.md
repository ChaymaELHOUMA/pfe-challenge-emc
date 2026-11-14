# Journal de décisions d'architecture et de conception

Ce document consigne l'ensemble des choix de conception, des arbitrages techniques et des réponses aux problématiques métier rencontrées tout au long du développement du projet **Réseau de médiathèques** (Sujet 4 — PFE Challenge Education Media Company).

---

## 13/11/2026 — Démarrage et choix du sujet
* **Sujet choisi :** Sujet n°4 — Réseau de médiathèques.
* **Justification :** Le sujet présente un périmètre métier riche et formateur : règles de circulation strictes (prêts, retours, pénalités, prolongations conditionnelles), algorithmique de file d'attente FIFO avec réallocation automatique au retour, modèle de données relationnel à plusieurs niveaux (Œuvre / Édition / Exemplaire), et interconnexion à une API publique ouverte (Open Library).
* **Priorisation :**
  1. Niveaux 1 à 3 (modèle solide, règles métier encapsulées dans des services purs, back-office EasyAdmin, catalogue public et espace adhérent avec contrôle d'accès strict).
  2. Niveaux 4 et 5 (import Open Library individuel et par lot, conteneurisation Docker, documentation d'architecture exhaustive).

---

## 13/11/2026 — Modèle de données initial
* **Distinction Œuvre, Édition et Exemplaire :**
  * Une `Oeuvre` représente la création intellectuelle abstraite (titre, type).
  * Une `Edition` représente une publication physique ou éditoriale précise (ISBN, éditeur, année, langue, support).
  * Un `Exemplaire` représente l'objet matériel physique identifiable par un code-barres unique, rattaché à un site (`Site`) et possédant un état (`neuf`, `bon`, `use`) et un statut (`disponible`, `emprunte`, `reserve`, `perdu`, `retire`).
* **Paramétrage des formules d'abonnement :** L'entité `Formule` est persistée en base (nom, durée de prêt, plafond d'emprunts simultanés, nombre maximal de prolongations, tarif de pénalité journalière, tarif annuel).
* **Précision financière :** Tous les montants financiers (pénalités, forfaits, tarifs) sont stockés sous forme de chaînes décimales (`string` / type SQL `DECIMAL(10,2)`), bannissant tout calcul en virgule flottante (`float`) pour prévenir les erreurs d'arrondi.
* **Temporalité et testabilité :** Injection systématique de l'interface PSR-20 `ClockInterface` dans tous les services métier, permettant de simuler l'avancement du temps dans les tests unitaires (`MockClock`) sans dépendre de l'horloge système.

---

## 13/11/2026 — Règles métier de circulation (prêts, retours, prolongations)
* **Emprunt (`EmpruntService`) :**
  * Vérification de l'abonnement en cours de validité à la date du jour.
  * Respect du plafond d'emprunts de la formule active.
  * Refus si l'exemplaire n'est pas disponible (déjà prêté, perdu, retiré ou mis de côté pour un tiers).
  * Autorisation si l'exemplaire est mis de côté spécifiquement pour l'adhérent emprunteur.
* **Prolongation (`ProlongationService`) :**
  * Refusée si le prêt est déjà en retard (la prolongation prévient le retard mais ne l'efface pas).
  * Refusée si le plafond de prolongations de la formule est atteint.
  * Refusée si une réservation est en attente sur l'édition (priorité à la file d'attente).
  * Calcul de la nouvelle échéance : ajout des jours de la formule à la **date d'échéance courante** (et non à la date du jour) pour ne pas léser l'adhérent qui prolonge en avance.

---

## 13/11/2026 — File de réservation et mise de côté
* **Cible de la réservation :** Une réservation vise une **édition** (et non un exemplaire ou une œuvre globale), car un lecteur choisit un format, une langue ou un support spécifique.
* **Ordre de la file :** Ordonnancement strict FIFO basé sur la date et heure de création (`creeLe` croissant), avec départage par l'identifiant technique (`id` croissant).
* **Conditions de refus :** Absence d'abonnement actif, doublon de réservation active sur la même édition, édition déjà empruntée par l'adhérent, plafond de 5 réservations actives atteint, ou aucun exemplaire en circulation.
* **Règle locale vs distant :** Si un exemplaire est immédiatement disponible dans la médiathèque de rattachement de l'adhérent, la réservation est rejetée (invitation à l'emprunt direct). Si les exemplaires disponibles ne se trouvent que dans un autre site, la réservation est acceptée.
* **Mise de côté (`MiseDeCoteService`) :**
  * Lors du retour d'un document, si une file existe, le premier adhérent éligible se voit attribuer le document. Sa réservation passe au statut `disponible` avec une date limite fixée à 5 jours calendaires (23h59).
  * Si l'abonnement du premier adhérent a expiré entre-temps, sa réservation passe à `expiree` et l'exemplaire est proposé au suivant dans la file.
  * Si la file est vide, l'exemplaire redevient immédiatement `disponible` en rayon.

---

## 13/11/2026 — Pénalités, déclarations de perte et dégradation
* **Retard (`RetourService`) :** Calculé en jours calendaires entre l'échéance et le jour du retour effectif. Le jour de l'échéance n'est pas pénalisé. Le montant journalier appliqué est celui de la formule **en vigueur au début du prêt** (respect du contrat initial).
* **Perte (`PenaliteService::declarerPerdu`) :** Forfait fixe de 25 MAD, clôture du prêt et passage de l'exemplaire au statut `perdu`.
* **Dégradation (`PenaliteService::declarerAbime`) :** Forfait fixe de 5 MAD, clôture du prêt et passage de l'exemplaire au statut `retire`.

---

## 13/11/2026 — Authentification et gestion des rôles
* **Découplage `User` et `Adherent` :** Un compte système `User` n'est pas obligatoirement un adhérent physique (ex. bibliothécaire). Un adhérent possède une relation OneToOne optionnelle vers `User`.
* **Hiérarchie et contrôle d'accès :**
  * `/admin` restreint exclusivement au rôle `ROLE_BIBLIOTHECAIRE`.
  * `/mon-espace` restreint au rôle `ROLE_ADHERENT`.
  * `/catalogue` public et accessible sans authentification.
* **Sécurité applicative :**
  * Formulaire standard Symfony `form_login` avec protection CSRF active (`_csrf_token`).
  * Limitation des tentatives (`login_throttling` via `symfony/rate-limiter`) pour contrecarrer les attaques par force brute.
  * Mots de passe hachés via l'algorithme par défaut recommandé (`auto` : bcrypt / argon2id).

---

## 13/11/2026 — Catalogue public et recherche
* **Recherche plein texte sans effet N+1 :** Les requêtes dans `EditionRepository::searchCatalogue()` emploient des jointures Doctrine ORM (`join` et `addSelect`) et le `Paginator` natif pour charger les auteurs et les exemplaires en un nombre constant de requêtes.
* **Filtres multi-critères :** Filtre par recherche textuelle (titre, auteur, ISBN), par type d'œuvre (`livre`, `bd`, `dvd`), par langue et par disponibilité immédiate.
* **Réservation en libre-accès :** Bouton « Réserver » sur la fiche détaillée : redirige un visiteur anonyme vers `/login`, ou déclenche l'action POST sécurisée par CSRF pour un adhérent connecté.

---

## 13/11/2026 — Espace personnel adhérent
* **Vision 360° de l'adhérent :**
  * Prêts en cours (alerte visuelle si retard, calcul du nombre de jours restant ou dépassé, éligibilité à la prolongation).
  * Réservations actives (affichage de la position en temps réel dans la file d'attente `#1`, `#2` ou de la date limite de retrait si le document est mis de côté).
  * Historique des prêts passés avec mention des pénalités éventuelles.
  * Solde cumulé des pénalités impayées (en MAD).
* **Contrôle de propriété strict :** Toute action de prolongation ou d'annulation vérifie en amont que la ressource appartient bien à l'adhérent authentifié (renvoie une `AccessDeniedException` 403 en cas de tentative frauduleuse sur la ressource d'autrui).

---

## 13/11/2026 — Tableau de bord et statistiques back-office
* **Architecture découplée :** Les requêtes d'agrégats statistiques sont regroupées dans `StatistiquesRepository` en SQL natif / DBAL (COUNT, SUM, GROUP BY) pour garantir des performances optimales sans la surcharge de l'ORM.
* **Indicateurs clés affichés :** Prêts en cours par site, prêts en retard, top 10 des éditions les plus empruntées, total des pénalités non réglées (MAD) et volume des réservations en attente par édition.

---

## 13/11/2026 — Import Open Library par ISBN
* **Normalisation et validation ISBN (`IsbnNormalizer`) :**
  * Prise en charge des formats ISBN-10 et ISBN-13 (avec ou sans tirets/espaces).
  * Validation stricte des clés de contrôle (modulo 11 pour l'ISBN-10 avec prise en charge du chiffre `X`, modulo 10 pour l'ISBN-13).
  * Conversion systématique de tout ISBN-10 vers sa représentation normalisée ISBN-13 (13 chiffres).
* **Client HTTP résilient (`OpenLibraryClient`) :**
  * Utilise Symfony `HttpClientInterface` avec un timeout de 10 secondes.
  * Interception des codes 404 (renvoie `null` au lieu d'une exception non maîtrisée).
  * Extraction d'auteurs tolérante : requêtage direct de l'endpoint `/authors/OL....json` si seule la clé est fournie, repli sur `by_statement` si la liste est vide.
* **Idempotence de l'import (`ImportLivreService`) :** Aucune duplication en base lors d'imports successifs du même ISBN. Réutilisation automatique des entités `Oeuvre`, `Personne` et `Contribution`.
* **Commande console :** `app:import:isbn {isbn}` avec affichage synthétique et gestion des statuts de sortie CLI.

---

## 14/11/2026 — Import en lot via CSV
* **Résilience continue :** La commande `app:import:lot {fichier.csv}` poursuit son exécution en cas d'erreur sur une ligne donnée (ISBN invalide, livre non trouvé sur Open Library, site inexistant).
* **Création d'exemplaires optionnelle :** Si les colonnes `site` et `quantite` sont présentes et renseignées, des exemplaires physiques sont automatiquement générés au statut `disponible` avec un code-barres unique (`EX-...`).
* **Mode simulation (`--dry-run`) :** Encapsulation de l'import dans une transaction DBAL systématiquement annulée (`rollBack`) en fin de traitement, garantissant une simulation 100% fidèle sans résidu en base.
* **Rapport d'exécution détaillé :** Décompte des succès/échecs, chronométrage global et tableau des erreurs répertoriant le numéro de ligne, l'ISBN incriminé et le motif exact du rejet.

---

## 14/11/2026 — Environnement de conteneurisation Docker
* **Conteneur applicatif (`Dockerfile`) :** Image basée sur `php:8.3-cli-alpine` intégrant Composer v2, les extensions requises (`pdo_mysql`, `intl`, `opcache`, `zip`) et un script d'entrée `docker/entrypoint.sh` qui attend la disponibilité de MySQL et applique automatiquement les migrations Doctrine.
* **Orchestration multi-services (`compose.yaml`) :**
  * Service `app` : serveur web Symfony sur le port 8000.
  * Service `database` : MySQL 8.0 officiel avec `healthcheck` actif (`mysqladmin ping`). Port hôte mappé sur `3307` (`3307:3306`) afin d'éviter tout conflit de port avec un serveur MySQL déjà actif sur l'hôte.
  * Volumes nommés pour la persistance des données MySQL (`db_data`) et des caches applicatifs (`var_data`).

---

## Questions à trancher (Réponses complètes et argumentées)

### 1. Deux éditions d'un même roman sont-elles la même œuvre ?
**Décision :** Oui. Une œuvre (`Oeuvre`) modélise la création littéraire ou intellectuelle abstraite, tandis qu'une édition (`Edition`) modélise sa concrétisation éditoriale (broché, poche, version illustrée, traduction étrangère), identifiable par son propre ISBN.
* **Impact sur la réservation :** L'adhérent réserve une **édition précise** (choix d'une langue ou d'un format spécifique).
* **Impact sur le catalogue :** Les recherches et listes regroupent les éditions sous leur œuvre commune, mais permettent d'inspecter et d'emprunter chaque édition distinctement.
* **Limites assumées :** Deux œuvres homonymes créées indépendamment ne sont pas fusionnées automatiquement. Lors des imports Open Library, les titres présentant de légères variations typographiques créent des œuvres distinctes qui nécessitent une réconciliation manuelle si désiré.

### 2. Réserver un document uniquement disponible dans l'autre site ?
**Décision :**
* Si un exemplaire de l'édition demandée est immédiatement `disponible` dans la médiathèque de rattachement de l'adhérent, la réservation est **rejetée** (message invitant à emprunter directement le document sur place).
* Si tous les exemplaires locaux sont déjà empruntés ou réservés, mais qu'un exemplaire est disponible dans un **autre site**, la réservation est **acceptée**. L'adhérent s'inscrit dans la file pour obtenir en priorité le prochain exemplaire retourné dans son site de rattachement.
* **Limites assumées :** Le transfert physique inter-médiathèques (« navette logistique ») n'est pas modélisé. Un adhérent souhaitant obtenir immédiatement l'exemplaire disponible dans l'autre site doit s'y déplacer en personne.

### 3. Combien de temps un document réservé reste-t-il mis de côté ?
**Décision :** Le document est conservé pendant **5 jours calendaires**, jusqu'à 23h59 du cinquième jour (constante `MiseDeCoteService::DELAI_JOURS`). Passé ce délai, la réservation est clôturée au statut `expiree` et le document est automatiquement proposé au suivant de la file ou remis en rayon.
* **Justification :** 5 jours représentent un délai suffisant pour permettre à l'adhérent de se déplacer en médiathèque, sans immobiliser excessivement le fonds documentaire pour les autres usagers.
* **Limites assumées :** L'envoi automatique d'emails de notification n'est pas activé. L'adhérent consulte la date limite de retrait directement sur son espace personnel (`/mon-espace`). L'expiration quotidienne est déclenchée par la commande planifiable `app:reservations:expirer` ; toutefois, `EmpruntService` refuse préventivement tout emprunt d'un document mis de côté dont la date limite est échue, même si la commande de purge n'a pas encore tourné.

### 4. Auteurs multiples, traducteurs, illustrateurs ?
**Décision :** Modélisation via une table d'association `Contribution` reliant une `Oeuvre` et une `Personne` avec un champ textuel `role` (`auteur`, `traducteur`, `illustrateur`, etc.).
* **Justification :** Cette modélisation normalisée permet d'associer un nombre illimité de contributeurs à une même œuvre et de réutiliser la même personne pour différentes créations avec des rôles distincts.
* **Limites assumées :** L'import Open Library standard par ISBN ne distinguant pas les rôles de manière structurée sur son point d'entrée simplifié, tous les contributeurs importés reçoivent le rôle par défaut `auteur`. L'ordre d'apparition des auteurs n'est pas ordonnancé par une colonne de position.

### 5. Open Library : données incomplètes ou différentes ?
**Constat :** Open Library est une base collaborative ouverte. Les métadonnées varient considérablement d'une notice à l'autre : dates textuelles hétérogènes (« April 1943 », « c1982 », « 1999? »), auteurs parfois référencés par simple clé interne (`/authors/OL...`) sans nom dans l'objet principal ou renseignés uniquement dans un champ libre `by_statement`, éditeurs multiples ou absents, et notices 404.

**Décisions d'implémentation :**
1. **Normalisation systématique de l'ISBN :**
   * L'ISBN sert de clé unique pour l'édition (`Edition::$isbn`).
   * Tout ISBN fourni (ISBN-10 ou ISBN-13, avec ou sans tirets/espaces) passe par `IsbnNormalizer` : validation mathématique de la clé de contrôle (modulo 11 ou 10) et conversion systématique au format normalisé ISBN-13 (13 chiffres). Un ISBN invalide est rejeté immédiatement avec un message explicite sans interroger l'API.

2. **Résilience face aux données incomplètes (`OpenLibraryClient`) :**
   * **Titre manquant :** repli sur la valeur par défaut « Titre inconnu ».
   * **Éditeur absent :** premier éditeur du tableau utilisé, ou repli sur « Éditeur inconnu ».
   * **Date hétérogène :** extraction robuste de la première année à 4 chiffres (entre 1800 et 2099) via expression régulière (`/\b(1[89]\d{2}|20\d{2})\b/`). Si aucune année n'est détectée, repli sur l'année courante pour satisfaire la contrainte non-nullable du modèle.
   * **Auteurs incomplets :**
     * Si un objet auteur contient `name`, il est utilisé directement.
     * Si l'objet auteur ne contient qu'une clé (`/authors/OL...`), le client effectue un sous-appel HTTP léger vers la notice de l'auteur pour récupérer son nom complet.
     * Si le tableau d'auteurs est vide, extraction du nom depuis le champ textuel `by_statement` (nettoyage des préfixes « by » / « par »).
     * Si aucun auteur n'est trouvé, attribution à une personne nommée « Auteur inconnu » (avec `prenom = null`).
   * **Langue :** conversion des codes ISO à 3 lettres (`fre`, `fra`, `eng`) ou clé `/languages/...` vers les codes standardisés (`fr`, `en`, `es`, etc.), avec repli sur `fr` par défaut.

3. **Différences entre éditions d'une même œuvre (`ImportLivreService`) :**
   * **Idempotence de l'édition :** si une édition avec cet ISBN existe déjà, ses métadonnées sont mises à jour sans recréer d'enregistrement.
   * **Rattachement à l'œuvre :** une recherche par titre exact est effectuée (`OeuvreRepository::findOneBy(['titre' => $titre])`). Si trouvée, la nouvelle édition lui est rattachée ; sinon, une nouvelle œuvre de type `livre` est créée.
   * **Auteurs et contributions :** chaque auteur est découpé en prénom et nom. Si la `Personne` existe déjà en base, elle est réutilisée. La `Contribution` avec le rôle `auteur` est créée uniquement si elle n'existe pas déjà pour cette œuvre (aucun doublon).

4. **Documents absents ou erreurs réseau :**
   * Un code HTTP 404 est intercepté proprement : renvoie `null`, traduit en `RegleMetierException` claire (« Aucun document trouvé sur Open Library pour l'ISBN ... »).
   * Les timeouts réseau (10 secondes) et erreurs HTTP 5xx sont capturés et n'interrompent pas les imports par lot grâce au mécanisme de résilience de `ImportLotService`.

**Limites assumées :**
* **Variations typographiques du titre :** si deux éditions d'une même œuvre possèdent un titre légèrement différent sur Open Library (ex. « Le Petit Prince » et « Le petit prince (illustré) »), deux entités `Oeuvre` distinctes seront créées. Une fusion manuelle en back-office serait nécessaire.
* **Rôle unique des contributeurs importés :** l'API Open Library standard n'indique pas de façon normalisée les rôles spécifiques (illustrateur, préfacier, traducteur) sur le point d'entrée ISBN : tous les contributeurs importés reçoivent le rôle par défaut `auteur`.
* **Découpage prénom/nom :** le découpage par espace attribue le dernier mot au `nom` et le reste au `prenom`. Les noms composés sans trait d'union peuvent être imparfaitement segmentés, ce qui reste acceptable pour une base bibliographique sans contrainte d'état civil.

---

## Bilan des choix techniques et limites globales assumées

| Fonctionnalité | Choix réalisé | Limite assumée / Piste d'évolution |
|----------------|---------------|------------------------------------|
| **Gestion financière** | Stockage SQL `DECIMAL(10,2)` manipulé en chaînes. | Pas de passerelle de paiement en ligne (règlement au guichet). |
| **Notifications** | Consultation sur l'espace web et flash messages. | Pas de serveur SMTP / SMS configuré par défaut. |
| **Transfert inter-sites** | Réservation autorisée si disponible ailleurs. | Pas de gestion logistique d'acheminement physique (« navette »). |
| **Authentification** | Formulaire Symfony standard avec rate-limiting. | Pas d'authentification OAuth2 / OpenID Connect tiers. |
| **Base de test** | Nettoyage transactionnel ou réinitialisation post-suite. | Nécessite une base MySQL accessible pour les tests de repository. |
