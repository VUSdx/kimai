<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Reporting;

use App\Controller\AbstractController;
use App\Reporting\LeaveByYear\LeaveByYear;
use App\Reporting\LeaveByYear\LeaveByYearForm;
use App\Repository\TimesheetRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting/leave')]
#[IsGranted('report:user')]
final class LeaveReportController extends AbstractController
{
    #[Route(path: '/year', name: 'report_leave_year', methods: ['GET'])]
    public function leaveByYear(Request $request, TimesheetRepository $timesheetRepository): Response
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($currentUser);
        $canChangeUser = $this->isGranted('view_other_timesheet') && $this->isGranted('view_other_reporting');

        $values = new LeaveByYear();
        $values->setUser($currentUser);
        $values->setDate($dateTimeFactory->createStartOfYear());

        $form = $this->createFormForGetRequest(LeaveByYearForm::class, $values, [
            'include_user' => $canChangeUser,
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($currentUser);
        }

        if ($currentUser !== $values->getUser() && !$canChangeUser) {
            throw new AccessDeniedException('User is not allowed to see other users data');
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->createStartOfYear());
        }

        $selectedUser = $values->getUser();
        $year = (int) $values->getDate()->format('Y');

        $previous = clone $values->getDate();
        $previous->modify('-1 year');

        $next = clone $values->getDate();
        $next->modify('+1 year');

        // Sum leave hours per month (project "Time off", activity "Leave"), expressed as days (hours / 8)
        $yearStart = new \DateTime($year . '-01-01 00:00:00');
        $yearEnd = new \DateTime(($year + 1) . '-01-01 00:00:00');

        $qb = $timesheetRepository->createQueryBuilder('t');
        $qb->select('MONTH(t.begin) as month, SUM(t.duration) as seconds')
            ->join('t.project', 'p')
            ->join('t.activity', 'a')
            ->where('t.user = :user')
            ->andWhere('p.name = :projectName')
            ->andWhere('a.name = :activityName')
            ->andWhere('t.begin >= :start')
            ->andWhere('t.begin < :end')
            ->groupBy('month')
            ->setParameter('user', $selectedUser->getId())
            ->setParameter('projectName', 'Time off')
            ->setParameter('activityName', 'Leave')
            ->setParameter('start', $yearStart)
            ->setParameter('end', $yearEnd);

        $countsByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $countsByMonth[$m] = [
                'month' => new \DateTime($year . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '-01'),
                'cnt' => 0.0,
            ];
        }
        foreach ($qb->getQuery()->getResult() as $row) {
            $countsByMonth[(int) $row['month']]['cnt'] = (int) $row['seconds'] / 28800.0;
        }

        $total = array_sum(array_column($countsByMonth, 'cnt'));

        return $this->render('reporting/leave_by_year.html.twig', [
            'report_title' => 'report_leave_year',
            'box_id' => 'leave-year-reporting-box',
            'form' => $form->createView(),
            'user' => $selectedUser,
            'current' => $values->getDate(),
            'previous' => $previous,
            'next' => $next,
            'countsByMonth' => $countsByMonth,
            'total' => $total,
        ]);
    }
}
