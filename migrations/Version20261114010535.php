<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114010535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE edition (id INT AUTO_INCREMENT NOT NULL, isbn VARCHAR(50) DEFAULT NULL, editeur VARCHAR(255) NOT NULL, annee INT NOT NULL, langue VARCHAR(50) NOT NULL, support VARCHAR(50) NOT NULL, oeuvre_id INT NOT NULL, UNIQUE INDEX UNIQ_A891181FCC1CF4E6 (isbn), INDEX IDX_A891181F88194DE8 (oeuvre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exemplaire (id INT AUTO_INCREMENT NOT NULL, code_barres VARCHAR(100) NOT NULL, etat VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, edition_id INT NOT NULL, site_id INT NOT NULL, UNIQUE INDEX UNIQ_5EF83C9268EF62E0 (code_barres), INDEX IDX_5EF83C9274281A5E (edition_id), INDEX IDX_5EF83C92F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE edition ADD CONSTRAINT FK_A891181F88194DE8 FOREIGN KEY (oeuvre_id) REFERENCES oeuvre (id)');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C9274281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C92F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE edition DROP FOREIGN KEY FK_A891181F88194DE8');
        $this->addSql('ALTER TABLE exemplaire DROP FOREIGN KEY FK_5EF83C9274281A5E');
        $this->addSql('ALTER TABLE exemplaire DROP FOREIGN KEY FK_5EF83C92F6BD1646');
        $this->addSql('DROP TABLE edition');
        $this->addSql('DROP TABLE exemplaire');
    }
}
