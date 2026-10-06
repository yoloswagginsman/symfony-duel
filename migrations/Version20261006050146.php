<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006050146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таймер хода: срок, до которого активный игрок должен сходить';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games ADD turn_deadline TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games DROP turn_deadline');
    }
}
