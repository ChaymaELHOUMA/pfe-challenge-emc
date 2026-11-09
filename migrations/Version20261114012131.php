<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114012131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE abonnement (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, adherent_id INT NOT NULL, formule_id INT NOT NULL, INDEX IDX_351268BB25F06C53 (adherent_id), INDEX IDX_351268BB2A68F4D1 (formule_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE adherent (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, date_inscription DATETIME NOT NULL, site_rattachement_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_90D3F060E7927C74 (email), INDEX IDX_90D3F06067D5E16 (site_rattachement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formule (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(80) NOT NULL, max_prets INT NOT NULL, duree_pret_jours INT NOT NULL, max_prolongations INT NOT NULL, penalite_par_jour NUMERIC(6, 2) NOT NULL, tarif_annuel NUMERIC(8, 2) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT FK_351268BB25F06C53 FOREIGN KEY (adherent_id) REFERENCES adherent (id)');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT FK_351268BB2A68F4D1 FOREIGN KEY (formule_id) REFERENCES formule (id)');
        $this->addSql('ALTER TABLE adherent ADD CONSTRAINT FK_90D3F06067D5E16 FOREIGN KEY (site_rattachement_id) REFERENCES site (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BB25F06C53');
        $this->addSql('ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BB2A68F4D1');
        $this->addSql('ALTER TABLE adherent DROP FOREIGN KEY FK_90D3F06067D5E16');
        $this->addSql('DROP TABLE abonnement');
        $this->addSql('DROP TABLE adherent');
        $this->addSql('DROP TABLE formule');
    }
}
