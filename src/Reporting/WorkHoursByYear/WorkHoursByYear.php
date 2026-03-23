<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\WorkHoursByYear;

use App\Entity\Team;

final class WorkHoursByYear
{
    private ?\DateTimeInterface $date = null;
    private bool $decimal = false;
    private ?Team $team = null;

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function isDecimal(): bool
    {
        return $this->decimal;
    }

    public function setDecimal(bool $decimal): void
    {
        $this->decimal = $decimal;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team = null): void
    {
        $this->team = $team;
    }
}
