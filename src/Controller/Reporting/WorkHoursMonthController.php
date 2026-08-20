<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Reporting;

use App\Controller\AbstractController;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Reporting\WorkHoursByMonth\WorkHoursByMonth;
use App\Reporting\WorkHoursByMonth\WorkHoursByMonthForm;
use App\Repository\Query\TimesheetStatisticQuery;
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
    public function report(Request $request, TimesheetStatisticService $statisticService): Response
    {
        return $this->render(
            'reporting/work_hours_by_month.html.twig',
            $this->getData($request, $statisticService)
        );
    }

    #[Route(path: '/work_hours_month_export', name: 'report_work_hours_month_export', methods: ['GET', 'POST'])]
    public function export(Request $request, TimesheetStatisticService $statisticService): Response
    {
        $data = $this->getData($request, $statisticService);

        $content = $this->renderView('reporting/work_hours_by_month_export.html.twig', $data);

        $reader = new Html();
        $spreadsheet = $reader->loadFromString($content);

        $writer = new BinaryFileResponseWriter(new XlsxWriter(), 'kimai-export-work-hours-daily');

        return $writer->getFileResponse($spreadsheet);
    }

    private function getData(Request $request, TimesheetStatisticService $statisticService): array
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory();

        $values = new WorkHoursByMonth();
        $values->setUser($currentUser);
        $values->setDate($dateTimeFactory->createStartOfYear());

        $form = $this->createFormForGetRequest(WorkHoursByMonthForm::class, $values, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
        ]);

        $form->submit($request->query->all(), false);

        if ($form->isSubmitted() && !$form->isValid()) {
            $values->setDate($dateTimeFactory->createStartOfYear());
            $values->setUser($currentUser);
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->createStartOfYear());
        }

        $selectedUser = $values->getUser() ?? $currentUser;
        $start = $dateTimeFactory->createStartOfYear($values->getDate());
        $end = $dateTimeFactory->createEndOfYear($values->getDate());

        $statsQuery = new TimesheetStatisticQuery($start, $end, [$selectedUser]);
        $statsQuery->setExcludedProjectNames(self::EXCLUDED_PROJECTS);
        $dayStats = $statisticService->getDailyStatistics($statsQuery);

        $days = \count($dayStats) === 0 ? [] : $dayStats[0]->getDays();

        $total = 0;
        foreach ($days as $day) {
            $total += $day->getTotalDuration();
        }

        return [
            'report_title' => 'report_work_hours_month',
            'box_id' => 'work-hours-month-reporting-box',
            'export_route' => 'report_work_hours_month_export',
            'decimal' => $values->isDecimal(),
            'form' => $form->createView(),
            'user' => $selectedUser,
            'year' => $start,
            'days' => $days,
            'total' => $total,
            'hasData' => $total > 0,
        ];
    }
}
