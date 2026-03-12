<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\User;
use App\Entity\VacationAllowance;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<VacationAllowance>
 */
class VacationAllowanceRepository extends EntityRepository
{
    public function findByUserAndYear(User $user, int $year): ?VacationAllowance
    {
        return $this->findOneBy(['user' => $user->getId(), 'year' => $year]);
    }

    public function save(VacationAllowance $allowance): void
    {
        $em = $this->getEntityManager();
        $em->persist($allowance);
        $em->flush();
    }
}
