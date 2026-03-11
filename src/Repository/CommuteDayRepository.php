<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use DateTime;
use App\Entity\CommuteDay;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
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

    /**
     * Returns commute day counts grouped by month for the given year.
     *
     * @return array<int, int> Month number (1–12) → count of commuted days
     */
    public function countByYear(User $user, int $year): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select("MONTH(c.date) as month, COUNT(c.id) as cnt")
            ->where($qb->expr()->eq('c.user', ':user'))
            ->setParameter('user', $user->getId())
            ->andWhere($qb->expr()->eq('YEAR(c.date)', ':year'))
            ->setParameter('year', $year)
            ->andWhere($qb->expr()->eq('c.commute', ':commute'))
            ->setParameter('commute', true, Types::BOOLEAN)
            ->groupBy('month')
        ;

        $counts = array_combine(range(1, 12),
                                array_map(fn($m) => ['month' => new DateTime($year . "-" . $m . "-01"), 'cnt' => 0], range(1, 12)));
        foreach ($qb->getQuery()->getResult() as $row) {
            $counts[(int)$row['month']] = ['month' => new DateTime($year . "-" . $row['month']. "-01"), 'cnt' => (int) $row['cnt']];
        }

        error_log('CommuteDayRepository::countByYear counts: ' . json_encode($counts));

        return $counts;
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
