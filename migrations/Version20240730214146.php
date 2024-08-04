<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240730214146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE day (id INT AUTO_INCREMENT NOT NULL, day_of_week INT NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE status (id INT AUTO_INCREMENT NOT NULL, is_on TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE time_slot (id INT AUTO_INCREMENT NOT NULL, start_day_id INT DEFAULT NULL, end_day_id INT DEFAULT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, type VARCHAR(20) NOT NULL, INDEX IDX_1B3294AC30DD6B6 (start_day_id), INDEX IDX_1B3294A79D92AEE (end_day_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE time_slot ADD CONSTRAINT FK_1B3294AC30DD6B6 FOREIGN KEY (start_day_id) REFERENCES day (id)');
        $this->addSql('ALTER TABLE time_slot ADD CONSTRAINT FK_1B3294A79D92AEE FOREIGN KEY (end_day_id) REFERENCES day (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE time_slot DROP FOREIGN KEY FK_1B3294AC30DD6B6');
        $this->addSql('ALTER TABLE time_slot DROP FOREIGN KEY FK_1B3294A79D92AEE');
        $this->addSql('DROP TABLE day');
        $this->addSql('DROP TABLE status');
        $this->addSql('DROP TABLE time_slot');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
