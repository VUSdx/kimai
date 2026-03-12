<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Reporting;

use App\Controller\AbstractController;
use App\Reporting\VacationByYear\VacationByYear;
use App\Reporting\VacationByYear\VacationByYearForm;
use App\Repository\TimesheetRepository;
use App\Repository\VacationAllowanceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting/vacation')]
#[IsGranted('report:user')]
final class VacationReportController extends AbstractController
{
    #[Route(path: '/year', name: 'report_vacation_year', methods: ['GET'])]
    public function vacationByYear(
        Request $request,
        VacationAllowanceRepository $allowanceRepository,
        TimesheetRepository $timesheetRepository,
    ): Response {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($currentUser);
        $canChangeUser = $this->isGranted('view_other_timesheet') && $this->isGranted('view_other_reporting');

        $values = new VacationByYear();
        $values->setUser($currentUser);
        $values->setDate($dateTimeFactory->createStartOfYear());

        $form = $this->createFormForGetRequest(VacationByYearForm::class, $values, [
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

        $allowance = $allowanceRepository->findByUserAndYear($selectedUser, $year);
        $allowanceHours = $allowance !== null ? $allowance->getHours() : 0.0;

        // Sum duration of timesheets on "Vacation" activity in "Time off" project for the selected user & year
        $yearStart = new \DateTime($year . '-01-01 00:00:00');
        $yearEnd = new \DateTime(($year + 1) . '-01-01 00:00:00');

        $qb = $timesheetRepository->createQueryBuilder('t');
        $qb->select('COALESCE(SUM(t.duration), 0) as total_seconds')
            ->join('t.project', 'p')
            ->join('t.activity', 'a')
            ->where('t.user = :user')
            ->andWhere('p.name = :projectName')
            ->andWhere('a.name = :activityName')
            ->andWhere('t.begin >= :start')
            ->andWhere('t.begin < :end')
            ->setParameter('user', $selectedUser->getId())
            ->setParameter('projectName', 'Time off')
            ->setParameter('activityName', 'Vacation')
            ->setParameter('start', $yearStart)
            ->setParameter('end', $yearEnd);

        $totalSeconds = (int) $qb->getQuery()->getSingleScalarResult();
        $usedHours = $totalSeconds / 3600.0;
        $remainingHours = $allowanceHours - $usedHours;

        // Monthly breakdown
        $qbMonthly = $timesheetRepository->createQueryBuilder('t');
        $qbMonthly->select('MONTH(t.begin) as month, COALESCE(SUM(t.duration), 0) as seconds')
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
            ->setParameter('activityName', 'Vacation')
            ->setParameter('start', $yearStart)
            ->setParameter('end', $yearEnd);

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'month' => new \DateTime($year . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '-01'),
                'hours' => 0.0,
            ];
        }
        foreach ($qbMonthly->getQuery()->getResult() as $row) {
            $m = (int) $row['month'];
            $monthlyData[$m]['hours'] = (int) $row['seconds'] / 3600.0;
        }

        return $this->render('reporting/vacation_by_year.html.twig', [
            'report_title' => 'report_vacation_year',
            'box_id' => 'vacation-year-reporting-box',
            'form' => $form->createView(),
            'user' => $selectedUser,
            'current' => $values->getDate(),
            'previous' => $previous,
            'next' => $next,
            'allowanceHours' => $allowanceHours,
            'usedHours' => $usedHours,
            'remainingHours' => $remainingHours,
            'monthlyData' => $monthlyData,
        ]);
    }
}
