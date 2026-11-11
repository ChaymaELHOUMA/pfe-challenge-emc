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
