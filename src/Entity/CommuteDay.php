<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\CommuteDayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'kimai2_commute_days')]
#[ORM\UniqueConstraint(columns: ['user_id', 'work_date'])]
#[ORM\Entity(repositoryClass: CommuteDayRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CommuteDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'work_date', type: Types::DATE_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $date;

    #[ORM\Column(name: 'commute', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $commute = false;

    public function __construct(User $user, \DateTimeImmutable $date)
    {
        $this->user = $user;
        $this->date = $date;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function isCommute(): bool
    {
        return $this->commute;
    }

    public function setCommute(bool $commute): void
    {
        $this->commute = $commute;
    }
}
