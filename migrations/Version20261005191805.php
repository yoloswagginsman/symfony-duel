<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005191805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Игра с компьютером: место компьютера (player = null, его индекс — computer_player), индекс победителя';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games ADD computer_player SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE games ADD winner_index SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE games ALTER player0_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games DROP computer_player');
        $this->addSql('ALTER TABLE games DROP winner_index');
        $this->addSql('ALTER TABLE games ALTER player0_id SET NOT NULL');
    }
}
