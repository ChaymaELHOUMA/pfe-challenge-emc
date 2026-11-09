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

## Questions à trancher

### 1. Deux éditions d'un même roman sont-elles la même œuvre ?
**Décision :** oui. Une œuvre regroupe toutes ses éditions (poche, grand format, traduction). Un adhérent réserve une édition précise, mais le catalogue regroupe par œuvre.
**Limite assumée :** deux œuvres homonymes ne sont pas fusionnées automatiquement.

### 2. Réserver un document uniquement disponible dans l'autre site ?
*À trancher au moment de la file de réservation (étape 4).*

### 3. Combien de temps un document réservé reste-t-il mis de côté ?
*À trancher au moment de la file de réservation (étape 4).*

### 4. Auteurs multiples, traducteurs, illustrateurs ?
**Décision :** table `Contribution` avec un rôle. Une œuvre peut avoir plusieurs contributions, une personne peut contribuer à plusieurs œuvres.

### 5. Open Library : données incomplètes ou différentes ?
*À trancher si le niveau 4 est réalisé.*
