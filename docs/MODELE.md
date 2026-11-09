# Modèle de données

```mermaid
erDiagram
    OEUVRE ||--o{ CONTRIBUTION : "comporte"
    AUTEUR ||--o{ CONTRIBUTION : "participe"
    OEUVRE ||--o{ EDITION : "possède"
    EDITION ||--o{ EXEMPLAIRE : "se décline en"
    SITE ||--o{ EXEMPLAIRE : "abrite"
    ADHERENT ||--o{ EMPRUNT : "effectue"
    EXEMPLAIRE ||--o{ EMPRUNT : "est prêté"
    ADHERENT ||--o{ RESERVATION : "effectue"
    EDITION ||--o{ RESERVATION : "fait l'objet de"

    OEUVRE {
        int id PK
        string titre
        text resume
        string langue_originale
        int annee_creation
    }

    AUTEUR {
        int id PK
        string nom
        string prenom
        string biographie
    }

    CONTRIBUTION {
        int id PK
        int oeuvre_id FK
        int auteur_id FK
        string role "auteur, traducteur, illustrateur"
    }

    EDITION {
        int id PK
        int oeuvre_id FK
        string isbn
        string editeur
        int annee_publication
        int nombre_pages
        string format
    }

    SITE {
        int id PK
        string nom
        string adresse
    }

    EXEMPLAIRE {
        int id PK
        int edition_id FK
        int site_id FK
        string code_barre
        string etat "neuf, bon, abime, perdu"
        string statut "disponible, emprunte, reserve"
    }

    ADHERENT {
        int id PK
        string nom
        string prenom
        string email
        string numero_adherent
        date date_adhesion
        string statut "actif, suspendu"
    }

    EMPRUNT {
        int id PK
        int exemplaire_id FK
        int adherent_id FK
        datetime date_emprunt
        datetime date_retour_prevue
        datetime date_retour_effective
        string statut "en_cours, rendu, en_retard"
    }

    RESERVATION {
        int id PK
        int edition_id FK
        int adherent_id FK
        datetime date_reservation
        string statut "en_attente, disponible, annulee, traitee"
    }
```

## Explications du modèle

Ce modèle de données repose sur une séparation claire des responsabilités :
- **Séparation Œuvre / Édition / Exemplaire** : L'œuvre représente le contenu intellectuel abstrait (l'histoire, le texte original). L'édition représente la publication commerciale et éditoriale (ISBN, éditeur, date, traduction). L'exemplaire représente l'objet physique matériel, localisé dans un site précis du réseau, identifié par un code-barres et caractérisé par son état d'usure.
- **Gestion des contributeurs** : La table intermédiaire `Contribution` permet de rattacher plusieurs personnes à une œuvre en précisant leur rôle exact (auteur principal, traducteur, illustrateur, etc.).
- **Réservations vs Emprunts** : Les réservations ciblent une édition (l'usager réserve le contenu/livre souhaité), alors que l'emprunt est directement rattaché à un exemplaire physique précis.
- **Réseau multi-sites** : Chaque exemplaire est affecté à un `Site` du réseau de médiathèques, permettant la gestion des transferts et de la disponibilité par site.
