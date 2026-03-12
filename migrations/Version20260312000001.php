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
 * @version 2.47
 */
final class Version20260312000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for per-user per-year vacation allowance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kimai2_vacation_allowances (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            year INT NOT NULL,
            hours DOUBLE PRECISION NOT NULL DEFAULT 0,
            UNIQUE INDEX uq_vacation_user_year (user_id, year),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('ALTER TABLE kimai2_vacation_allowances ADD CONSTRAINT fk_vacation_user FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_vacation_allowances DROP FOREIGN KEY fk_vacation_user');
        $this->addSql('DROP TABLE kimai2_vacation_allowances');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
