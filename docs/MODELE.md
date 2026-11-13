# Modèle de données

Sujet 4 : réseau de médiathèques. Dernière mise à jour : 14/11/2026.

## Diagramme

```mermaid
erDiagram
    SITE ||--o{ EXEMPLAIRE : possede
    SITE ||--o{ ADHERENT : rattache
    OEUVRE ||--o{ EDITION : a
    OEUVRE ||--o{ CONTRIBUTION : a
    PERSONNE ||--o{ CONTRIBUTION : participe
    EDITION ||--o{ EXEMPLAIRE : a
    EDITION ||--o{ RESERVATION : visee
    FORMULE ||--o{ ABONNEMENT : definit
    ADHERENT ||--o| USER : possede
    ADHERENT ||--o{ ABONNEMENT : souscrit
    ADHERENT ||--o{ PRET : emprunte
    ADHERENT ||--o{ RESERVATION : fait
    EXEMPLAIRE ||--o{ PRET : concerne
    PRET ||--o{ PENALITE : genere

    SITE {
        int id
        string nom
        string adresse
    }
    PERSONNE {
        int id
        string nom
        string prenom
    }
    OEUVRE {
        int id
        string titre
        string typeOeuvre
    }
    CONTRIBUTION {
        int id
        string role
    }
    EDITION {
        int id
        string isbn
        string editeur
        int annee
        string langue
        string support
    }
    EXEMPLAIRE {
        int id
        string codeBarres
        string etat
        string statut
    }
    FORMULE {
        int id
        string nom
        int maxPrets
        int dureePretJours
        int maxProlongations
        decimal penaliteParJour
        decimal tarifAnnuel
    }
    ADHERENT {
        int id
        string nom
        string prenom
        string email
        datetime dateInscription
    }
    ABONNEMENT {
        int id
        date dateDebut
        date dateFin
    }
    PRET {
        int id
        datetime dateDebut
        datetime dateEcheance
        datetime dateRetour
        int nbProlongations
    }
    RESERVATION {
        int id
        datetime creeLe
        string statut
        datetime miseDeCoteJusqua
    }
    PENALITE {
        int id
        decimal montant
        string motif
        datetime creeLe
        bool reglee
    }
    USER {
        int id
        string email
        json roles
        string password
    }
```

## Choix de modélisation

- **Œuvre, Édition, Exemplaire** : trois niveaux distincts. L'œuvre est le contenu intellectuel, l'édition est une publication précise (ISBN, éditeur, année, langue, support), l'exemplaire est l'objet physique prêtable, avec son code-barres, son état et son site.
- **Contribution** : table de liaison entre Œuvre et Personne, avec un rôle (auteur, traducteur, illustrateur). Elle gère les auteurs multiples sans colonne dédiée.
- **Formule** : stockée en base. Limites de prêts, durée, prolongations et pénalité journalière sont paramétrables, rien n'est codé en dur.
- **Abonnement** : historique des souscriptions d'un adhérent (dates de début et de fin). La formule applicable à une date donnée est calculée par `Adherent::getFormuleActive()`.
- **Réservation** : liée à une **édition**, pas à un exemplaire. L'adhérent veut un titre, peu importe l'exemplaire. L'ordre de la file est donné par `creeLe`.
- **Prêt** : `dateRetour` à `null` signifie « en cours ». Le nombre de prolongations est suivi sur le prêt.
- **Pénalité** : rattachée au prêt, avec un motif (retard, perdu, abîmé) et un état de règlement.
- **User** : gère l'authentification (`email` unique, `roles`, mot de passe haché). Lié en OneToOne nullable à `Adherent` (un bibliothécaire n'est pas forcément adhérent ; un adhérent a un compte utilisateur pour son espace personnel).
- **Montants** : `decimal`, jamais de `float`.
