<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * @version 2.46
 */
final class Version20260310000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for per-user per-day commute registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kimai2_commute_days (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            work_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            commute TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE INDEX uq_commute_user_date (user_id, work_date),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('ALTER TABLE kimai2_commute_days ADD CONSTRAINT fk_commute_user FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_commute_days DROP FOREIGN KEY fk_commute_user');
        $this->addSql('DROP TABLE kimai2_commute_days');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
