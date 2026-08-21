<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Reporting;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Reporting\WorkHoursByMonth\WorkHoursByMonth;
use App\Reporting\WorkHoursByMonth\WorkHoursByMonthForm;
use App\Repository\Query\TimesheetStatisticQuery;
use App\Repository\Query\UserQuery;
use App\Repository\Query\VisibilityInterface;
use App\Repository\UserRepository;
use App\Timesheet\TimesheetStatisticService;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting/users')]
#[IsGranted('report:other')]
final class WorkHoursMonthController extends AbstractController
{
    private const EXCLUDED_PROJECTS = ['Non-WBSO activities', 'Time off'];

    #[Route(path: '/work_hours_month', name: 'report_work_hours_month', methods: ['GET', 'POST'])]
    public function report(Request $request, TimesheetStatisticService $statisticService, UserRepository $userRepository): Response
    {
        return $this->render(
            'reporting/work_hours_by_month.html.twig',
            $this->getData($request, $statisticService, $userRepository)
        );
    }

    #[Route(path: '/work_hours_month_export', name: 'report_work_hours_month_export', methods: ['GET', 'POST'])]
    public function export(Request $request, TimesheetStatisticService $statisticService, UserRepository $userRepository): Response
    {
        $data = $this->getData($request, $statisticService, $userRepository);

        $content = $this->renderView('reporting/work_hours_by_month_export.html.twig', $data);

        $reader = new Html();
        $spreadsheet = $reader->loadFromString($content);

        $writer = new BinaryFileResponseWriter(new XlsxWriter(), 'kimai-export-work-hours-daily');

        return $writer->getFileResponse($spreadsheet);
    }

    private function getData(Request $request, TimesheetStatisticService $statisticService, UserRepository $userRepository): array
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory();

        $values = new WorkHoursByMonth();
        $values->setDate($dateTimeFactory->createStartOfYear());

        $form = $this->createFormForGetRequest(WorkHoursByMonthForm::class, $values, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
        ]);

        $form->submit($request->query->all(), false);

        if ($form->isSubmitted() && !$form->isValid()) {
            $values->setDate($dateTimeFactory->createStartOfYear());
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->createStartOfYear());
        }

        $selectedUsers = $values->getUsers()->toArray();

        // no selection means: all users the current one is allowed to see
        if (\count($selectedUsers) === 0) {
            $userQuery = new UserQuery();
            $userQuery->setVisibility(VisibilityInterface::SHOW_BOTH);
            $userQuery->setSystemAccount(false);
            $userQuery->setCurrentUser($currentUser);
            $selectedUsers = $userRepository->getUsersForQuery($userQuery);
        }

        usort($selectedUsers, fn (User $a, User $b) => strtolower($a->getDisplayName()) <=> strtolower($b->getDisplayName()));

        $start = $dateTimeFactory->createStartOfYear($values->getDate());
        $end = $dateTimeFactory->createEndOfYear($values->getDate());

        $rows = [];
        $userTotals = [];
        $total = 0;

        if (\count($selectedUsers) > 0) {
            $statsQuery = new TimesheetStatisticQuery($start, $end, $selectedUsers);
            $statsQuery->setExcludedProjectNames(self::EXCLUDED_PROJECTS);
            $statistics = $statisticService->getDailyStatistics($statsQuery);

            $statisticsByUser = [];
            foreach ($statistics as $statistic) {
                $statisticsByUser[(string) $statistic->getUser()->getId()] = $statistic;
            }

            foreach ($selectedUsers as $user) {
                $userTotals[(string) $user->getId()] = 0;
            }

            foreach ($statistics[0]->getDateTimes() as $date) {
                $reportDate = $date->format('Y-m-d');
                $durations = [];
                $rowTotal = 0;

                foreach ($selectedUsers as $user) {
                    $userId = (string) $user->getId();
                    $day = $statisticsByUser[$userId]->getDayByReportDate($reportDate);
                    $duration = $day !== null ? $day->getTotalDuration() : 0;
                    $durations[$userId] = [
                        'duration' => $duration,
                        'billable' => $day !== null ? $day->getBillableDuration() : 0,
                    ];
                    $rowTotal += $duration;
                    $userTotals[$userId] += $duration;
                }

                $rows[] = [
                    'date' => $date,
                    'durations' => $durations,
                    'total' => $rowTotal,
                ];
                $total += $rowTotal;
            }
        }

        return [
            'report_title' => 'report_work_hours_month',
            'box_id' => 'work-hours-month-reporting-box',
            'export_route' => 'report_work_hours_month_export',
            'decimal' => $values->isDecimal(),
            'form' => $form->createView(),
            'users' => $selectedUsers,
            'year' => $start,
            'rows' => $rows,
            'userTotals' => $userTotals,
            'total' => $total,
            'hasData' => $total > 0,
        ];
    }
}
