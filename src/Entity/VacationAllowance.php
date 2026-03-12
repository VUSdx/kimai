<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\VacationAllowanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'kimai2_vacation_allowances')]
#[ORM\UniqueConstraint(columns: ['user_id', 'year'])]
#[ORM\Entity(repositoryClass: VacationAllowanceRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class VacationAllowance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'year', type: Types::INTEGER, nullable: false)]
    private int $year;

    #[ORM\Column(name: 'hours', type: Types::FLOAT, nullable: false, options: ['default' => 0])]
    private float $hours = 0.0;

    public function __construct(User $user, int $year, float $hours = 0.0)
    {
        $this->user = $user;
        $this->year = $year;
        $this->hours = $hours;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getHours(): float
    {
        return $this->hours;
    }

    public function setHours(float $hours): void
    {
        $this->hours = $hours;
    }
}
