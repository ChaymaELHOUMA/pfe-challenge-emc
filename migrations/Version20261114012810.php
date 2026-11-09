<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114012810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE penalite (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(8, 2) NOT NULL, motif VARCHAR(30) NOT NULL, cree_le DATETIME NOT NULL, reglee TINYINT DEFAULT 0 NOT NULL, pret_id INT NOT NULL, INDEX IDX_C62D8C5D1B61704B (pret_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pret (id INT AUTO_INCREMENT NOT NULL, date_debut DATETIME NOT NULL, date_echeance DATETIME NOT NULL, date_retour DATETIME DEFAULT NULL, nb_prolongations INT DEFAULT 0 NOT NULL, adherent_id INT NOT NULL, exemplaire_id INT NOT NULL, INDEX IDX_52ECE97925F06C53 (adherent_id), INDEX IDX_52ECE9795843AA21 (exemplaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, cree_le DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, mise_de_cote_jusqua DATETIME DEFAULT NULL, adherent_id INT NOT NULL, edition_id INT NOT NULL, INDEX IDX_42C8495525F06C53 (adherent_id), INDEX IDX_42C8495574281A5E (edition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE penalite ADD CONSTRAINT FK_C62D8C5D1B61704B FOREIGN KEY (pret_id) REFERENCES pret (id)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE97925F06C53 FOREIGN KEY (adherent_id) REFERENCES adherent (id)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE9795843AA21 FOREIGN KEY (exemplaire_id) REFERENCES exemplaire (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495525F06C53 FOREIGN KEY (adherent_id) REFERENCES adherent (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495574281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE penalite DROP FOREIGN KEY FK_C62D8C5D1B61704B');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE97925F06C53');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE9795843AA21');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495525F06C53');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495574281A5E');
        $this->addSql('DROP TABLE penalite');
        $this->addSql('DROP TABLE pret');
        $this->addSql('DROP TABLE reservation');
    }
}
