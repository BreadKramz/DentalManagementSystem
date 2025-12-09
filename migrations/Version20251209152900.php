<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make user_id nullable in activity_log table to allow user deletion while preserving logs
 */
final class Version20251209152900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make user_id nullable in activity_log table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log CHANGE user_id user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log CHANGE user_id user_id INT NOT NULL');
    }
}