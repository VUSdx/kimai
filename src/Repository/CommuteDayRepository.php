<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\CommuteDay;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<CommuteDay>
 */
class CommuteDayRepository extends EntityRepository
{
    /**
     * Returns CommuteDays for the given date range, indexed by Y-m-d date string.
     *
     * @return array<string, CommuteDay>
     */
    public function findForWeek(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('c')
            ->where($qb->expr()->eq('c.user', ':user'))
            ->setParameter('user', $user->getId())
            ->andWhere($qb->expr()->between('c.date', ':start', ':end'))
            ->setParameter('start', \DateTimeImmutable::createFromInterface($start), 'date_immutable')
            ->setParameter('end', \DateTimeImmutable::createFromInterface($end), 'date_immutable')
        ;

        /** @var array<CommuteDay> $rows */
        $rows = $qb->getQuery()->getResult();

        $indexed = [];
        foreach ($rows as $commuteDay) {
            $indexed[$commuteDay->getDate()->format('Y-m-d')] = $commuteDay;
        }

        return $indexed;
    }

    public function save(CommuteDay $commuteDay): void
    {
        $em = $this->getEntityManager();
        $em->persist($commuteDay);
        $em->flush();
    }

    public function remove(CommuteDay $commuteDay): void
    {
        $em = $this->getEntityManager();
        $em->remove($commuteDay);
        $em->flush();
    }
}
